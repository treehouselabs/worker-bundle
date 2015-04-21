<?php

namespace TreeHouse\WorkerBundle;

use Pheanstalk\Exception;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TreeHouse\WorkerBundle\Event\ExecutionEvent;
use TreeHouse\WorkerBundle\Event\JobEvent;
use TreeHouse\WorkerBundle\Exception\RescheduleException;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;
use TreeHouse\WorkerBundle\Executor\ObjectPayloadInterface;

/**
 * The QueueManager is a service which handles persistent scheduled actions.
 * It defines certain actions which can be added and processed.
 *
 * This is useful when you have a lot of actions which you want to perform
 * later on, e.g. when importing large amounts of items.
 *
 * NOTE: While the manager tries to prevent duplicate actions, there is no
 * guarantee of this. As a result of this, you should make all jobs idempotent,
 * meaning they can be processed more than once.
 */
class QueueManager
{
    /**
     * @var PheanstalkInterface
     */
    protected $pheanstalk;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Registered executors
     *
     * @var array<string, ExecutorInterface>
     */
    protected $executors = [];

    /**
     * @param PheanstalkInterface $pheanstalk
     * @param EventDispatcherInterface        $dispatcher
     * @param LoggerInterface                 $logger
     */
    public function __construct(PheanstalkInterface $pheanstalk, EventDispatcherInterface $dispatcher, LoggerInterface $logger = null)
    {
        $this->pheanstalk = $pheanstalk;
        $this->dispatcher = $dispatcher;
        $this->logger     = $logger ?: new NullLogger();
    }

    /**
     * @return PheanstalkInterface
     */
    public function getPheanstalk()
    {
        return $this->pheanstalk;
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * @param string $action
     *
     * @return boolean
     */
    public function hasExecutor($action)
    {
        return array_key_exists($action, $this->executors);
    }

    /**
     * Add an executor
     *
     * @param ExecutorInterface $executor
     *
     * @throws \InvalidArgumentException
     */
    public function addExecutor(ExecutorInterface $executor)
    {
        $action = $executor->getName();

        if ($this->hasExecutor($action)) {
            throw new \InvalidArgumentException(sprintf(
                'There is already an executor registered for action "%s".',
                $action
            ));
        }

        $this->executors[$action] = $executor;
    }

    /**
     * Returns a registered executor for given action
     *
     * @param string $action
     *
     * @throws \InvalidArgumentException
     *
     * @return ExecutorInterface
     */
    public function getExecutor($action)
    {
        if (!$this->hasExecutor($action)) {
            throw new \InvalidArgumentException(sprintf(
                'There is no executor registered for action "%s".',
                $action
            ));
        }

        return $this->executors[$action];
    }

    /**
     * @return array<string, ExecutorInterface>
     */
    public function getExecutors()
    {
        return $this->executors;
    }

    /**
     * Reschedules a job
     *
     * @param Job $job
     * @param \DateTime       $date
     *
     * @throws \InvalidArgumentException When `$date` is in the past
     */
    public function reschedule(Job $job, \DateTime $date)
    {
        if ($date < new \DateTime()) {
            throw new \InvalidArgumentException(
                sprintf('You cannot reschedule a job in the past (got %s, and the current date is %s)', $date->format(DATE_ISO8601), date(DATE_ISO8601))
            );
        }

        $this->pheanstalk->release($job, PheanstalkInterface::DEFAULT_PRIORITY, $date->getTimestamp() - time());
    }

    /**
     * Adds a job to the queue for an object
     *
     * @param string    $action   The action
     * @param object    $object   The object to add a job for
     * @param \DateTime $date     The time after which the job can be reserved. Defaults to the current time
     * @param integer   $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
     * @param integer   $ttr      Time To Run: seconds a job can be reserved for
     *
     * @throws \LogicException           If the executor does not accepts objects as payloads
     * @throws \InvalidArgumentException If the executor does not accept the given object
     * @throws \InvalidArgumentException When the action is not defined
     *
     * @return integer The job id
     */
    public function addForObject($action, $object, \DateTime $date = null, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $ttr = 1200)
    {
        $executor = $this->getExecutor($action);

        if (!$executor instanceof ObjectPayloadInterface) {
            throw new \LogicException(
                sprintf(
                    'The executor for action "%s" cannot be used for objects. Implement the ObjectPayloadInterface in class "%s" to enable this.',
                    $action,
                    get_class($executor)
                )
            );
        }

        if (!$executor->supportsObject($object)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The executor for action "%s" does not support %s objects',
                    $action,
                    get_class($object)
                )
            );
        }

        $payload = $executor->getObjectPayload($object);

