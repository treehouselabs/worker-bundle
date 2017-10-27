<?php

namespace TreeHouse\WorkerBundle\Tests;

use DateTime;
use InvalidArgumentException;
use Pheanstalk\Exception\ServerException;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;
use Pheanstalk\Response;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use TreeHouse\WorkerBundle\Executor\ObjectPayloadInterface;
use TreeHouse\WorkerBundle\ExecutorPool;
use TreeHouse\WorkerBundle\Queue;
use TreeHouse\WorkerBundle\Tests\Mock\EventDispatcherMock;

class QueueTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PheanstalkInterface
     */
    protected $pheanstalk;
    /**
     * @var EventDispatcherMock
     */
    protected $dispatcher;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var ExecutorPool
     */
    protected $executorPool;
    /**
     * @var Queue
     */
    protected $queue;

    protected function setUp()
    {
        $this->pheanstalk = $this->getMockForAbstractClass(PheanstalkInterface::class);
        $this->executorPool = $this->getMock(ExecutorPool::class);
        $this->logger = $this->getMock(LoggerInterface::class);

        $this->queue = new Queue($this->pheanstalk, $this->executorPool, $this->logger);
    }

    public function testGetPheanstalk()
    {
        $this->assertSame($this->pheanstalk, $this->queue->getPheanstalk());
    }

    public function testActionStats()
    {
        $stats = ['foo' => 'bar'];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsTube')
            ->with('action')
            ->will($this->returnValue($stats));

        $this->assertEquals($stats, $this->queue->getActionStats('action'));
    }

    public function testStatsForMissingAction()
    {
        $this->pheanstalk
            ->expects($this->once())
            ->method('statsTube')
            ->with('action')
            ->will(
                $this->throwException(
                    new ServerException(sprintf('Server reported %s', Response::RESPONSE_NOT_FOUND))
                )
            );

        $this->assertNull($this->queue->getActionStats('action'));
    }

    /**
     * @expectedException \Pheanstalk\Exception\ServerException
     */
    public function testStatsForFailingAction()
    {
        $this->pheanstalk
            ->expects($this->once())
            ->method('statsTube')
            ->with('action')
            ->will($this->throwException(new ServerException('Oh noes!')));

        $this->queue->getActionStats('action');
    }

    public function testAddWithDefaults()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $payload = ['data'];

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with(
                'action',
                json_encode($payload),
                PheanstalkInterface::DEFAULT_PRIORITY,
                PheanstalkInterface::DEFAULT_DELAY,
                PheanstalkInterface::DEFAULT_TTR
            )
            ->will($this->returnValue(1234));

        $this->assertEquals(1234, $this->queue->add('action', $payload));
    }

    public function testAddWithArguments()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $payload = ['data'];
        $priority = 10;
        $delay = 10;
        $ttr = 1200;

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with('action', json_encode($payload), $priority, $delay, $ttr)
            ->will($this->returnValue(1234));

        $this->assertEquals(1234, $this->queue->add('action', $payload, $delay, $priority, $ttr));
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage The priority for a job cannot be negative
     */
    public function testAddWithNegativePriority()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $this->queue->add('action', [], null, -1);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage You cannot schedule a job in the past
     */
    public function testAddWithNegativeDelay()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $this->queue->add('action', [], -10);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage You cannot schedule a job in the past
     */
    public function testAddWithNegativeStringDelay()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $this->queue->add('action', [], '-1 minute');
    }

    public function testAddWithStringDelay()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $payload = ['data'];
        $priority = 10;
        $ttr = 1200;

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with('action', json_encode($payload), $priority, 60, $ttr)
            ->will($this->returnValue(1234));

        $this->assertEquals(1234, $this->queue->add('action', $payload, '1 minute', $priority, $ttr));
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Action "action" is not defined in QueueManager
     */
    public function testAddForMissingAction()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(false);

        $this->queue->add('action', []);
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Implement the ObjectPayloadInterface
     */
    public function testAddForObjectMissingInterface()
    {
        $this->executorPool->method('getExecutor')
            ->with('action')
            ->willReturn(new stdClass());

        $this->queue->addForObject('action', new \stdClass());
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage does not support stdClass objects
     */
    public function testAddForUnsupportedObject()
    {
        $payload = new stdClass();

        $executor = $this->getMock(ObjectPayloadInterface::class);
        $executor->method('supportsObject')
            ->with($payload)
            ->willReturn(false);

        $this->executorPool->method('getExecutor')
            ->with('action')
            ->willReturn($executor);

        $this->queue->addForObject('action', $payload);
    }

    public function testAddForObjectWithDefaults()
    {
        $payload = new stdClass();

        $executor = $this->getMock(ObjectPayloadInterface::class);
        $executor->method('supportsObject')
            ->with($payload)
            ->willReturn(true);
        $executor->method('getObjectPayload')
            ->with($payload)
            ->willReturn(['test']);

        $this->executorPool->method('getExecutor')
            ->with('action')
            ->willReturn($executor);

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with(
                'action',
                json_encode(['test']),
                PheanstalkInterface::DEFAULT_PRIORITY,
                PheanstalkInterface::DEFAULT_DELAY,
                PheanstalkInterface::DEFAULT_TTR
            )
            ->will($this->returnValue(1234));

        $this->assertEquals(1234, $this->queue->addForObject('action', $payload));
    }

    public function testAddForObjectWithArguments()
    {
        $payload = new stdClass();

        $executor = $this->getMock(ObjectPayloadInterface::class);
        $executor->method('supportsObject')
            ->with($payload)
            ->willReturn(true);
        $executor->method('getObjectPayload')
            ->with($payload)
            ->willReturn(['test']);

        $this->executorPool->method('getExecutor')
            ->with('action')
            ->willReturn($executor);

        $priority = 10;
        $delay = 10;
        $ttr = 1200;

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with('action', json_encode(['test']), $priority, $delay, $ttr)
            ->will($this->returnValue(1234));

        $this->assertEquals(1234, $this->queue->addForObject('action', $payload, $delay, $priority, $ttr));
    }

    public function testReschedule()
    {
        $delay = 10;
        $date = new DateTime(sprintf('+ %d seconds', $delay));
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job, PheanstalkInterface::DEFAULT_PRIORITY, $delay);

        $this->queue->reschedule($job, $date);
    }

    public function testRescheduleWithPriority()
    {
        $delay = 10;
        $date = new DateTime(sprintf('+ %d seconds', $delay));
        $job = new Job(1234, 'data');
        $priority = PheanstalkInterface::DEFAULT_PRIORITY - 1;

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job, $priority, $delay);

        $this->queue->reschedule($job, $date, $priority);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage You cannot reschedule a job in the past
     */
    public function testReschedulePastDate()
    {
        $job = new Job(1234, 'data');
        $date = new DateTime('-1 minute');

        $this->queue->reschedule($job, $date);
    }

    public function testWatch()
    {
        $action = 'test';

        $this->pheanstalk
            ->expects($this->once())
            ->method('watch')
            ->with($action);

        $this->queue->watch($action);
    }

    public function testWatchMultiple()
    {
        $actions = ['test1', 'test2'];

        foreach ($actions as $index => $action) {
            $this->pheanstalk
                ->expects($this->at($index))
                ->method('watch')
                ->with($action);
        }

        $this->queue->watch($actions);
    }

    public function testWatchOnly()
    {
        $action = 'test1';

        $this->pheanstalk
            ->expects($this->once())
            ->method('listTubesWatched')
            ->will($this->returnValue(['test']));

        $this->pheanstalk
            ->expects($this->once())
            ->method('ignore')
            ->with('test');

        $this->pheanstalk
            ->expects($this->once())
            ->method('watch')
            ->with($action);

        $this->queue->watchOnly($action);
    }

    public function testIgnore()
    {
        $action = 'test';

        $this->pheanstalk
            ->expects($this->once())
            ->method('ignore')
            ->with($action);

        $this->queue->ignore($action);
    }

    public function testGet()
    {
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('reserve')
            ->will($this->returnValue($job));

        $this->assertSame($job, $this->queue->get());
    }

    public function testGetTimeout()
    {
        $this->pheanstalk
            ->expects($this->once())
            ->method('reserve')
            ->with(10)
            ->will($this->returnValue(false));

        $this->assertFalse($this->queue->get(10));
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Action "action" is not defined in QueueManager
     */
    public function testPeekInvalidAction()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(false);

        $this->queue->peek('action');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage $state must be one of
     */
    public function testPeekInvalidState()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $this->queue->peek('action', 'foo');
    }

    public function testPeek()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('peekReady')
            ->with('action')
            ->will($this->returnValue($job));

        $this->assertSame($job, $this->queue->peek('action'));
    }

    public function testPeekMissingAction()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $this->pheanstalk
            ->expects($this->once())
            ->method('peekReady')
            ->with('action')
            ->will(
                $this->throwException(
                    new ServerException(sprintf('Server reported %s', Response::RESPONSE_NOT_FOUND))
                )
            );

        $this->assertNull($this->queue->peek('action'));
    }

    public function testDelete()
    {
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('delete')
            ->with($job);

        $this->queue->delete($job);
    }

    public function testBury()
    {
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('bury')
            ->with($job);

        $this->queue->bury($job);
    }

    public function testKick()
    {
        $action = 'action';
        $max = 10;
        $kicked = 5;

        $this->pheanstalk
            ->expects($this->once())
            ->method('useTube')
            ->with($action);

        $this->pheanstalk
            ->expects($this->once())
            ->method('kick')
            ->with($max)
            ->will($this->returnValue($kicked));

        $this->assertEquals($kicked, $this->queue->kick($action, $max));
    }

    public function testJobStats()
    {
        $job = new Job(1234, 'data');
        $stats = ['foo' => 'bar'];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats));

        $this->assertEquals($stats, $this->queue->getJobStats($job));
    }

    public function testClear()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->at(0))
            ->method('peekReady')
            ->will($this->returnValue($job));

        $this->pheanstalk
            ->expects($this->at(1))
            ->method('peekReady')
            ->will(
                $this->throwException(
                    new ServerException(sprintf('Server reported %s', Response::RESPONSE_NOT_FOUND))
                )
            );

        $this->pheanstalk
            ->expects($this->once())
            ->method('delete')
            ->with($job);

        $this->queue->clear('action', ['ready']);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage $state must be one of
     */
    public function testClearInvalidState()
    {
        $this->executorPool->method('hasExecutor')
            ->with('action')
            ->willReturn(true);

        $this->queue->clear('action', ['foo']);
    }
}
