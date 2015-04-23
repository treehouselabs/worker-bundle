<?php

namespace TreeHouse\WorkerBundle\Executor;

use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
     * Executes a job with given payload.
     *
     * @param array $payload
     *
     * @return mixed
     */
    public function execute(array $payload);

    /**
     * Configures the payload for this executor.
     * You can throw an optionsresolver exception if an invalid payload is
     * given to a job, in which case the job will be deleted.
     *
     * @param OptionsResolver $resolver
     *
     * @throws ExceptionInterface
     */
    public function configurePayload(OptionsResolver $resolver);
}
