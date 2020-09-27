<?php

namespace Retepmal\LaravelGaeQueue;

use Google_Client;
use Google_Service_CloudTasks;
use Google_Service_CloudTasks_AppEngineHttpRequest;
use Google_Service_CloudTasks_CreateTaskRequest;
use Google_Service_CloudTasks_Task;
use Google_Service_ServiceUser;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Queue\Queue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GaeQueue extends Queue implements QueueContract
{
    /**
     * The Google project ID.
     *
     * @var string
     */
    protected $project;

    /**
     * The Location ID.
     *
     * @var string
     */
    protected $location;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * Create a new Google App Engine queue instance.
     *
     * @param  string  $project
     * @param  string  $location
     * @return void
     */
    public function __construct(string $project, string $location)
    {
        $this->project = $project;
        $this->location = $location;
    }

    /**
     * Get the size of the queue.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null)
    {
        // Cloud Task API don't support counting tasks
        return 0;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushToCloudTask($queue, $this->createPayload(
            $job, $this->getQueue($queue), $data
        ));
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->pushToCloudTask($this->getQueue($queue), $payload);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushToCloudTask($queue, $this->createPayload(
            $job, $this->getQueue($queue), $data
        ), $delay);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        // Cloud Task would send HTTP request to our handler endpoint,
        // instead of running the jobs using queue:* commands
        return null;
    }

    /**
     * Get the queue or return the default.
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    /**
     * Push a raw payload to the Cloud Task with a given delay.
     *
     * @param  string|null  $queue
     * @param  string  $payload
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return mixed
     */
    protected function pushToCloudTask($queue, $payload, $delay = 0)
    {
        $parent = sprintf(
            'projects/%s/locations/%s/queues/%s',
            $this->project,
            $this->location,
            $this->getQueue($queue)
        );

        // build the AppEngineHttpRequest resource
        $appEngineHttpRequest = new Google_Service_CloudTasks_AppEngineHttpRequest;
        $appEngineHttpRequest->setHttpMethod('POST');
        $appEngineHttpRequest->setRelativeUri(env('GOOGLE_CLOUD_TASK_HANDLER_URI', '/task_handler'));
        $appEngineHttpRequest->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);
        $appEngineHttpRequest->setBody(base64_encode($payload));        

        // build the Task resource
        $task = new Google_Service_CloudTasks_Task;
        $task->setScheduleTime(
            Carbon::createFromTimestamp($this->availableAt($delay))
                ->toIso8601ZuluString()
        );
        $task->setAppEngineHttpRequest($appEngineHttpRequest);

        // build the task creation request
        $requestBody = new Google_Service_CloudTasks_CreateTaskRequest;
        $requestBody->setTask($task);

        // initialize the API client
        $client = new Google_Client;
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_ServiceUser::CLOUD_PLATFORM);

        $service = new Google_Service_CloudTasks($client);
        $response = null;

        // send the request with retries if failed
        $backoff = new ExponentialBackoff;
        $backoff->execute(function () use (&$response, $service, $parent, $requestBody) {
            $response = $service->projects_locations_queues_tasks->create($parent, $requestBody);
        });

        // return the task ID if success
        return ($response instanceof Google_Service_CloudTasks_Task)
            ? Str::afterLast($response->getName(), $parent . '/tasks/')
            : null;
    }
}
