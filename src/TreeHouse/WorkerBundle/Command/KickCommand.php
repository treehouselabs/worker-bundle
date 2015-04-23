<?php

namespace TreeHouse\WorkerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\QueueManager;

class KickCommand extends Command
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
            ->setName('worker:kick')
            ->addArgument('action', InputArgument::REQUIRED, 'The action to execute')
            ->addArgument('number', InputArgument::OPTIONAL, 'Number of jobs to kick', 1)
            ->setDescription('Kicks buried jobs for an action back onto the queue')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        $number = $input->getArgument('number');

        $kicked = $this->manager->kick($action, $number);

        $output->writeln(sprintf('Kicked <info>%d</info> buried <info>%s</info> job(s)', $kicked, $action));
    }
}
