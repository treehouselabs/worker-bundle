# Executors

So we have a _queue_ and a _manager_ that gives us _jobs_ from it, how do we
actually _work_ it?

The QueueManager uses a concept that we call an executor. An executor is a
piece of code (a service) that processes a job's _payload_. The payload is the
data of a job, and it is stored as a string. The QueueManager uses JSON to
encode/decode the strings, so you can use array's as payload.

An executor is an instance of [ExecutorInterface][0] and as such it implements
these methods:

```php

class Executor implements ExecutorInterface
{
    public function getName()
    {
        return 'user.mail.registration';
    }

    public function execute(array $payload)
    {
        $userId = array_shift($payload);
        $user = $this->findUser($userId);

        // send a registration mail
        $this->mailer->sendRegistrationMail($user);

        return true;
    }
}
```

[0]: src/TreeHouse/WorkerBundle/Executor/ExecutorInterface.php

These executors need to be registered in the QueueManager. This is required
when adding a job or requesting one! When you create a service for this
executor and tag it with `tree_house.worker.executor`, the executor gets
registered automatically.

## Object payloads

We mentioned in the [previous chapter][1] that you can add jobs for objects.
To do this, the executor needs to implement the [ObjectPayloadInterface][2],
which tells the QueueManager how to construct a payload from an object.
Continuing from our previous example:

```php

class Executor implements ExecutorInterface, ObjectPayloadInterface
{
    // ...

    public function supportsObject($object)
    {
        return $object instanceof User;
    }

    public function getObjectPayload($object)
    {
        return ['id' => $object->getId()];
    }
}
```

[1]: /docs/2-queue-manager.md#adding-for-objects
[2]: src/TreeHouse/WorkerBundle/Executor/ObjectPayloadInterface.php

## Configuring payloads

...

## Next

