<?php

namespace TreeHouse\WorkerBundle\Tests;

use DateTime;
use Exception;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TreeHouse\WorkerBundle\Event\ExecutionEvent;
use TreeHouse\WorkerBundle\Event\JobEvent;
use TreeHouse\WorkerBundle\Exception\AbortException;
use TreeHouse\WorkerBundle\Exception\RescheduleException;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;
use TreeHouse\WorkerBundle\ExecutorPool;
use TreeHouse\WorkerBundle\Queue;
use TreeHouse\WorkerBundle\QueueExecutor;
use TreeHouse\WorkerBundle\Tests\Fixtures\ObjectPayloadExecutor;
use TreeHouse\WorkerBundle\WorkerEvents;

class QueueExecutorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Queue
     */
    private $queue;
    /**
     * @var ExecutorPool
     */
    private $executorPool;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var QueueExecutor
     */
    private $queueExecutor;

    protected function setUp()
    {
        $this->queue = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->executorPool = $this->getMock(ExecutorPool::class);
        $this->dispatcher = $this->getMock(EventDispatcherInterface::class);
        $this->logger = $this->getMock(LoggerInterface::class);

        $this->queueExecutor = new QueueExecutor(
            $this->queue,
            $this->executorPool,
            $this->dispatcher,
            $this->logger
        );
    }


    public function testGetDispatcher()
    {
        $this->assertSame($this->dispatcher, $this->queueExecutor->getDispatcher());
    }

    public function testExecute()
    {
        $executor = new ObjectPayloadExecutor();

        $action = $executor->getName();

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $this->assertEquals('done & done!', $this->queueExecutor->execute($action, [1]));
    }

    public function testExecuteInvalidPayload()
    {
        $executor = new ObjectPayloadExecutor();

        $action = $executor->getName();

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $this->assertFalse($this->queueExecutor->execute($action, []));
    }

    public function testExecuteJob()
    {
        $executor = new ObjectPayloadExecutor();

        $action = $executor->getName();

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $data  = [1];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->queue
            ->expects($this->once())
            ->method('getJobStats')
            ->with($job)
            ->willReturn($stats);

        $this->queue
            ->expects($this->once())
            ->method('delete')
            ->with($job);

        $doneExecutionEvent = new ExecutionEvent($executor, $action, $data);
        $doneExecutionEvent->setResult('done & done!');

        $this->dispatcher->expects($this->exactly(3))
            ->method('dispatch')->withConsecutive([
                WorkerEvents::EXECUTE_JOB, new JobEvent($job)
            ], [
                WorkerEvents::PRE_EXECUTE_ACTION, $this->equalTo(new ExecutionEvent($executor, $action, $data))
            ], [
                WorkerEvents::POST_EXECUTE_ACTION, $this->equalTo($doneExecutionEvent)
            ]);

        $this->assertEquals('done & done!', $this->queueExecutor->executeJob($job));
    }

    public function testExecuteJobAndReschedule()
    {
        $action   = 'test';
        /* @var ExecutorInterface $executor */
        $executor = $this->getMock(ExecutorInterface::class);

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->queue
            ->expects($this->once())
            ->method('getJobStats')
            ->with($job)
            ->willReturn($stats);

        $rescheduleException = RescheduleException::create('10 seconds');

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException($rescheduleException);

        $this->queue
            ->expects($this->once())
            ->method('reschedule')
            ->with($job, $rescheduleException->getRescheduleDate(), PheanstalkInterface::DEFAULT_PRIORITY)
            ->willReturn($stats);

        $this->assertFalse($this->queueExecutor->executeJob($job));
    }

    public function testExecuteJobAndRescheduleWithNewPriority()
    {
        $action   = 'test';
        /* @var ExecutorInterface $executor */
        $executor = $this->getMock(ExecutorInterface::class);

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->queue
            ->expects($this->once())
            ->method('getJobStats')
            ->with($job)
            ->willReturn($stats);

        $rescheduleException = RescheduleException::create('10 seconds', null, PheanstalkInterface::DEFAULT_PRIORITY -1);

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException($rescheduleException);

        $this->queue
            ->expects($this->once())
            ->method('reschedule')
            ->with($job, $rescheduleException->getRescheduleDate(), PheanstalkInterface::DEFAULT_PRIORITY -1)
            ->willReturn($stats);

        $this->assertFalse($this->queueExecutor->executeJob($job));
    }


    /**
     * @expectedException \TreeHouse\WorkerBundle\Exception\AbortException
     */
    public function testExecuteJobAndAbort()
    {
        $action   = 'test';
        /* @var ExecutorInterface $executor */
        $executor = $this->getMock(ExecutorInterface::class);

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->queue
            ->expects($this->once())
            ->method('getJobStats')
            ->with($job)
            ->willReturn($stats);

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(AbortException::create('because...reasons'));

        $this->queueExecutor->executeJob($job);
    }

    public function testExecuteJobAndRetry()
    {
        $action   = 'test';
        /* @var ExecutorInterface $executor */
        $executor = $this->getMock(ExecutorInterface::class);

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->queue
            ->expects($this->once())
            ->method('getJobStats')
            ->with($job)
            ->willReturn($stats);

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('oh noes!'));

        $this->queue
            ->expects($this->once())
            ->method('reschedule')
            ->with($job, $this->callback(function ($dateTime) {
                if (!($dateTime instanceof DateTime)) {
                    return false;
                }

                $expectedTimestamp = (new DateTime('+10 minutes'))
                    ->getTimestamp();

                //Within to two seconds
                //Since this is called later, our time is greater than the one in $dateTime
                return $expectedTimestamp - $dateTime->getTimestamp() <= 2;
            }), PheanstalkInterface::DEFAULT_PRIORITY)
            ->willReturn($stats);

        $this->queueExecutor->executeJob($job);
    }

    public function testExecuteJobAndNoMoreRetries()
    {
        $action   = 'test';
        /* @var ExecutorInterface $executor */
        $executor = $this->getMock(ExecutorInterface::class);

        $this->executorPool->method('getExecutor')
            ->with($action)
            ->willReturn($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 2,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->queue
            ->expects($this->once())
            ->method('getJobStats')
            ->with($job)
            ->willReturn($stats);

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception('oh noes!'));

        $this->queue
            ->expects($this->once())
            ->method('bury')
            ->with($job)
            ->willReturn($stats);

        $this->queueExecutor->executeJob($job);
    }
}
