# Introduction into message queues and workers

The world of workers can be a bit overwhelming when you're new to it. This
chapter tries to explain the basic concept and how you can (and why you should)
use it in your project.

Take in mind that this bundle provides basic worker functionality: adding
payloads to a distributing queue and processing those. If you want more control
and stuff, like implementing PubSub patterns, message routing, remote procedure
calls and what not, take a look at our [QueueBundle][0], which uses the AMQP
messaging protocol.

In this documentation we'll only be referring to queues/messages/workers, etc.
in the context of Beanstalk. This is not the Wikipedia page on message queues
:wink:.

[0]: https://github.com/treehouselabs/TreeHouseQueueBundle

## What are message queues and workers?

A _message queue_ is a data store where you can put _jobs_ into a queue. A job
consists just of a simple payload (in our case an array, which is json encoded
into a string). The message queue then distributes these jobs to one or more
_workers_, asynchronously, thereby dividing work load to multiple processes.

When dealing with message queues, there are almost always two sides involved:
a _producer_ and a _consumer_. The producer adds jobs to the queue, while
consumers process them. In our case, both the producer and the consumer are
part of your application code. The producer does not need to be a standalone
application, or even a service. The producing part could be adding a job to
send a registration email from a controller when someone signs up on your site.

The consuming side does benefit from being a separate part of your application,
since you'll likely run multiple instances to quickly process work load.

## Why use a message queue?

Some tasks in an application can be heavy on resources, or time consuming, or
both. Especially — but not only — with user interactions you want quick
responses. Sometimes you can offload work to a separate process if the result
is not immediately needed. In these cases, a message queue can really benefit
your application.

### More reading

There are tons of resources on the internet about message queues and why you
should use them. If you're not convinced yet, you need to read more :wink:.

## Next

In the next chapter we will explain how these concepts are implemented in this
bundle: [The QueueManager][/docs/2-queue-manager.md]
