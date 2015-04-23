<?php

namespace TreeHouse\WorkerBundle\Executor;

interface ObjectPayloadInterface
{
    /**
     * Whether the given object is supported.
     *
     * @param object $object
     *
     * @return bool
     */
    public function supportsObject($object);

    /**
     * Returns the payload for a given object.
     *
     * @param object $object
     *
     * @return array
     */
    public function getObjectPayload($object);
}
