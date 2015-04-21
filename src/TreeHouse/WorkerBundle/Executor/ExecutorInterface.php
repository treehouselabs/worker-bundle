<?php

namespace TreeHouse\WorkerBundle\Executor;

interface ExecutorInterface
{
    /**
     * The name of this executor, corresponds directly with a tube name.
     * The naming convention is the same as that of services and events, ie:
     * lowercased, separated by periods, eg: `entity.update`.
     *
     * @return string
     */
    public function getName();

    /**
     * Executes a job with given payload
     *
     * @param  array  $payload
     *
     * @return mixed
     */
    public function execute(array $payload);
}
