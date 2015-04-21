<?php

namespace TreeHouse\WorkerBundle\Executor;

abstract class AbstractExecutor implements ExecutorInterface
{
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
