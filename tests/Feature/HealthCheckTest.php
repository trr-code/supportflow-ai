<?php

use App\Exceptions\ApplicationUnhealthyException;
use App\Services\ApplicationHealth;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\WorkerIdle;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

test('GET /up succeeds when the database is up and the worker lists ai and default', function () {
    seedQueueHeartbeat();

    $this->get('/up')->assertSuccessful();
});

test('GET /up returns 500 when the queue heartbeat is missing', function () {
    Cache::forget(ApplicationHealth::CACHE_KEY);

    $this->get('/up')->assertServerError();
});

test('GET /up returns 500 when the queue heartbeat is stale', function () {
    seedQueueHeartbeat();

    $this->travel(91)->seconds();

    $this->get('/up')->assertServerError();
});

test('GET /up returns 500 when the heartbeat lists only ai', function () {
    seedQueueHeartbeat(['ai']);

    $this->get('/up')->assertServerError();
});

test('GET /up returns 500 when the heartbeat lists only default', function () {
    seedQueueHeartbeat(['default']);

    $this->get('/up')->assertServerError();
});

test('GET /up returns 500 when the database ping fails', function () {
    seedQueueHeartbeat();

    $this->mock(ApplicationHealth::class, function (MockInterface $mock): void {
        $mock->shouldReceive('assertHealthy')
            ->once()
            ->andThrow(new ApplicationUnhealthyException('Database is unavailable.'));
    });

    $this->get('/up')->assertServerError();
});

test('an idle worker heartbeat listing ai and default keeps GET /up available', function () {
    Event::dispatch(new WorkerIdle('database', 'ai,default', new WorkerOptions));

    $this->get('/up')->assertSuccessful();

    expect(Cache::get(ApplicationHealth::CACHE_KEY)['queues'])->toBe(['ai', 'default'])
        ->and(Cache::get(ApplicationHealth::CACHE_KEY)['connection'])->toBe('database');
});

test('a listen-style job pop listing ai and default keeps GET /up available', function () {
    Event::dispatch(new JobPopping('database', 'ai,default'));

    $this->get('/up')->assertSuccessful();

    expect(Cache::get(ApplicationHealth::CACHE_KEY)['queues'])->toBe(['ai', 'default'])
        ->and(Cache::get(ApplicationHealth::CACHE_KEY)['connection'])->toBe('database');
});

test('a job processing refresh keeps previously recorded required queues', function () {
    Event::dispatch(new WorkerIdle('database', 'ai,default', new WorkerOptions));

    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('getQueue')->andReturn('ai');

    Event::dispatch(new JobProcessing('database', $job));

    $this->get('/up')->assertSuccessful();

    expect(Cache::get(ApplicationHealth::CACHE_KEY)['queues'])->toBe(['ai', 'default']);
});
