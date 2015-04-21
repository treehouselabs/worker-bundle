<?php

namespace TreeHouse\WorkerBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use TreeHouse\WorkerBundle\DependencyInjection\Compiler\ExecutorCompilerPass;

class TreeHouseWorkerBundle extends Bundle
{
    /**
     * @inheritdoc
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ExecutorCompilerPass());
    }
}
