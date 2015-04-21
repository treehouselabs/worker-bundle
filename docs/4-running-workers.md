# Running jobs

Now that we have everything in place, lets run some jobs!

To manually process a job, first get it from the QueueManager, then process it.
The QueueManager handles the delegation to the executor, the payload
processing, job retrying and more.

```php
# wait max 10 seconds to get a job
$job = $queueManager->get(10);
$result = $queueManager->processJob($job);
```

## The workflow for a worker

A worker typically runs a loop. In that loop it keeps asking for a job, and
then processes it, like the example shown above. There's a number of cases and
events that need to be dealt with here: what if the payload is incorrect, the
process fails somehow, the job needs rescheduling, and how do you implement a
retry when a job fails the first time?

The easiest way to deal with this is to use the `worker:run` command, which
handles the complete workflow for you: reserving jobs, delegating them to the
executors, deleting, burying, rescheduling jobs, etc. You can do this manually
but there are a lot of caveats that you need to consider. We won't cover this
here, look at the run command if you want to implement this yourself.

The run command provides control to include/exclude queues that you want to
process, set a limit on the number of jobs to process, a maximum amount of time
and/or memory to use, and more.

## Rescheduling jobs

When a job depends on some other part of your system, or piece of data, and
that dependency is not yet available when the job is being processed, you can
reschedule the same job at any point in the job's execution by throwing a
[RescheduleException][1] with a new date to try the job again:

```php
# inside the worker:
if (!someCondition()) {
    # try again in 10 seconds
    throw RescheduleException::create('10 seconds');
}

# continue processing
```

[1]: /src/TreeHouse/WorkerBundle/Exception/RescheduleException.php

## Retries



## Events
