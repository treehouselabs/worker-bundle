<?php

namespace TreeHouse\WorkerBundle;

use Exception;
use Pheanstalk\Job;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TreeHouse\WorkerBundle\Event\ExecutionEvent;
use TreeHouse\WorkerBundle\Event\JobBuriedEvent;
use TreeHouse\WorkerBundle\Event\JobEvent;
use TreeHouse\WorkerBundle\Exception\AbortException;
use TreeHouse\WorkerBundle\Exception\RescheduleException;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;

class QueueExecutor
{
    /**
     * @var Queue
     */
    private $queue;
    /**
     * @var ExecutorPool
     */
    private $executorPool;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var OptionsResolver[]
     */
    private $resolvers = [];

    public function __construct(
        Queue $queue,
        ExecutorPool $executorPool,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger
    ) {
        $this->queue = $queue;
        $this->executorPool = $executorPool;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    /**
     * @return EventDispatcherInterface
     *
     * @deprecated Removed in next major version
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param Job $job The job to process
     * @param int $maxRetries The number of retries for this job
     *
     * @throws AbortException
     *
     * @return bool|mixed The executor result if successful, false otherwise
     */
    public function executeJob(Job $job, $maxRetries = 1)
    {
        $this->dispatcher->dispatch(WorkerEvents::EXECUTE_JOB, new JobEvent($job));

        $stats = $this->queue->getJobStats($job);
        $payload = (array) json_decode($job->getData(), true);
        $releases = intval($stats['releases']);
        $priority = intval($stats['pri']);

        // context for logging
        $context = [
            'tube'    => $stats['tube'],
            'payload' => $payload,
            'attempt' => $releases + 1,
        ];

        try {
            // execute command
            $result = $this->execute($stats['tube'], $payload);

            // delete job if it completed without exceptions
            $this->queue->delete($job);

            return $result;
        } catch (RescheduleException $re) {
            // Override priority if the RescheduleException provides a new one.
            if (!is_null($re->getReshedulePriority())) {
                $priority = $re->getReshedulePriority();
            }
            // reschedule the job
            $this->queue->reschedule($job, $re->getRescheduleDate(), $priority);
        } catch (AbortException $e) {
            // abort thrown from executor, rethrow it and let the caller handle it
            throw $e;
        } catch (Exception $e) {
            // some other exception occured
            $message = sprintf(
                'Exception occurred: %s in %s on line %d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            $this->logJob($job->getId(), $message, LogLevel::ERROR, $context);
            $this->logJob($job->getId(), $e->getTraceAsString(), LogLevel::DEBUG, $context);

            // see if we have any retries left
            if ($releases > $maxRetries) {
                // no more retries, bury job for manual inspection
                $this->queue->bury($job);

                $this->dispatcher->dispatch(WorkerEvents::JOB_BURIED_EVENT, new JobBuriedEvent($job, $e, $releases));
            } else {
                // try again, regardless of the error
                $this->queue->reschedule($job, new \DateTime('+10 minutes'), $priority);
            }
        }

        return false;
    }

    /**
     * Executes an action with a specific payload.
     *
     * @param string $action
     * @param array $payload
     *
     * @return mixed
     */
    public function execute($action, array $payload)
    {
        $executor = $this->executorPool->getExecutor($action);

        // dispatch pre event, listeners may change the payload here
        $event = new ExecutionEvent($executor, $action, $payload);
        $this->dispatcher->dispatch(WorkerEvents::PRE_EXECUTE_ACTION, $event);

        try {
            $resolver = $this->getPayloadResolver($executor);
            $payload = $resolver->resolve($event->getPayload());
        } catch (ExceptionInterface $exception) {
            $this->logger->error(
                sprintf(
                    'Payload %s for "%s" is invalid: %s',
                    json_encode($payload, JSON_UNESCAPED_SLASHES),
                    $action,
                    $exception->getMessage()
                )
            );

            return false;
        }

        $result = $executor->execute($payload);

        $event = clone $event;

        // dispatch post event, listeners may change the result here
        $event->setResult($result);
        $this->dispatcher->dispatch(WorkerEvents::POST_EXECUTE_ACTION, $event);

        return $event->getResult();
    }

    /**
     * Returns a cached version of the payload resolver for an executor.
     *
     * @param ExecutorInterface $executor
     *
     * @return OptionsResolver
     */
    protected function getPayloadResolver(ExecutorInterface $executor)
    {
        $key = $executor->getName();

        if (!array_key_exists($key, $this->resolvers)) {
            $resolver = new OptionsResolver();
            $executor->configurePayload($resolver);

            $this->resolvers[$key] = $resolver;
        }

        return $this->resolvers[$key];
    }

    /**
     * @param int $jobId
     * @param string $msg
     * @param string $level
     * @param array $context
     */
    private function logJob($jobId, $msg, $level = LogLevel::DEBUG, array $context = [])
    {
        $this->logger->log($level, sprintf('[%s] %s', $jobId, $msg), $context);
    }
}
