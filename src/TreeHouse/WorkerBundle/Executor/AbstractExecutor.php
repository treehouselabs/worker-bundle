<?php

namespace TreeHouse\WorkerBundle\Executor;

use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractExecutor implements ExecutorInterface
{
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * @inheritdoc
     */
    public function configurePayload(OptionsResolver $resolver)
    {
        // by default no payload is configured, if you are using a payload you
        // must define these options!
    }
}
