<?php

namespace TreeHouse\WorkerBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TreeHouse\WorkerBundle\QueueManager;

class ScheduleCommand extends Command
{
    /**
     * @var QueueManager
     */
    protected $manager;

    /**
     * @var ManagerRegistry
     */
    protected $doctrine;

    /**
     * @param QueueManager    $queueManager
     * @param ManagerRegistry $doctrine
     */
    public function __construct(QueueManager $queueManager, ManagerRegistry $doctrine)
    {
        $this->manager = $queueManager;
        $this->doctrine = $doctrine;

        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('worker:schedule')
            ->addArgument('action', InputArgument::REQUIRED, 'The action to execute')
            ->addArgument('payload', InputArgument::IS_ARRAY, 'The payload to send to the action')
            ->addOption('dql', 'd', InputOption::VALUE_OPTIONAL, 'A DQL query to execute. The resulting entities will all be forwarded to the job scheduler')
            ->setDescription('Schedules jobs based on a payload or DQL result set')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action  = $input->getArgument('action');
        $payload = $input->getArgument('payload');
        $dql     = $input->getOption('dql');

        if ($payload && $dql) {
            throw new \InvalidArgumentException('You cannot provide both a <comment>payload</comment> and a <comment>--dql</comment> query.');
        }

        if (!empty($payload)) {
            $job = $this->manager->add($action, $payload);
            $output->writeln(sprintf('Scheduled job <info>%d</info> with payload <info>%s</info>', $job, json_encode($payload)));

            return 0;
        }

        if (empty($dql)) {
            throw new \InvalidArgumentException('You must provide either a <comment>payload</comment> or a <comment>--dql</comment> query.');
        }

        $doctrine = $this->doctrine->getManager();

        if (!$doctrine instanceof EntityManagerInterface) {
            $output->writeln('<error>Sorry, only Doctrine\'s ORM is supported at this point. You\'re welcome to submit a PR of course!</error>');

            return 1;
        }

        $meta  = null;
        $query = $doctrine->createQuery($dql);

        foreach ($query->iterate() as list ($entity)) {
            if (!$meta) {
                $meta = $doctrine->getClassMetadata(get_class($entity));
            }

            $job = $this->manager->addForObject($action, $entity);

            $output->writeln(
                sprintf(
                    'Scheduled job <info>%d</info> for entity <info>%s: %s</info>',
                    $job,
                    json_encode($meta->getIdentifierValues($entity)),
                    $this->entityToString($entity)
                )
            );

            $doctrine->clear();
        }

        return 0;
    }

    /**
     * @param object $entity
     *
     * @return string
     */
    protected function entityToString($entity)
    {
        return method_exists($entity, '__toString') ? (string) $entity : get_class($entity) . '@' . spl_object_hash($entity);
    }
}
