<?php

namespace TreeHouse\WorkerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\QueueManager;

class PeekCommand extends Command
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
            ->setName('worker:peek')
            ->addArgument('action', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Selects which actions to peek for. Defaults to all actions')
            ->addOption('state', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The state of job to peek for. Valid options are <comment>ready</comment>, <comment>delayed</comment> and <comment>buried</comment>.', ['ready'])
            ->setDescription('Peeks the next job from the queue')
            ->setHelp('Inspects the next job from the queue for one or more actions. Note that this does not reserve the job, so it will still be given to a worker if/once it\'s ready.')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $actions = $input->getArgument('action');
        if (empty($actions)) {
            $actions = array_keys($this->manager->getExecutors());
        }

        $states = $input->getOption('state');

        foreach ($actions as $action) {
            $output->writeln(sprintf('Action: <info>%s</info>', $action));

            $table = new Table($output);
            $table->setHeaders(['state', 'id', 'data']);
            foreach ($states as $state) {
                $job = $this->manager->peek($action, $state);

                if (is_null($job)) {
                    $table->addRow([$state, '-', '-']);
                } else {
                    $table->addRow([$state, $job->getId(), $job->getData()]);
                }
            }

            $table->render();
            $output->writeln('');
        }
    }
}
