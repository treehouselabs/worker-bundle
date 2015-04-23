<?php

namespace TreeHouse\WorkerBundle;

final class WorkerEvents
{
    /**
     * Dispatched when the worker needs to flush. This typically occurs after a
     * batch completes during the run command, or when a single job is finished.
     */
    const FLUSH = 'worker.flush';

    /**
     * Dispatched when executing a job.
     * Listeners receive a JobEvent instance.
     */
    const EXECUTE_JOB = 'worker.job.execute';

    /**
     * Dispatched before executing an action.
     * Listeners receive a ExecutionEvent instance.
     */
    const PRE_EXECUTE_ACTION = 'worker.action.execute.pre';

    /**
     * Dispatched after executing an action.
     * Listeners receive a ExecutionEvent instance.
     */
    const POST_EXECUTE_ACTION = 'worker.action.execute.post';
}