        return $this->add($action, $payload, $date, $priority, $ttr);
    }

    /**
     * Add a job to the queue
     *
     * @param string    $action   The action
     * @param array     $payload  The job's payload
     * @param \DateTime $date     The time after which the job can be reserved. Defaults to the current time
     * @param integer   $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
     * @param integer   $ttr      Time To Run: seconds a job can be reserved for
     *
     * @throws \InvalidArgumentException When the action is not defined
     * @throws \InvalidArgumentException When `$date` is in the past
     *
     * @return integer The job id
     */
    public function add($action, array $payload, \DateTime $date = null, $priority = PheanstalkInterface::DEFAULT_PRIORITY, $ttr = 1200)
    {
        if (false === $this->hasExecutor($action)) {
            throw new \InvalidArgumentException(sprintf(
                'Action "%s" is not defined in QueueManager',
                $action
            ));
        }

        if (null === $date) {
            $date = new \DateTime();
        }

        $delay = $date->getTimestamp() - time();
        if ($delay < 0) {
            throw new \InvalidArgumentException(
                sprintf('You cannot schedule a job in the past (got %s, and the current date is %s)', $date->format(DATE_ISO8601), date(DATE_ISO8601))
            );
        }

        if ($priority < 0) {
            $priority = 0;
        }

        return $this->pheanstalk->putInTube($action, json_encode($payload), $priority, $delay, $ttr);
    }

    /**
     * @param integer $timeout
     *
     * @return Job
     */
    public function get($timeout = null)
    {
        return $this->pheanstalk->reserve($timeout);
    }

    /**
     * Inspects the next job from the queue. Note that this does not reserve
     * the job, so it will still be given to a worker if/once it's ready.
     *
     * @param string $action The action to peek
     * @param string $state  The state to peek for, can be 'ready', 'delayed' or 'buried'
     *
     * @throws \InvalidArgumentException When $action is not a defined action
     * @throws \InvalidArgumentException When $state is not a valid state
     *
     * @return Job The next job for the given state.
     */
    public function peek($action, $state = 'ready')
    {
        if (false === $this->hasExecutor($action)) {
            throw new \InvalidArgumentException(sprintf(
                'Action "%s" is not defined in QueueManager',
                $action
            ));
        }

        $states = ['ready', 'delayed', 'buried'];
        if (!in_array($state, $states)) {
            throw new \InvalidArgumentException(
                sprintf('$state must be one of %s, got %s', json_encode($states), json_encode($state))
            );
        }

        $peekMethod = sprintf('peek%s', ucfirst($state));

        return $this->pheanstalk->$peekMethod($action);
    }

    /**
     * Permanently deletes a job.
     *
     * @param Job $job
     */
    public function delete(Job $job)
    {
        $this->pheanstalk->delete($job);
    }

    /**
     * Puts a job into a 'buried' state, revived only by 'kick' command.
     *
     * @param Job $job
     */
    public function bury(Job $job)
    {
        $this->pheanstalk->bury($job);
    }

    /**
     * @param Job $job
     *
     * @return array
     */
    public function getJobStats(Job $job)
    {
        return $this->pheanstalk->statsJob($job);
    }

    /**
     * Process a job
     *
     * @param Job $job        The job to process
     * @param integer         $maxRetries The number of retries for this job
     *
     * @return boolean|mixed The executor result if successful, false otherwise
     */
    public function executeJob(Job $job, $maxRetries = 1)
    {
        $this->dispatcher->dispatch(WorkerEvents::EXECUTE_JOB, new JobEvent($job));

        $stats   = $this->pheanstalk->statsJob($job);
        $payload = (array) json_decode($job->getData(), true);

        // context for logging
        $context = [
            'tube'    => $stats['tube'],
            'payload' => $payload,
        ];

        try {
            // execute command
            $result = $this->execute($stats['tube'], $payload);

            // delete job if it completed without exceptions
            $this->delete($job);

            return $result;
        } catch (RescheduleException $re) {
            // issue a reschedule
            $this->reschedule($job, $re->getRescheduleDate());

            $this->logger->notice(
                sprintf(
                    'Rescheduled job for %s: %s',
                    $re->getRescheduleDate()->format('c'),
                    $re->getRescheduleMessage()
                ),
                $context
            );
        } catch (\Exception $e) {
            // some other exception occured, see if we have any retries left
            $releases = intval($stats['releases']);
            if ($releases > $maxRetries) {
                // no more retries, bury job for manual inspection
                $this->bury($job);

                $this->logger->error(
                    sprintf(
                        'Exception occurred: %s in %s on line %d',
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ),
                    $context
                );
            } else {
                // try again, regardless of the error
                $this->reschedule($job, new \DateTime('+10 minutes'));

                $this->logger->warning(
                    sprintf(
                        'Exception occurred: %s in %s on line %d, rescheduling job...',
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ),
                    $context
                );
                $this->logger->debug($e->getTraceAsString(), $context);
            }
        }

        return false;
    }

    /**
     * Executes an action with a specific payload.
     *
     * @param string $action
     * @param array  $payload
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function execute($action, array $payload)
    {
        if (!$this->hasExecutor($action)) {
            throw new \RuntimeException(sprintf('Action "%s" is not defined in QueueManager', $action));
        }

        $executor = $this->getExecutor($action);

        // dispatch pre event, listeners may change the payload here
        $event = new ExecutionEvent($executor, $action, $payload);
        $this->dispatcher->dispatch(WorkerEvents::PRE_EXECUTE_ACTION, $event);

        $result = $executor->execute($event->getPayload());

        // dispatch post event, listeners may change the result here
        $event->setResult($result);
        $this->dispatcher->dispatch(WorkerEvents::POST_EXECUTE_ACTION, $event);

        return $event->getResult();
    }

    /**
     * CAUTION: this removes all items from an action's queue.
     * This is an irreversible action!
     *
     * @param string $action
     * @param array  $states
     */
    public function clear($action, array $states = [])
    {
        if (empty($states)) {
            $states = ['ready', 'delayed', 'buried'];
        }

        foreach ($states as $state) {
            $this->clearTube($action, $state);
        }
    }

    /**
     * @param string $tube
     * @param string $state
     *
     * @throws \Exception
     */
    protected function clearTube($tube, $state = 'ready')
    {
        while ($job = $this->peek($tube, $state)) {
            $this->deleteJob($job);
        }
    }

    /**
     * @param Job $job
     *
     * @throws Exception
     */
    protected function deleteJob(Job $job)
    {
        try {
            $this->pheanstalk->delete($job);
        } catch (Exception $e) {
            if (false === strpos($e->getMessage(), '/NOT_FOUND/')) {
                throw $e;
            }
        }
    }
}
