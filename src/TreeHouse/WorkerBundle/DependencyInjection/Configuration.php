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
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('tree_house_worker')->children();

        $rootNode
            ->scalarNode('pheanstalk')
            ->info('The id of a pheanstalk service. The service must implement Pheanstalk\\PheanstalkInterface')
        ;

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

        return $treeBuilder;
    }
}
