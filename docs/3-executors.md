# Executors

So we have a _queue_ and a _manager_ that gives us _jobs_ from it, how do we
actually _work_ it?

The QueueManager uses a concept that we call an executor. An executor is a
piece of code (a service) that processes a job's _payload_. The payload is the
data of a job, and it is stored as a string. The QueueManager uses JSON to
encode/decode the strings, so you can use array's as payload.

An executor is an instance of [ExecutorInterface][executor] and as such it
implements these methods:

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

[executor]: src/TreeHouse/WorkerBundle/Executor/ExecutorInterface.php

These executors need to be registered in the QueueManager. This is required
when adding a job or requesting one! When you create a service for this
executor and tag it with `tree_house.worker.executor`, the executor gets
registered automatically.


## Configuring payloads

Since a payload can be any string that we encode/decode, it is useful to make
this more robust and configure the payload that an executor accepts. The
[OptionsResolver component][or] is very useful for this. In our previous
example we are passing around an id for a User object. We want to make
sure that the given payload:
  1. is in fact a numeric value, and not `null`, for example
  2. is a valid identifier

Using the `OptionsResolver` we can ensure both requirements, and in fact even
transform the identifier into a User object!

```php

use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;

class Executor implements ExecutorInterface
{
    // ...

    public function configurePayload(OptionsResolver $resolver)
    {
        $resolver->setRequired(0);
        $resolver->setAllowedTypes(0, 'numeric');
        $resolver->setNormalizer(0, function (Options $options, $value) {
            if (null === $part = $this->findUser($value)) {
                throw new InvalidArgumentException(sprintf('User with id "%d" does not exist', $value));
            }

            return $part;
        });
    }
}
```

[or]: http://symfony.com/doc/current/components/options_resolver.html

### Names or numbers for option keys?

Normally it is advised to use names for the options, instead of digits. However
since the [`ScheduleCommand`][schedule] and `ExecuteCommand`[execute] are not
equipped to input associative arrays we generally recommend against it. If
you're not using these commands, or using a workaround for this, you can use
names for your options.

[schedule]: /src/TreeHouse/WorkerBundle/Command/ScheduleCommand.php
[execute]: /src/TreeHouse/WorkerBundle/Command/ExecuteCommand.php


## Object payloads

We mentioned in the [previous chapter][prev] that you can add jobs for objects.
To do this, the executor needs to implement the [ObjectPayloadInterface][opi],
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
        return [$object->getId()];
    }
}
```

[prev]: /docs/2-queue-manager.md#adding-for-objects
[opi]: /src/TreeHouse/WorkerBundle/Executor/ObjectPayloadInterface.php


## Next

Now that we have everything in place, lets [work some jobs][doc-jobs]!

[doc-jobs]: /docs/4-working-jobs.md
