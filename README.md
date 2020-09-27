# Laravel Queue on Google App Engine
[Google Cloud Task](https://cloud.google.com/tasks) can manage Laravel job queues by collecting job payloads, sending them via a HTTP request and running the job by a job handler endpoint.

> Warning: the rate limit will be handled by Cloud Task queue.yaml instead of individual jobs.

## Installation
1. Install this package via the Composer package manager:
```shell
composer require retepmal/laravel-gae-queue-driver
```

2. Set the following environment varibales:
```
QUEUE_CONNECTION=gae
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service/account.json # for local testing
GOOGLE_CLOUD_PROJECT=
GOOGLE_CLOUD_TASK_LOCATION_ID=
GOOGLE_CLOUD_TASK_HANDLER_URI=
```

> Provide the App Engine region as ``GOOGLE_CLOUD_TASK_LOCATION_ID``.
> 
> For the handler engpoint, it is /task_handler by default and you may set it using ``GOOGLE_CLOUD_TASK_HANDLER_URI``.

3. Create an ``queue.yaml`` file in project root as below:
```yaml
queue:
- name: default
  rate: 1/s
  bucket_size: 5
  retry_parameters:
    task_retry_limit: 3
```

> For fine-turing the queue, please refer to [queue.yaml Reference](https://cloud.google.com/appengine/docs/standard/php/config/queueref)

4. Deplay the queue setting by executing the command:
```shell
gcloud app delpoy queue.yaml
```
