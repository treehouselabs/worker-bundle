<?php

namespace TreeHouse\WorkerBundle\Event;

use Pheanstalk\Job;
use Symfony\Component\EventDispatcher\Event;

class JobBuriedEvent extends Event
{
    /**
     * Number of attempts before job got buried
     *
     * @var int
     */
    protected $attempts;

    /**
     * @var Job
     */
    protected $job;

    /**
     * Exception that caused the job to be buried
     *
     * @var \Exception
     */
    protected $exception;

    /**
     * JobBuriedEvent constructor.
     *
     * @param Job $job
     * @param \Exception $exception
     * @param int $attempts
     */
    public function __construct(Job $job, \Exception $exception, $attempts)
    {
        $this->job = $job;
        $this->exception = $exception;
        $this->attempts = $attempts;
    }

    /**
     * @return Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->attempts;
    }
}
