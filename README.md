worker-bundle
=============

A Symfony2 bundle that adds worker functionality to your project, using
Beanstalkd[0] as the message queue.

[0]: http://kr.github.io/beanstalkd/

## Installation

For this process, we assume you have a Beanstalk server up and running.

Install via [Composer][1]:

```bash
$ composer require treehouselabs/worker-bundle:~1.0
```

[1]: https://getcomposer.org

Enable the bundle:

```php
# app/AppKernel.php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...

            new TreeHouse\WorkerBundle\TreeHouseWorkerBundle(),
        ];

        // ...
    }

    // ...
}
```

## Configuration

Define a queue and you're good to go:

```yaml
# app/config/config.yml

tree_house_worker:
  queue:
    server: localhost
```

The bundle also supports the [PheanstalkBundle][2], if you're using that:

```yaml
# app/config/config.yml

tree_house_worker:
  pheanstalk: leezy.pheanstalk
```

[2]: https://github.com/armetiz/LeezyPheanstalkBundle

## Basic Usage

The bundle creates a [`QueueManager`][3] service, which you can use to manage
jobs. The manager has services registered to execute specific tasks, called
_executors_. An executor receives a job from the QueueManager and processes it.

[3]: /src/TreeHouse/WorkerBundle/QueueManager.php

### Defining executors

First you need to register an executor, and tag it as such:

```php
# src/AppBundle/Executor/HelloWorldExecutor.php

use TreeHouse\WorkerBundle\Executor\AbstractExecutor;

class HelloWorldExecutor extends AbstractExecutor
{
    public function getName()
    {
        return 'hello.world';
    }

    public function execute(array $payload)
    {
        $name = array_shift($payload);

        # process stuff here, in this example we just print something
        echo 'Hello, ' . $name;

        return true;
    }
}
```

```yaml
# app/config/services.yml
app.executor.hello_world:
  class: AppBundle/Executor/HelloWorldExecutor
  tags:
    - { name: tree_house.worker.executor }
```

### Scheduling jobs

Now you can add jobs, either via code or using the `worker:schedule` command:

**In your application's code:**

```php
$queueManager = $container->get('tree_house.worker.queue_manager');
$queueManager->add('hello.world', ['Peter']);
```

**Using the command:**

```
php app/console worker:schedule hello.world Peter
```

### Working jobs

A worker can now receive and process these jobs via the console:

```
php app/console worker:run

# prints:
# Working hello.world with payload ["Peter"]
# Hello, Peter
# Completed job in 1ms with result: true
```

You can run workers by adding them to your crontab, creating a [Supervisor][4]
program for it, or whatever your preferred method is.

[4]: http://supervisord.org

## Documentation

1. [Message queues & workers][doc-1]
2. [The QueueManager][doc-2]
3. [Executors][doc-3]
4. [Running workers][doc-4]
5. Commands

[doc-1]: /docs/1-introduction.md
[doc-2]: /docs/2-queue-manager.md
[doc-3]: /docs/3-executors.md
[doc-4]: /docs/4-running-workers.md
