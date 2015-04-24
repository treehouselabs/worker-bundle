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
            ->addArgument('payload', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The payload to send to the action')
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

        $output->writeln(sprintf('Performing action <info>%s</info> with payload <info>%s</info>', $action, json_encode($payload, JSON_UNESCAPED_SLASHES)));

        $timeStart = microtime(true) * 1000;
        $memStart  = memory_get_usage(true);

        $result = $this->manager->execute($action, $payload);

        $duration = microtime(true) * 1000 - $timeStart;
        $usage    = memory_get_usage(true) - $memStart;

        $output->writeln(
            sprintf(
                'Completed in <comment>%dms</comment> using <comment>%s</comment> with result: <info>%s</info>',
                $duration,
                $this->formatBytes($usage),
                json_encode($result, JSON_UNESCAPED_SLASHES)
            )
        );

        $this->manager->getDispatcher()->dispatch(WorkerEvents::FLUSH);
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
