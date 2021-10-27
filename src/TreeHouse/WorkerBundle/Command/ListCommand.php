<?php

namespace TreeHouse\WorkerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use TreeHouse\WorkerBundle\QueueManager;

class ListCommand extends Command
{
    /**
     * @var QueueManager
     */
    protected $manager;

    /**
     * @var int
     */
    protected $lineCount;

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
            ->setName('worker:list')
            ->addArgument('action', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Selects which actions to clear. Defaults to all actions')
            ->addOption('refresh', null, InputOption::VALUE_OPTIONAL, 'Refresh rate in seconds, only applies for interactive commands', 1)
            ->addOption('empty', null, InputOption::VALUE_NONE, 'Show empty actions')
            ->setDescription('List actions and their stats')
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

        $empty   = $input->getOption('empty');
        $refresh = $input->getOption('refresh');

        if ($input->isInteractive()) {
            $output->writeln([
                '',
                sprintf('This list refreshes every <comment>%d</comment> second(s), type CTRL-C to exit', $refresh),
                '',
            ]);

            while (true) {
                $this->render($output, $actions, $empty);
                sleep($refresh);
            }
        } else {
            $this->render($output, $actions, $input->getOption('empty'));
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param array           $actions
     * @param bool            $empty
     */
    protected function render(OutputInterface $output, array $actions, $empty = false)
    {
        $fields = [
            'action'   => 'name',
            'workers'  => 'current-watching',
            'reserved' => 'current-jobs-reserved',
            'ready'    => 'current-jobs-ready',
            'urgent'   => 'current-jobs-urgent',
            'delayed'  => 'current-jobs-delayed',
            'buried'   => 'current-jobs-buried',
        ];

        $table = new Table($output);
        $table->setHeaders(array_keys($fields));

        $rows = [];
        foreach ($actions as $action) {
            if (!$stats = $this->manager->getActionStats($action)) {
                if (!$empty) {
                    continue;
                }

                $stats = array_combine(array_values($fields), array_fill(0, sizeof($fields), '-'));
                $stats['name'] = $action;
            }

            $rows[$action] = array_map(
                function ($field) use ($stats) {
                    return $stats[$field];
                },
                $fields
            );
        }

        ksort($rows);

        $table->addRows($rows);

        if ($this->lineCount) {
            // move back to the beginning
            $output->write("\033[0G");
            $output->write(sprintf("\033[%dA", $this->lineCount));

            // overwrite the complete table before rendering the new one
            $width = (new Terminal())->getWidth();
            $lines = array_fill(0, $this->lineCount, str_pad('', $width, ' '));
            $output->writeln($lines);

            $output->write(sprintf("\033[%dA", $this->lineCount));
        }

        // render the new table
        $table->render();

        // top table border + header + header border + bottom table border = 4
        $this->lineCount = 4 + sizeof($rows);
    }
}
