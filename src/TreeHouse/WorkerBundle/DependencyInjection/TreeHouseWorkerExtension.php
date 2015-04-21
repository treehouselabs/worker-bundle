<?php

namespace TreeHouse\WorkerBundle\DependencyInjection;

use Pheanstalk\Pheanstalk;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class TreeHouseWorkerExtension extends Extension
{
    /**
     * @inheritdoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->configurePheanstalk($config, $container);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function configurePheanstalk(array $config, ContainerBuilder $container)
    {
        $queueManager = $container->getDefinition('tree_house.worker.queue_manager');

        if (isset($config['pheanstalk'])) {
            $queueManager->replaceArgument(0, new Reference($config['pheanstalk']));

            return;
        }

        if (!isset($config['queue'])) {
            throw new InvalidConfigurationException('You must define either a pheanstalk service or a queue configuration');
        }

        // create a pheanstalk
        $queue = $config['queue'];

        $pheanstalkConfig = [
            $queue['server'],
            $queue['port'],
            $queue['timeout'],
        ];

        $pheanstalk = new Definition(Pheanstalk::class, $pheanstalkConfig);
        $queueManager->replaceArgument(0, $pheanstalk);
    }
}
