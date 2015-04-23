<?php

namespace TreeHouse\WorkerBundle\Tests\Event;

use TreeHouse\WorkerBundle\Event\ExecutionEvent;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;

class ExecutionEventTest extends \PHPUnit_Framework_TestCase
{
    public function testEvent()
    {
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $action   = 'test';
        $payload  = ['data'];

        $event = new ExecutionEvent($executor, $action, $payload);

        $this->assertSame($executor, $event->getExecutor());
        $this->assertSame($action, $event->getAction());
        $this->assertSame($payload, $event->getPayload());
    }

    public function testSetPayload()
    {
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $event    = new ExecutionEvent($executor, 'test', ['data']);

        $newPayload = ['new and improved'];
        $event->setPayload($newPayload);
        $this->assertSame($newPayload, $event->getPayload());
    }

    public function testSetResult()
    {
        $executor = $this->getMockForAbstractClass(ExecutorInterface::class);
        $event    = new ExecutionEvent($executor, 'test', ['data']);

        $result = 'done & done';
        $event->setResult($result);
        $this->assertSame($result, $event->getResult());
    }
}
