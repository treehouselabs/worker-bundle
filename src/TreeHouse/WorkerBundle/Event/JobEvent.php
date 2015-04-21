<?php

namespace TreeHouse\WorkerBundle\Event;

use Pheanstalk\Job;
use Symfony\Component\EventDispatcher\Event;

class JobEvent extends Event
{
    /**
     * @var Job
     */
    protected $job;

    /**
     * @param Job $job
     */
    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    /**
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }
}
