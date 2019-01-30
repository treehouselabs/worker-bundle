<?php

namespace TreeHouse\WorkerBundle\Tests\Event;

use Pheanstalk\Job;
use PHPUnit\Framework\TestCase;
use TreeHouse\WorkerBundle\Event\JobEvent;

class JobEventTest extends TestCase
{
    public function testEvent()
    {
        $job = new Job(1234, 'data');

        $event = new JobEvent($job);

        $this->assertSame($job, $event->getJob());
    }
}
