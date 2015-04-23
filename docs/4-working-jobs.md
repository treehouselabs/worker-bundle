# Working jobs

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


## Return values

An executor can return whatever value it sees fit. It does not matter to the
worker that's running it, meaning returning `false` will not stop the worker,
or retry the job. To put it simple: any outcome but an exception is an accepted
return value, even no return value at all.


## Rescheduling jobs

When a job depends on some other part of your system, or piece of data, and
that dependency is not yet available when the job is being processed, you can
reschedule the same job at any point in the job's execution by throwing a
[RescheduleException][re] with a new date to try the job again:

```php
# inside the worker:
if (!someCondition()) {
    # try again in 10 seconds
    throw RescheduleException::create('10 seconds');
}

# continue processing
```

[re]: /src/TreeHouse/WorkerBundle/Exception/RescheduleException.php


## Retries and handling exceptions

When an exception is thrown in the job execution process, it is caught and
logged by the QueueManager. The job is then automatically rescheduled to run
again, 10 minutes later. If the job fails a second time, it is put in the
_buried_ state, meaning it will not be available to a worker until it is
_kicked_ back onto the queue.

The premise of this process is that all jobs need to complete without any
unexpected outcomes; during the execution of a job things can happen that the
executor does not handle. These can be runtime errors (an unavailable
service for example), or just use cases that are not handled properly
(eg: working on an object that has been removed since the job was created).

In any case, once a situation like this occurs, it needs to be dealt with. This
means the developer should monitor the buried jobs for an executor. And if
there are any: inspect them manually to see what goes wrong, fix this in the
executor, and process the buried jobs again. This way you are gradually
improving the robustness of your executors.


## Aborting a worker

Sometimes an error occurs during a job execution that is severe enough that the
worker needs to be stopped immediately. For example: when a Doctrine
EntityManager is closed due to a persistence error, or an external service that
becomes unavailable.

When this happens, you can throw an [AbortException][ae], which shuts down the
worker.

[ae]: /src/TreeHouse/WorkerBundle/Exception/AbortException.php


## Events

All available events are defined in the [WorkerEvents][we] class. We'll
describe some of the events here:

[we]: /src/TreeHouse/WorkerBundle/WorkerEvents.php

### `worker.flush`

For longer running workers, or workers that process a lot of jobs very fast, it
can be useful to perform some actions in batches, like flushing an
EntityManager. This event is dispatched from the `worker:run` command.

### `worker.action.execute.pre`

Dispatched before executing an action. Listeners receive an
[`ExecutionEvent`][ee], and may use that to change the payload before it is
given to the executor.

[ee]: /src/TreeHouse/WorkerBundle/Event/ExecutionEvent.php

### `worker.action.execute.post`

Dispatched after executing an action. Listeners receive an
[`ExecutionEvent`][ee] in which the execution result has been set, and may use
that to change the result.
