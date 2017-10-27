<?php

namespace TreeHouse\WorkerBundle;

use InvalidArgumentException;
use OutOfBoundsException;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;

class ExecutorPool
{
    /**
     * @var ExecutorInterface[]
     */
    protected $executors = [];

    /**
     * @param string $action
     *
     * @return bool
     */
    public function hasExecutor($action)
    {
        return array_key_exists($action, $this->executors);
    }

    /**
     * Add an executor.
     *
     * @param ExecutorInterface $executor
     *
     * @throws InvalidArgumentException
     */
    public function addExecutor(ExecutorInterface $executor)
    {
        $action = $executor->getName();

        if ($this->hasExecutor($action)) {
            throw new InvalidArgumentException(
                sprintf(
                    'There is already an executor registered for action "%s".',
                    $action
                )
            );
        }

        $this->executors[$action] = $executor;
    }

    /**
     * Returns a registered executor for given action.
     *
     * @param string $action
     *
     * @return ExecutorInterface
     *
     * @throws OutOfBoundsException
     */
    public function getExecutor($action)
    {
        if (!$this->hasExecutor($action)) {
            throw new OutOfBoundsException(
                sprintf(
                    'There is no executor registered for action "%s".',
                    $action
                )
            );
        }

        return $this->executors[$action];
    }

    /**
     * @return ExecutorInterface[]
     */
    public function getExecutors()
    {
        return $this->executors;
    }
}
