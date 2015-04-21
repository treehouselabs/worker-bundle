<?php

namespace TreeHouse\WorkerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ExecutorCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritdoc
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('tree_house.worker.queue_manager')) {
            return;
        }

        $definition = $container->getDefinition('tree_house.worker.queue_manager');

        $taggedServices = $container->findTaggedServiceIds('tree_house.worker.executor');
        foreach ($taggedServices as $id => $attributes) {
            $definition->addMethodCall('addExecutor', [new Reference($id)]);
        }
    }
}
