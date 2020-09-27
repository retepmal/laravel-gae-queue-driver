<?php

namespace Retepmal\LaravelGaeQueue;

use Google_Client;
use Google_Service_CloudTasks;
use Google_Service_CloudTasks_Location;
use Google_Service_ServiceUser;
use Google\Cloud\Core\ExponentialBackoff;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Connectors\ConnectorInterface;

class GaeQueueConnector
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config): QueueContract
    {
        $projectId = $config['project_id'] ?? env('GOOGLE_CLOUD_PROJECT');

        return new GaeQueue(
            $projectId,
            $config['location_id'] ?? $this->getLocationId($projectId),
            $config['queue_id'] ?? 'default'
        );
    }

    /**
     * Obtain the first available location ID for the project if not specified.
     *
     * @param  string  $projectId
     * @return string|null
     */
    protected function getLocationId(string $projectId): ?string
    {
        $client = new Google_Client;
        $client->useApplicationDefaultCredentials();
        $client->addScope(Google_Service_ServiceUser::CLOUD_PLATFORM);

        $service = new Google_Service_CloudTasks($client);
        $response = null;

        // use projects.locations.list to get the location IDs
        $backoff = new ExponentialBackoff;
        $backoff->execute(function () use (&$response, $service, $projectId) {
            $response = $service->projects_locations->listProjectsLocations('projects/' . $projectId);
        });

        // expect Google_Service_CloudTasks_Location
        $firstCloudTaskLocation = data_get($response, 'locations.0');

        return ($firstCloudTaskLocation instanceof Google_Service_CloudTasks_Location)
            ? $firstCloudTaskLocation->getLocationId()
            : null;
    }
}
