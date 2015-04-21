<?php

namespace TreeHouse\WorkerBundle\Command;

use Pheanstalk\Job;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
            ->addOption('action', 'a', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Limit worker to only run specific actions')
            ->addOption('filter', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Prevent worker from running specific actions', ['default'])
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
        $this->registerActions($input->getOption('action'), $input->getOption('filter'));

        $this->output('Waiting for reserved job...');

        $start         = time();
        $minDuration   = 15;
        $jobsCompleted = 0;

        // wait for job, timeout after 1 minute
        while ($job = $this->manager->get(60)) {
            $stats = $this->manager->getJobStats($job);

            $this->log($job, sprintf('Working <info>%s</info> with payload <info>%s</info>', $stats['tube'], $job->getData()));

            try {
                $start    = microtime(true) * 1000;
                $result   = $this->manager->executeJob($job);
                $duration = (microtime(true) * 1000) - $start;

                // job completed without exception, file and delete it
                $this->log(
                    $job,
                    sprintf('Completed job in <comment>%dms</comment> with result: <info>%s</info>', $duration, json_encode($result))
                );
            } catch (\Exception $e) {
                // something went wrong that even the queue manager didn't
                // catch, or maybe it has caused it itself.
                $this->log($job, sprintf('<error>%s</error>', $e->getMessage()));
                $this->manager->bury($job);
            }

            ++$jobsCompleted;

            // intermediate flush
            if ($jobsCompleted % $batchSize === 0) {
                $this->output('Batch complete');
                $dispatcher->dispatch(WorkerEvents::FLUSH);
            }

            if ($jobsCompleted >= $maxJobs) {
                $this->output('Maximum number of jobs completed');

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

        $dispatcher->dispatch(WorkerEvents::FLUSH);

        // make sure worker doesn't quit to quickly, or supervisor will mark it
        // as a failed restart, and put the worker in FATAL state.
        $duration = time() - $start;
        if ($duration < $minDuration) {
            $this->output(sprintf('Sleeping until worker has run for at least %s seconds', $minDuration));
            sleep($minDuration - $duration);
        }

        $dispatcher->dispatch(WorkerEvents::RUN_TERMINATE);

        $this->output('Shutting down worker');
    }

    /**
     * @param array $actions
     * @param array $filters
     */
    protected function registerActions(array $actions = [], array $filters = [])
    {
        foreach ($actions as $action) {
            $this->manager->getPheanstalk()->watch($action);
        }

        foreach ($filters as $action) {
            $this->manager->getPheanstalk()->ignore($action);
        }
    }

    /**
     * @param $msg
     */
    protected function output($msg)
    {
        $this->output->writeln(sprintf('[%s] %s', date('Y-m-d H:i:s'), $msg));
    }

    /**
     * @param Job    $job
     * @param string $msg
     */
    protected function log(Job $job, $msg)
    {
        $this->output(sprintf('[%s] %s', $job->getId(), $msg));
    }
}
