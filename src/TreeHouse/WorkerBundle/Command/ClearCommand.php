<?php

namespace TreeHouse\WorkerBundle\Command;

use Leezy\PheanstalkBundle\Event\CommandEvent;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\QueueManager;

class ClearCommand extends Command
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
            ->setName('worker:clear')
            ->addArgument('action', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Selects which actions to clear. Defaults to all actions')
            ->addOption('state', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The state of jobs to peek for. Valid options are <comment>ready</comment>, <comment>delayed</comment> and <comment>buried</comment>.', ['ready'])
            ->setDescription('Clears jobs for action(s)')
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

        /** @var PheanstalkProxy $pheanstalk */
        $pheanstalk = $this->manager->getPheanstalk();
        $dispatcher = $pheanstalk->getDispatcher();

        foreach ($actions as $action) {
            $stats = $pheanstalk->statsTube($action);
            $progressbar = new ProgressBar($output, $stats['']);

            $callback = function () use ($progressbar) {
                $progressbar->advance();
            };

            $dispatcher->addListener(CommandEvent::DELETE, $callback);

            $output->writeln(
                sprintf('Clearing <info>%s</info> jobs with <info>%s</info> status', $action, json_encode($states))
            );

            $this->manager->clear($action, $states);

            $dispatcher->removeListener(CommandEvent::DELETE, $callback);

            $progressbar->finish();
        }
    }
}
