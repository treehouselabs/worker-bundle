worker-bundle
=============

A Symfony2 bundle that adds worker functionality to your project, using
[Beanstalkd][beanstalkd] as the message queue.

[beanstalkd]: http://kr.github.io/beanstalkd/

[![Build Status](https://travis-ci.org/treehouselabs/TreeHouseWorkerBundle.svg)](https://travis-ci.org/treehouselabs/TreeHouseWorkerBundle)
[![Code Coverage](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseWorkerBundle/badges/coverage.png)](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseWorkerBundle/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseWorkerBundle/badges/quality-score.png)](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseWorkerBundle/)

## Installation

For this process, we assume you have a Beanstalk server up and running.

Install via [Composer][composer]:

```bash
$ composer require treehouselabs/worker-bundle:~1.0
```

[composer]: https://getcomposer.org

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

The bundle also supports the [PheanstalkBundle][pb], if you're using that:

```yaml
# app/config/config.yml

tree_house_worker:
  pheanstalk: leezy.pheanstalk
```

[pb]: https://github.com/armetiz/LeezyPheanstalkBundle

## Basic Usage

The bundle creates a [`QueueManager`][qm] service, which you can use to manage
jobs. The manager has services registered to execute specific tasks, called
_executors_. An executor receives a job from the QueueManager and processes it.

[qm]: /src/TreeHouse/WorkerBundle/QueueManager.php

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

    public function configurePayload(OptionsResolver $resolver)
    {
        $resolver->setRequired(0);
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

You can run workers by adding them to your crontab, creating a
[Supervisor][supervisord] program for it, or whatever your preferred method is.

[supervisord]: http://supervisord.org

## Documentation

1. [Message queues & workers][doc-1]
2. [The QueueManager][doc-2]
3. [Executors][doc-3]
4. [Working jobs][doc-4]

[doc-1]: /docs/1-introduction.md
[doc-2]: /docs/2-queue-manager.md
[doc-3]: /docs/3-executors.md
[doc-4]: /docs/4-working-jobs.md
