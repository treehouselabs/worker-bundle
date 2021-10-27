<?php

namespace TreeHouse\WorkerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('tree_house_worker');
        $rootNode = $treeBuilder->getRootNode()->children();

        $rootNode
            ->scalarNode('pheanstalk')
            ->info('The id of a pheanstalk service. The service must implement Pheanstalk\\PheanstalkInterface')
        ;

        // beanstalk settings
        $queue = $rootNode->arrayNode('queue')->children();
        $queue
            ->scalarNode('server')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('The server where Beanstalk is running')
        ;

        $queue
            ->scalarNode('port')
            ->cannotBeEmpty()
            ->defaultValue(11300)
            ->info('The port Beanstalk is listening to')
        ;

        $queue
            ->scalarNode('timeout')
            ->cannotBeEmpty()
            ->defaultValue(60)
            ->info('Connection timeout')
        ;

        // queue manager settings

        $qm = $rootNode->arrayNode('queue_manager');
        $qm
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('default_ttr')
                    ->cannotBeEmpty()
                    ->defaultValue(1200)
                    ->isRequired()
                    ->info('Default time-to-run for a job')
                ->end()
            ->end()
        ;


        return $treeBuilder;
    }
}
