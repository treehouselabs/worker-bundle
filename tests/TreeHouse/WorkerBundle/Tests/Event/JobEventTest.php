<?php

namespace TreeHouse\WorkerBundle\Tests\Event;

use Pheanstalk\Job;
use TreeHouse\WorkerBundle\Event\JobEvent;

class JobEventTest extends \PHPUnit_Framework_TestCase
{
    public function testEvent()
    {
        $job = new Job(1234, 'data');

        $event = new JobEvent($job);

        $this->assertSame($job, $event->getJob());
    }
}
