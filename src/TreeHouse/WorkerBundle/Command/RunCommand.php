<?php

namespace TreeHouse\WorkerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\Exception\AbortException;
use TreeHouse\WorkerBundle\QueueManager;
use TreeHouse\WorkerBundle\WorkerEvents;

class RunCommand extends Command
{
    /**
     * @var QueueManager
     */
    protected $manager;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @param QueueManager $queueManager
     */
    public function __construct(QueueManager $queueManager)
    {
        $this->manager = $queueManager;

        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('worker:run')
            ->setDescription('Starts a worker')
            ->addOption('action', 'a', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Selects actions to run, defaults to all')
            ->addOption('exclude', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Excludes actions to run')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of jobs to execute', 20)
            ->addOption('max-memory', 'm', InputOption::VALUE_OPTIONAL, 'Maximum amount of memory to use (in MB). The worker will try to stop before this limit is reached. Set to 0 for infinite.', 0)
            ->addOption('max-time', 't', InputOption::VALUE_OPTIONAL, 'Maximum running time in seconds. Set to 0 for infinite', 0)
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of jobs to process before completing a batch', 15)
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $dispatcher   = $this->manager->getDispatcher();

        $maxMemory = intval($input->getOption('max-memory')) * 1024 * 1024;
        $maxTime   = intval($input->getOption('max-time'));
        $maxJobs   = intval($input->getOption('limit'));
        $batchSize = intval($input->getOption('batch-size'));

        // configure pheanstalk to watch the right tubes
        $this->watchActions($input->getOption('action'), $input->getOption('exclude'));

        $start         = time();
        $minDuration   = 15;
        $jobsCompleted = 0;

        // wait for job, timeout after 1 minute
        $timeout = 60;
        $this->output(sprintf('Waiting at most <info>%d seconds</info> for a reserved job...', $timeout));

        $exit = 0;
        while ($job = $this->manager->get($timeout)) {
            $stats = $this->manager->getJobStats($job);

            $timeStart = microtime(true) * 1000;
            $memStart = memory_get_usage(true);

            try {
                $this->output(
                    sprintf(
                        'Working job <info>%d</info> for action <comment>%s</comment> with payload <info>%s</info>',
                        $job->getId(),
                        $stats['tube'],
                        $job->getData()
                    )
                );

                $result = $this->manager->executeJob($job);
            } catch (AbortException $e) {
                $message = 'Worker aborted ' . ($e->getReason() ? ('with reason: ' . $e->getReason()) : 'without a given reason');
                $this->output($message);

                $exit = 1;

                break;
            }

            $duration = microtime(true) * 1000 - $timeStart;
            $usage    = memory_get_usage(true) - $memStart;
            $message = sprintf(
                'Completed job <info>%d</info> in <comment>%dms</comment> using <comment>%s</comment> with result: <info>%s</info>',
                $job->getId(),
                $duration,
                $this->formatBytes($usage),
                json_encode($result, JSON_UNESCAPED_SLASHES)
            );
            $this->output($message);

            ++$jobsCompleted;

            // intermediate flush
            if ($jobsCompleted % $batchSize === 0) {
                $this->output('Batch complete', OutputInterface::VERBOSITY_VERBOSE);
                $dispatcher->dispatch(WorkerEvents::FLUSH);
            }

            if ($jobsCompleted >= $maxJobs) {
                $this->output(sprintf('Maximum number of jobs completed (%d)', $maxJobs), OutputInterface::VERBOSITY_VERBOSE);

                break;
            }

            if (($maxMemory > 0) && memory_get_usage(true) > $maxMemory) {
                $this->output(
                    sprintf('Memory peak of %dMB reached (peak: %sMB)', $maxMemory / 1024 / 1024, memory_get_usage(true) / 1024 / 1024),
                    OutputInterface::VERBOSITY_VERBOSE
                );

                break;
            }

            if (($maxTime > 0) && ((time() - $start) > $maxTime)) {
                $this->output(
                    sprintf('Maximum execution time of %ds reached', $maxTime),
                    OutputInterface::VERBOSITY_VERBOSE
                );

                break;
            }
        }

        // flush remaining
        $dispatcher->dispatch(WorkerEvents::FLUSH);

        // make sure worker doesn't quit to quickly, or supervisor will mark it
        // as a failed restart, and put the worker in FATAL state.
        $duration = time() - $start;
        if ($duration < $minDuration) {
            $this->output(sprintf('Sleeping until worker has run for at least %s seconds', $minDuration));
            sleep($minDuration - $duration);
        }

        $this->output('Shutting down worker');

        return $exit;
    }

    /**
     * @param string[] $include
     * @param string[] $exclude
     */
    protected function watchActions(array $include = [], array $exclude = [])
    {
        $actions = array_keys($this->manager->getExecutors());

        if (empty($include)) {
            $include = $actions;
        }

        if (!empty($diff = array_diff($include, $actions))) {
            throw new \InvalidArgumentException(sprintf('Action(s) "%s" are not defined by QueueManager', implode(', ', $diff)));
        }

        if (!empty($diff = array_diff($exclude, $actions))) {
            throw new \InvalidArgumentException(sprintf('Filter(s) "%s" are not defined by QueueManager', implode(', ', $diff)));
        }

        $include = array_diff($include, $exclude);

        if (empty($include)) {
            throw new \InvalidArgumentException('No actions specified to run');
        }

        // watch only these actions
        $this->manager->watchOnly($include);
    }

    /**
     * @param string $msg
     * @param int    $threshold
     */
    protected function output($msg, $threshold = OutputInterface::VERBOSITY_NORMAL)
    {
        if ($this->output->getVerbosity() >= $threshold) {
            $this->output->writeln(sprintf('[%s] %s', date('Y-m-d H:i:s'), $msg));
        }
    }

    /**
     * @param int $bytes
     *
     * @return string
     */
    private function formatBytes($bytes)
    {
        $bytes = (int) $bytes;

        if ($bytes > 1024*1024) {
            return round($bytes/1024/1024, 2).'MB';
        } elseif ($bytes > 1024) {
            return round($bytes/1024, 2).'KB';
        }

        return $bytes . 'B';
    }
}
