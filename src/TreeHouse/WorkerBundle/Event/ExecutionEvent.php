<?php

namespace TreeHouse\WorkerBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;

class ExecutionEvent extends Event
{
    /**
     * @var ExecutorInterface
     */
    protected $executor;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var mixed
     */
    protected $result;

    /**
     * @param ExecutorInterface $executor
     * @param string            $action
     * @param array             $payload
     */
    public function __construct(ExecutorInterface $executor, $action, array $payload)
    {
        $this->executor = $executor;
        $this->action   = $action;
        $this->payload  = $payload;
    }

    /**
     * @return ExecutorInterface
     */
    public function getExecutor()
    {
        return $this->executor;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param array $payload
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
    }

    /**
     * @return array
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }
}
