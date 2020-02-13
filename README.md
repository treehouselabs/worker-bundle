Deprecated & archived
=====================

This bundle is no longer maintained. It will still work with Symfony versions `^2.8|^3.0|^4.0` and is archived here to not break existing applications using it. However it will not get maintenance updates or even security fixes.

If you need queue/worker functionality in your project, there are far better solutions right now to look at:

* https://github.com/symfony/messenger/
* https://github.com/php-enqueue/enqueue-dev

Previous readme below 👇

---

Worker bundle
=============

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]

A Symfony bundle that adds worker functionality to your project, using
[Beanstalkd][beanstalkd] as the message queue.

[beanstalkd]: http://kr.github.io/beanstalkd/

## Installation

For this process, we assume you have a Beanstalk server up and running.

Install via [Composer][composer]:

```bash
$ composer require treehouselabs/worker-bundle
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


## Security

If you discover any security related issues, please email dev@treehouse.nl
instead of using the issue tracker.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


## Credits

- [Peter Kruithof][link-author]
- [All Contributors][link-contributors]


[ico-version]: https://img.shields.io/packagist/v/treehouselabs/worker-bundle.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/treehouselabs/worker-bundle/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/treehouselabs/worker-bundle.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/treehouselabs/worker-bundle.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/treehouselabs/worker-bundle.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/treehouselabs/worker-bundle
[link-travis]: https://travis-ci.org/treehouselabs/worker-bundle
[link-scrutinizer]: https://scrutinizer-ci.com/g/treehouselabs/worker-bundle/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/treehouselabs/worker-bundle
[link-downloads]: https://packagist.org/packages/treehouselabs/worker-bundle
[link-author]: https://github.com/pkruithof
[link-contributors]: ../../contributors
