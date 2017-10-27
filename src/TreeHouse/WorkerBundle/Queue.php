<?php

namespace TreeHouse\WorkerBundle;

use InvalidArgumentException;
use Pheanstalk\Exception;
use Pheanstalk\Job;
use Pheanstalk\PheanstalkInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use TreeHouse\WorkerBundle\Executor\ObjectPayloadInterface;

class Queue
{
    /**
     * @var PheanstalkInterface
     */
    private $pheanstalk;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var int
     */
    private $defaultTtr = PheanstalkInterface::DEFAULT_TTR;
    /**
     * @var ExecutorPool
     */
    private $executorPool;

    /**
     * @param PheanstalkInterface $pheanstalk
     * @param ExecutorPool $executorPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        PheanstalkInterface $pheanstalk,
        ExecutorPool $executorPool,
        LoggerInterface $logger
    ) {
        $this->pheanstalk = $pheanstalk;
        $this->logger = $logger;
        $this->executorPool = $executorPool;
    }

    /**
     * @return PheanstalkInterface
     *
     * @deprecated Removed in next major version
     */
    public function getPheanstalk()
    {
        return $this->pheanstalk;
    }

    /**
     * @return LoggerInterface
     *
     * @deprecated Removed in next major version
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param int $defaultTtr
     *
     * @return $this
     */
    public function setDefaultTtr($defaultTtr)
    {
        $this->defaultTtr = $defaultTtr;

        return $this;
    }

    /**
     * @param string $action
     *
     * @throws Exception
     *
     * @return array
     */
    public function getActionStats($action)
    {
        try {
            return $this->pheanstalk->statsTube($action);
        } catch (Exception $exception) {
            if (false !== strpos($exception->getMessage(), 'NOT_FOUND')) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Add a job to the queue.
     *
     * @param string $action The action
     * @param array $payload The job's payload
     * @param string|int $delay The delay after which the job can be reserved.
     *                             Can be a number of seconds, or a date-diff
     *                             string relative from now, like "10 seconds".
     * @param int $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
     * @param int $ttr Time To Run: seconds a job can be reserved for
     *
     * @throws InvalidArgumentException When the action is not defined
     * @throws InvalidArgumentException When `$delay` or `$priority` is negative
     *
     * @return int The job id
     */
    public function add($action, array $payload, $delay = null, $priority = null, $ttr = null)
    {
        if (false === $this->executorPool->hasExecutor($action)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Action "%s" is not defined in QueueManager',
                    $action
                )
            );
        }

        if (null === $delay) {
            $delay = PheanstalkInterface::DEFAULT_DELAY;
        }

        if (null === $priority) {
            $priority = PheanstalkInterface::DEFAULT_PRIORITY;
        }

        if (null === $ttr) {
            $ttr = $this->defaultTtr;
        }

        if (!is_numeric($delay)) {
            $delay = strtotime(sprintf('+ %s', $delay)) - time();
        }

        if ($delay < 0) {
            throw new InvalidArgumentException(
                sprintf('You cannot schedule a job in the past (delay was %d)', $delay)
            );
        }

        if ($priority < 0) {
            throw new InvalidArgumentException(
                sprintf('The priority for a job cannot be negative (was %d)', $priority)
            );
        }

        $payload = json_encode($payload);
        $jobId = $this->pheanstalk->putInTube($action, $payload, $priority, $delay, $ttr);

        $this->logJob(
            $jobId,
            sprintf(
                'Added job in tube "%s" with: payload: %s, priority: %d, delay: %ds, ttr: %s',
                $action,
                $payload,
                $priority,
                $delay,
                $ttr
            )
        );

        return $jobId;
    }

    /**
     * Adds a job to the queue for an object.
     *
     * @param string $action The action
     * @param object $object The object to add a job for
     * @param string|int $delay The delay after which the job can be reserved.
     *                             Can be a number of seconds, or a date-diff
     *                             string relative from now, like "10 seconds".
     * @param int $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
     * @param int $ttr Time To Run: seconds a job can be reserved for
     *
     * @throws \LogicException           If the executor does not accepts objects as payloads
     * @throws InvalidArgumentException If the executor does not accept the given object
     * @throws InvalidArgumentException When the action is not defined
     *
     * @return int The job id
     */
    public function addForObject($action, $object, $delay = null, $priority = null, $ttr = null)
    {
        $executor = $this->executorPool->getExecutor($action);

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
            throw new InvalidArgumentException(
                sprintf(
                    'The executor for action "%s" does not support %s objects',
                    $action,
                    get_class($object)
                )
            );
        }

