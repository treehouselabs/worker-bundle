<?php

namespace spec\TreeHouse\WorkerBundle;

use InvalidArgumentException;
use OutOfBoundsException;
use PhpSpec\ObjectBehavior;
use TreeHouse\WorkerBundle\Executor\ExecutorInterface;
use TreeHouse\WorkerBundle\ExecutorPool;

class ExecutorPoolSpec extends ObjectBehavior
{
    public function it_is_initializable()
    {
        $this->shouldHaveType(ExecutorPool::class);
    }

    public function it_should_be_empty_when_constructed()
    {
        $this->getExecutors()->shouldHaveCount(0);
    }

    public function it_should_throw_exception_on_unknown_executor()
    {
        $this->shouldThrow(
            new OutOfBoundsException('There is no executor registered for action "sample".')
        )->duringGetExecutor('sample');
    }

    public function it_should_have_registered_executor(ExecutorInterface $executor)
    {
        $executor->getName()->willReturn('executor');

        $this->addExecutor($executor);

        $this->hasExecutor('executor')->shouldReturn(true);
    }

    public function it_should_return_registered_executor(ExecutorInterface $executor)
    {
        $executor->getName()->willReturn('executor');

        $this->addExecutor($executor);

        $this->getExecutor('executor')->shouldReturn($executor);
    }

    public function it_should_throw_exception_when_readding_executor_with_same_name(ExecutorInterface $executor)
    {
        $executor->getName()->willReturn('executor');

        $this->addExecutor($executor);

        $expectedException = new InvalidArgumentException(
            'There is already an executor registered for action "executor".'
        );

        $this->shouldThrow($expectedException)->duringAddExecutor($executor);
    }

    public function it_should_register_multiple_executors(ExecutorInterface $firstExecutor, ExecutorInterface $secondExecutor)
    {
        $firstExecutor->getName()->willReturn('executor.first');
        $secondExecutor->getName()->willReturn('executor.second');

        $this->addExecutor($firstExecutor);
        $this->addExecutor($secondExecutor);

        $this->getExecutors()->shouldReturn([
            'executor.first' => $firstExecutor,
            'executor.second' => $secondExecutor
        ]);
    }
}
