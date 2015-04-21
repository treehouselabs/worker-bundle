<?php

namespace TreeHouse\WorkerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\QueueManager;
use TreeHouse\WorkerBundle\WorkerEvents;

class ExecuteCommand extends Command
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
            ->setName('worker:execute')
            ->addArgument('action', InputArgument::REQUIRED, 'The action to execute')
            ->addArgument('payload', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The payload to send to the action')
            ->setDescription('Executes a single job with given payload. This doesn\'t use the regular queue')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action  = $input->getArgument('action');
        $payload = $input->getArgument('payload');

        $output->writeln(sprintf('Performing action <info>%s</info> with payload <info>%s</info>', $action, json_encode($payload)));
        $start = round(microtime(true) * 1000);

        $result = $this->manager->execute($action, $payload);

        $stop = round(microtime(true) * 1000);

        $timing = $stop - $start;

        $this->manager->getDispatcher()->dispatch(WorkerEvents::FLUSH);

        $output->writeln(sprintf('Completed in <comment>%dms</comment> with result: <comment>%s</comment>', $timing, json_encode($result)));
    }
}
