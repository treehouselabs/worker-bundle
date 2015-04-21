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
        $rootNode = $treeBuilder->root('tree_house_worker');
        $rootNode
            ->children()
                ->scalarNode('pheanstalk')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
        ;

        return $treeBuilder;
    }
}
