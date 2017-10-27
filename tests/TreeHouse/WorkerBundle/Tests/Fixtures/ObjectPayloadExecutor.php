<?php

namespace TreeHouse\WorkerBundle\Tests\Fixtures;

use ArrayAccess;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;
use TreeHouse\WorkerBundle\Executor\ObjectPayloadInterface;

class ObjectPayloadExecutor implements ObjectPayloadInterface, ExecutorInterface
{
    public function getName()
    {
        return 'object.test';
    }

    public function execute(array $payload)
    {
        return 'done & done!';
    }

    public function configurePayload(OptionsResolver $resolver)
    {
        $resolver->setRequired(0);
    }

    public function supportsObject($object)
    {
        return $object instanceof ArrayAccess;
    }

    public function getObjectPayload($object)
    {
        return ['test'];
    }
}
