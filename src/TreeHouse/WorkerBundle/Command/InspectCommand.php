<?php

namespace TreeHouse\WorkerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\QueueManager;

class InspectCommand extends Command
{
    /**
     * @var QueueManager
     */
    protected $manager;

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
            ->setName('worker:inspect')
            ->addArgument('action', InputArgument::REQUIRED, 'Selects which action to inspect')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'The number of jobs to inspect', 20)
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show all jobs for this action, negates <comment>--limit</comment>')
            ->setDescription('Inspects jobs for an action without actually processing them')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        $limit  = $input->getOption('all') ? null : $input->getOption('limit');

        $this->inspect($output, $action, $limit);

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param string          $action
     * @param int             $limit
     */
    protected function inspect(OutputInterface $output, $action, $limit = null)
    {
        $output->writeln(sprintf('Inspecting jobs for the <info>%s</info> action', $action));

        $this->manager->watchOnly([$action]);

        $jobs = [];
        while ($job = $this->manager->get(1)) {
            $output->writeln(sprintf('<info>%d</info>: <comment>%s</comment>', $job->getId(), $job->getData()));

            $jobs[] = $job;

            if (null !== $limit && sizeof($jobs) >= $limit) {
                break;
            }
        }

        $output->writeln('Releasing the jobs back to the queue, <error>don\'t cancel this action!</error>');

        foreach ($jobs as $job) {
            $stats = $this->manager->getJobStats($job);
            $this->manager->getPheanstalk()->release($job, $stats['pri']);
        }
    }
}
