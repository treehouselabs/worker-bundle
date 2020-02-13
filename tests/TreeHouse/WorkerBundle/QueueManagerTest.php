<?php

namespace TreeHouse\WorkerBundle\Tests;

use Pheanstalk\Exception\ServerException;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;
use Pheanstalk\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TreeHouse\WorkerBundle\Event\ExecutionEvent;
use TreeHouse\WorkerBundle\Event\JobBuriedEvent;
use TreeHouse\WorkerBundle\Event\JobEvent;
use TreeHouse\WorkerBundle\Exception\AbortException;
use TreeHouse\WorkerBundle\Exception\RescheduleException;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;
use TreeHouse\WorkerBundle\Executor\ObjectPayloadInterface;
use TreeHouse\WorkerBundle\QueueManager;
use TreeHouse\WorkerBundle\Tests\Executor\FatalErrorExecutor;
use TreeHouse\WorkerBundle\Tests\Mock\EventDispatcherMock;
use TreeHouse\WorkerBundle\WorkerEvents;

class QueueManagerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PheanstalkInterface
     */
    protected $pheanstalk;

    /**
     * @var EventDispatcherMock
     */
    protected $dispatcher;

    /**
     * @var QueueManager
     */
    protected $manager;

    protected function setUp()
    {
        $this->pheanstalk = $this->getMockForAbstractClass(PheanstalkInterface::class);
        $this->dispatcher = new EventDispatcherMock();

        $this->manager = new QueueManager($this->pheanstalk, $this->dispatcher);
    }

    public function testGetPheanstalk()
    {
        $this->assertSame($this->pheanstalk, $this->manager->getPheanstalk());
    }

    public function testGetDispatcher()
    {
        $this->assertSame($this->dispatcher, $this->manager->getDispatcher());
    }

    public function testGetSetExecutors()
    {
        $executor = new Executor();
        $name = $executor->getName();

        $this->assertFalse($this->manager->hasExecutor($name));
        $this->manager->addExecutor($executor);
        $this->assertTrue($this->manager->hasExecutor($name));
        $this->assertSame($executor, $this->manager->getExecutor($name));
        $this->assertEquals([$name => $executor], $this->manager->getExecutors());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddDuplicateExecutor()
    {
        $executor = new Executor();

        $this->manager->addExecutor($executor);
        $this->manager->addExecutor($executor);
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetMissingExecutor()
    {
        $this->manager->getExecutor('foo');
    }

    public function testActionStats()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $stats  = ['foo' => 'bar'];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsTube')
            ->with($action)
            ->will($this->returnValue($stats))
        ;

        $this->assertEquals($stats, $this->manager->getActionStats($action));
    }

    public function testStatsForMissingAction()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsTube')
            ->with($action)
            ->will($this->throwException(
                new ServerException(sprintf('Server reported %s', Response::RESPONSE_NOT_FOUND))
            ))
        ;

        $this->assertNull($this->manager->getActionStats($action));
    }

    /**
     * @expectedException \Pheanstalk\Exception\ServerException
     */
    public function testStatsForFailingAction()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsTube')
            ->with($action)
            ->will($this->throwException(new ServerException('Oh noes!')))
        ;

        $this->manager->getActionStats($action);
    }

    public function testAddWithDefaults()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $payload = ['data'];
        $expectedPayload = $payload;
        $expectedPayload['__rescheduleTime'] = '10min';

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with($action, json_encode($expectedPayload), PheanstalkInterface::DEFAULT_PRIORITY, PheanstalkInterface::DEFAULT_DELAY, PheanstalkInterface::DEFAULT_TTR)
            ->will($this->returnValue(1234))
        ;

        $this->assertEquals(1234, $this->manager->add($action, $payload));
    }



    public function testAddWithArguments()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $payload  = ['data'];
        $priority = 10;
        $delay    = 10;
        $ttr      = 1200;
        $rescheduleTime = '60min';
        $expectedPayload = $payload;
        $expectedPayload['__rescheduleTime'] = '10min';

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with($action, json_encode($expectedPayload), $priority, $delay, $ttr)
            ->will($this->returnValue(1234))
        ;

        $this->assertEquals(1234, $this->manager->add($action, $payload, $delay, $priority, $ttr,$rescheduleTime));
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage The priority for a job cannot be negative
     */
    public function testAddWithNegativePriority()
    {
        $executor = new Executor();
        $action   = $executor->getName();

        $this->manager->addExecutor($executor);
        $this->manager->add($action, [], null, -1);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage You cannot schedule a job in the past
     */
    public function testAddWithNegativeDelay()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->manager->add($action, [], -10);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage You cannot schedule a job in the past
     */
    public function testAddWithNegativeStringDelay()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->manager->add($action, [], -60);
    }

    public function testAddWithStringDelay()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $payload  = ['data'];
        $priority = 10;
        $ttr      = 1200;

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with($action, json_encode($payload), $priority, 60, $ttr)
            ->will($this->returnValue(1234))
        ;

        $this->assertEquals(1234, $this->manager->add($action, $payload, '1 minute', $priority, $ttr));
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Action "test" is not defined in QueueManager
     */
    public function testAddForMissingAction()
    {
        $this->manager->add('test', []);
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Implement the ObjectPayloadInterface
     */
    public function testAddForObjectMissingInterface()
    {
        $executor = new Executor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->manager->addForObject($action, new \stdClass());
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage does not support stdClass objects
     */
    public function testAddForUnsupportedObject()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->manager->addForObject($action, new \stdClass());
    }

    public function testAddForObjectWithDefaults()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with($action, json_encode(['test']), PheanstalkInterface::DEFAULT_PRIORITY, PheanstalkInterface::DEFAULT_DELAY, PheanstalkInterface::DEFAULT_TTR)
            ->will($this->returnValue(1234))
        ;

        $this->assertEquals(1234, $this->manager->addForObject($action, new \ArrayObject()));
    }

    public function testAddForObjectWithArguments()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $priority = 10;
        $delay    = 10;
        $ttr      = 1200;

        $this->pheanstalk
            ->expects($this->once())
            ->method('putInTube')
            ->with($action, json_encode(['test']), $priority, $delay, $ttr)
            ->will($this->returnValue(1234))
        ;

        $this->assertEquals(1234, $this->manager->addForObject($action, new \ArrayObject(), $delay, $priority, $ttr));
    }

    public function testReschedule()
    {
        $delay = 10;
        $date  = new \DateTime(sprintf('+ %d seconds', $delay));
        $job   = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job, PheanstalkInterface::DEFAULT_PRIORITY, $delay)
        ;

        $this->manager->reschedule($job, $date);
    }

    public function testRescheduleWithPriority()
    {
        $delay = 10;
        $date  = new \DateTime(sprintf('+ %d seconds', $delay));
        $job   = new Job(1234, 'data');
        $priority = PheanstalkInterface::DEFAULT_PRIORITY - 1;

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job, $priority, $delay)
        ;

        $this->manager->reschedule($job, $date, $priority);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage You cannot reschedule a job in the past
     */
    public function testReschedulePastDate()
    {
        $job  = new Job(1234, 'data');
        $date = new \DateTime('-1 minute');

        $this->manager->reschedule($job, $date);
    }

    public function testWatch()
    {
        $action = 'test';

        $this->pheanstalk
            ->expects($this->once())
            ->method('watch')
            ->with($action)
        ;

        $this->manager->watch($action);
    }

    public function testWatchMultiple()
    {
        $actions = ['test1', 'test2'];

        foreach ($actions as $index => $action) {
            $this->pheanstalk
                ->expects($this->at($index))
                ->method('watch')
                ->with($action)
            ;
        }

        $this->manager->watch($actions);
    }

    public function testWatchOnly()
    {
        $action = 'test1';

        $this->pheanstalk
            ->expects($this->once())
            ->method('listTubesWatched')
            ->will($this->returnValue(['test']))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('ignore')
            ->with('test')
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('watch')
            ->with($action)
        ;

        $this->manager->watchOnly($action);
    }

    public function testIgnore()
    {
        $action = 'test';

        $this->pheanstalk
            ->expects($this->once())
            ->method('ignore')
            ->with($action)
        ;

        $this->manager->ignore($action);
    }

    public function testGet()
    {
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('reserve')
            ->will($this->returnValue($job))
        ;

        $this->assertSame($job, $this->manager->get());
    }

    public function testGetTimeout()
    {
        $this->pheanstalk
            ->expects($this->once())
            ->method('reserve')
            ->with(10)
            ->will($this->returnValue(false))
        ;

        $this->assertFalse($this->manager->get(10));
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Action "test" is not defined in QueueManager
     */
    public function testPeekInvalidAction()
    {
        $this->manager->peek('test');
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage $state must be one of
     */
    public function testPeekInvalidState()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->manager->peek($action, 'foo');
    }

    public function testPeek()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('peekReady')
            ->with($action)
            ->will($this->returnValue($job))
        ;

        $this->assertSame($job, $this->manager->peek($action));
    }

    public function testPeekMissingAction()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->pheanstalk
            ->expects($this->once())
            ->method('peekReady')
            ->with($action)
            ->will($this->throwException(
                new ServerException(sprintf('Server reported %s', Response::RESPONSE_NOT_FOUND))
            ))
        ;

        $this->assertNull($this->manager->peek($action));
    }

    public function testDelete()
    {
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('delete')
            ->with($job)
        ;

        $this->manager->delete($job);
    }

    public function testBury()
    {
        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->once())
            ->method('bury')
            ->with($job)
        ;

        $this->manager->bury($job);
    }

    public function testKick()
    {
        $action = 'test';
        $max    = 10;
        $kicked = 5;

        $this->pheanstalk
            ->expects($this->once())
            ->method('useTube')
            ->with($action)
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('kick')
            ->with($max)
            ->will($this->returnValue($kicked))
        ;

        $this->assertEquals($kicked, $this->manager->kick($action, $max));
    }

    public function testJobStats()
    {
        $job = new Job(1234, 'data');
        $stats = ['foo' => 'bar'];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->assertEquals($stats, $this->manager->getJobStats($job));
    }

    public function testClear()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $job = new Job(1234, 'data');

        $this->pheanstalk
            ->expects($this->at(0))
            ->method('peekReady')
            ->will($this->returnValue($job))
        ;

        $this->pheanstalk
            ->expects($this->at(1))
            ->method('peekReady')
            ->will($this->throwException(
                new ServerException(sprintf('Server reported %s', Response::RESPONSE_NOT_FOUND))
            ))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('delete')
            ->with($job)
        ;

        $this->manager->clear($action, ['ready']);
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage $state must be one of
     */
    public function testClearInvalidState()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->manager->clear($action, ['foo']);
    }

    public function testExecute()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->assertEquals('done & done!', $this->manager->execute($action, [1]));
    }

    public function testExecuteInvalidPayload()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $this->assertFalse($this->manager->execute($action, []));
    }

    public function testExecuteJob()
    {
        $executor = new ObjectExecutor();
        $action   = $executor->getName();
        $this->manager->addExecutor($executor);

        $data  = [1];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('delete')
            ->with($job)
        ;

        $this->assertEquals('done & done!', $this->manager->executeJob($job));

        $events = $this->dispatcher->getDispatchedEvents();

        // test for a execute event
        $this->assertArrayHasKey(WorkerEvents::EXECUTE_JOB, $events);
        $this->assertNotEmpty($events[WorkerEvents::EXECUTE_JOB]);
        $event = end($events[WorkerEvents::EXECUTE_JOB]);
        $this->assertInstanceOf(JobEvent::class, $event);
        $this->assertSame($job, $event->getJob());

        // test for a pre-execute event
        $this->assertArrayHasKey(WorkerEvents::PRE_EXECUTE_ACTION, $events);
        $this->assertNotEmpty($events[WorkerEvents::PRE_EXECUTE_ACTION]);
        $event = end($events[WorkerEvents::PRE_EXECUTE_ACTION]);
        $this->assertInstanceOf(ExecutionEvent::class, $event);
        $this->assertSame($data, $event->getPayload());

        // test for a post-execute event
        $this->assertArrayHasKey(WorkerEvents::PRE_EXECUTE_ACTION, $events);
        $this->assertNotEmpty($events[WorkerEvents::PRE_EXECUTE_ACTION]);
        $event = end($events[WorkerEvents::PRE_EXECUTE_ACTION]);
        $this->assertInstanceOf(ExecutionEvent::class, $event);
        $this->assertSame('done & done!', $event->getResult());
    }

    public function testExecuteJobAndReschedule()
    {
        $action   = 'test';
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $executor->expects($this->any())->method('getName')->will($this->returnValue($action));
        $this->manager->addExecutor($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job, PheanstalkInterface::DEFAULT_PRIORITY, 10)
        ;

        $executor
            ->expects($this->once())
            ->method('execute')
            ->will($this->throwException(RescheduleException::create('10 seconds')))
        ;

        $this->assertFalse($this->manager->executeJob($job));
    }

    public function testExecuteJobAndRescheduleWithNewPriority()
    {
        $action   = 'test';
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $executor->expects($this->any())->method('getName')->will($this->returnValue($action));
        $this->manager->addExecutor($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job, PheanstalkInterface::DEFAULT_PRIORITY - 1, 10)
        ;

        $executor
            ->expects($this->once())
            ->method('execute')
            ->will($this->throwException(RescheduleException::create('10 seconds', null, PheanstalkInterface::DEFAULT_PRIORITY -1 )))
        ;

        $this->assertFalse($this->manager->executeJob($job));
    }


    /**
     * @expectedException \TreeHouse\WorkerBundle\Exception\AbortException
     */
    public function testExecuteJobAndAbort()
    {
        $action   = 'test';
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $executor->expects($this->any())->method('getName')->will($this->returnValue($action));
        $this->manager->addExecutor($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $executor
            ->expects($this->once())
            ->method('execute')
            ->will($this->throwException(AbortException::create('because...reasons')))
        ;

        $this->manager->executeJob($job);
    }

    public function testExecuteJobAndRetry()
    {
        $action   = 'test';
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $executor->expects($this->any())->method('getName')->will($this->returnValue($action));
        $this->manager->addExecutor($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 0,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('release')
            ->with($job)
        ;

        $executor
            ->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new \Exception('oh noes!')))
        ;

        $this->manager->executeJob($job);
    }

    public function testExecuteJobAndNoMoreRetries()
    {
        $action   = 'test';
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $executor->expects($this->any())->method('getName')->will($this->returnValue($action));
        $this->manager->addExecutor($executor);

        $data  = [];
        $job   = new Job(1234, json_encode($data));
        $stats = [
            'tube'     => $action,
            'releases' => 2,
            'pri'      => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('bury')
            ->with($job)
        ;

        $executor
            ->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new \Exception('oh noes!')))
        ;

        $this->manager->executeJob($job);
    }

    public function testFatalErrorCatch()
    {
        $executor = new FatalErrorExecutor();
        $this->manager->addExecutor($executor);

        $data = [];
        $job = new Job(1234, json_encode($data));
        $stats = [
            'tube' => 'fatal.error',
            'releases' => 0,
            'pri' => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->assertEquals(false, $this->manager->executeJob($job));
    }

    public function testFatalErrorCatchWithNoRetries()
    {
        $executor = new FatalErrorExecutor();
        $this->manager->addExecutor($executor);

        $data = [];
        $job = new Job(1234, json_encode($data));
        $stats = [
            'tube' => 'fatal.error',
            'releases' => 2,
            'pri' => PheanstalkInterface::DEFAULT_PRIORITY,
        ];

        $this->pheanstalk
            ->expects($this->once())
            ->method('statsJob')
            ->with($job)
            ->will($this->returnValue($stats))
        ;

        $this->pheanstalk
            ->expects($this->once())
            ->method('bury')
            ->with($job)
        ;

        $this->assertEquals(false, $this->manager->executeJob($job));

        $events = $this->dispatcher->getDispatchedEvents();
        static::assertArrayHasKey(WorkerEvents::JOB_BURIED_EVENT, $events);
        static::assertCount(1, $events[WorkerEvents::JOB_BURIED_EVENT]);
        static::assertInstanceOf(JobBuriedEvent::class, $events[WorkerEvents::JOB_BURIED_EVENT][0]);
    }
}

class Executor implements ExecutorInterface
{
    public function getName()
    {
        return 'test';
    }

    public function execute(array $payload)
    {
        return 'done & done!';
    }

    public function configurePayload(OptionsResolver $resolver)
    {
        $resolver->setRequired(0);
    }
}

class ObjectExecutor extends Executor implements ObjectPayloadInterface
{
    public function getName()
    {
        return 'object.test';
    }

    public function supportsObject($object)
    {
        return $object instanceof \ArrayAccess;
    }

    public function getObjectPayload($object)
    {
        return ['test'];
    }
}
