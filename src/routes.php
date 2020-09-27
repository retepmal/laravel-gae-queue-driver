<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

Route::post(env('GOOGLE_CLOUD_TASK_HANDLER_URI', '/task_handler'), function (Request $request) {
    // refuse to execute job if there is no X-AppEngine-TaskName header in the request
    if (empty($request->header('X-AppEngine-TaskName'))) {
        throw new BadRequestHttpException('No X-Appengine-Taskname request header found');
    }

    // refuse any request with empty job data
    if (empty($request->input('data.command'))) {
        throw new BadRequestHttpException('Invalid task payload');
    }

    // unpack the job from data.command in JSON
    $job = unserialize($request->input('data.command'));

    // run the job synchronously
    dispatch($job)->onConnection('sync');
})->withoutMiddleware([
    // skipping CSRF verification as the request comes from Google internal servers
    VerifyCsrfToken::class,
]);
