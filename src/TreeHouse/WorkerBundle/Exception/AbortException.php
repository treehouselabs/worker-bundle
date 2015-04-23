<?php

namespace TreeHouse\WorkerBundle\Exception;

class AbortException extends \Exception
{
    /**
     * @var string
     */
    private $reason;

    /**
     * Factory method.
     *
     * @param string $reason
     *
     * @return static
     */
    public static function create($reason)
    {
        $exception = new static('Worker aborted');
        $exception->setReason($reason);

        return $exception;
    }

    /**
     * @param string $reason
     */
    public function setReason($reason)
    {
        $this->reason = $reason;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }
}