        $payload = $executor->getObjectPayload($object);

        return $this->add($action, $payload, $delay, $priority, $ttr);
    }

    /**
     * Reschedules a job.
     *
     * @param Job $job
     * @param \DateTime $date
     * @param integer $priority
     *
     * @throws InvalidArgumentException When `$date` is in the past
     */
    public function reschedule(Job $job, \DateTime $date, $priority = PheanstalkInterface::DEFAULT_PRIORITY)
    {
        if ($date < new \DateTime()) {
            throw new InvalidArgumentException(
                sprintf(
                    'You cannot reschedule a job in the past (got %s, and the current date is %s)',
                    $date->format(DATE_ISO8601),
                    date(DATE_ISO8601)
                )
            );
        }

        $this->pheanstalk->release($job, $priority, $date->getTimestamp() - time());

        $this->logJob($job->getId(), sprintf('Rescheduled job for %s', $date->format('Y-m-d H:i:s')));
    }

    /**
     * @param string|string[] $actions
     */
    public function watch($actions)
    {
        if (!is_array($actions)) {
            $actions = [$actions];
        }

        foreach ($actions as $action) {
            $this->pheanstalk->watch($action);

            $this->logger->debug(sprintf('Watching tube "%s"', $action));
        }
    }

    /**
     * @param string|string[] $actions
     */
    public function watchOnly($actions)
    {
        $watching = $this->pheanstalk->listTubesWatched();

        $this->watch($actions);
        $this->ignore($watching);
    }

    /**
     * @param string|string[] $actions
     */
    public function ignore($actions)
    {
        if (!is_array($actions)) {
            $actions = [$actions];
        }

        foreach ($actions as $action) {
            $this->pheanstalk->ignore($action);

            $this->logger->debug(sprintf('Ignoring tube "%s"', $action));
        }
    }

    /**
     * @param int $timeout
     *
     * @return Job|bool A job if there is one, false otherwise
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
     * @param string $state The state to peek for, can be 'ready', 'delayed' or 'buried'
     *
     * @throws InvalidArgumentException When $action is not a defined action
     * @throws InvalidArgumentException When $state is not a valid state
     * @throws Exception                 When Pheanstalk decides to do this
     *
     * @return Job The next job for the given state, or null if there is no next job
     */
    public function peek($action, $state = 'ready')
    {
        if (false === $this->executorPool->hasExecutor($action)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Action "%s" is not defined in QueueManager',
                    $action
                )
            );
        }

        $states = ['ready', 'delayed', 'buried'];

        if (!in_array($state, $states)) {
            throw new InvalidArgumentException(
                sprintf('$state must be one of %s, got %s', json_encode($states), json_encode($state))
            );
        }

        $peekMethod = sprintf('peek%s', ucfirst($state));

        try {
            return $this->pheanstalk->$peekMethod($action);
        } catch (Exception $exception) {
            if (false !== strpos($exception->getMessage(), 'NOT_FOUND')) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * Permanently deletes a job.
     *
     * @param Job $job
     */
    public function delete(Job $job)
    {
        $this->pheanstalk->delete($job);

        $this->logJob($job->getId(), 'Job deleted');
    }

    /**
     * Puts a job into a 'buried' state, revived only by 'kick' command.
     *
     * @param Job $job
     */
    public function bury(Job $job)
    {
        $this->pheanstalk->bury($job);

        $this->logJob($job->getId(), 'Job buried');
    }

    /**
     * Puts a job into a 'buried' state, revived only by 'kick' command.
     *
     * @param string $action
     * @param int $max
     *
     * @return int The number of kicked jobs
     */
    public function kick($action, $max)
    {
        $this->pheanstalk->useTube($action);

        $kicked = $this->pheanstalk->kick($max);

        $this->logger->debug(
            sprintf('Kicked %d "%s" jobs back onto the ready queue', $kicked, $action)
        );

        return $kicked;
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
     * CAUTION: this removes all items from an action's queue.
     * This is an irreversible action!
     *
     * @param string $action
     * @param array $states
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
     * @throws Exception
     */
    protected function clearTube($tube, $state = 'ready')
    {
        $this->logger->info(sprintf('Clearing all jobs with the "%s" state in tube "%s"', $state, $tube));

        while ($job = $this->peek($tube, $state)) {
            try {
                $this->delete($job);
            } catch (Exception $e) {
                // job could have been deleted by another process
                if (false === strpos($e->getMessage(), 'NOT_FOUND')) {
                    throw $e;
                }
            }
        }
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
