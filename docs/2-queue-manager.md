# The QueueManager

The WorkerBundle creates a [QueueManager][qm] service which acts as a central
point to add jobs in the queue, receive them back from it, inspect, bury and
delete jobs.

[qm]: /src/TreeHouse/WorkerBundle/QueueManager.php


## Examples

Below are some basic usage examples. These examples use a _tube_ (the name of a
queue in Beanstalk parlance) named `example`.

Add a job:

```php
$queueManager->add('example', $payload = ['data']);
```

Receive a job:

```php
# watch the 'example' tube
$queueManager->watch('example');

# waits at most 10 seconds, returns a \Pheanstalk\Job instance
$queueManager->get(10);
```

Inspect a job. This returns the next-ready job from the queue, without actually
reserving the job from the queue:

```php
# returns a \Pheanstalk\Job instance
$queueManager->peek('example');
```

Bury a job. This 'parks' the job outside the queue, which means the job will
not be released until it's _kicked_ back onto the queue:

```php
$queueManager->bury($job);
```

Kick jobs back onto the queue:

```php
$queueManager->kick('example', $max = 10);
```

Delete a job:

```php
$queueManager->delete($job);
```

Clear a tube:

```php
$queueManager->clear('example');
```


## Adding for objects

Often the jobs for a tube correspond directly to an object; a model in the
application's domain. For example: a `user.mail.registration` tube has a direct
relation to a `User` object. In these cases it can be useful to pass objects
to the QueueManager instead of constructing the payload manually every time.
We'll explain how these payloads are constructed in the next chapter, for now
we'll show how you this example works:

```php
$queueManager->addForObject('user.mail.registration', $user);
```


## Time to run

When adding a job you can indicate how long a worker may take to process this
job, before the job is considered released. This is called the _time to run_.
Since the queue is asynchronous it does not get an immediate response from the
worker after reserving a job for it. After the time to run has expired, and the
job has not been deleted, the queue considers the job as gone wrong and
releases it, after which the job becomes available to reserve again.

By default the time to run is set to 2 minutes. If you have a job that takes a
long time to complete, make sure to increase the time to run, or to
[touch][proto] the job during the process.

[proto]: https://github.com/kr/beanstalkd/blob/master/doc/protocol.txt#L308-L313


## Delaying & prioritizing jobs

The `add()` and `addForObject()` methods accept more arguments to give you more
control over when the job will be reserved to a worker.

You can set a specific time after which the job can be reserved:

```php
$queueManager->addForObject('user.mail.registration', $user, '10 minutes');
```

This delays the job for 10 minutes after now.

The second argument to influence this is the job priority. The priority is a
number between 0 (most urgent) and 4294967295 (least urgent). By default, all
jobs get a default priority of 1024, but you can change this in the `add*`
method calls:

```php
# urgent
$queueManager->addForObject('user.mail.registration', $user, null, 10);

# low priority
$queueManager->addForObject('user.mail.registration', $user, null, 10000);
```

Combining these arguments, you can influence even more control here:

```php
# urgent, but only after 1 minute
$queueManager->addForObject('user.mail.registration', $user, '1 minute', 10);
```

Note how we're saying _influence_ here. Depending on the number of jobs in the
queue, and the number of workers that are available, a job may come available
after the designated time, or not immediately if you're using the highest
priority. If there are 1000 jobs scheduled for right now, and there are 10
available workers, it may take some time for the queue to be completely
processed.


## Next

Now that we know how to manage jobs, let's see how we can actually _work_ them:
[Executors][doc-executors].

[doc-executors]: /docs/3-executors.md
