<?php

namespace App\Listeners;

use App\Services\ApplicationHealth;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerIdle;
use Illuminate\Queue\Events\WorkerStarting;

class RecordQueueHeartbeat
{
    public function __construct(public ApplicationHealth $health) {}

    public function handle(WorkerIdle|WorkerStarting|Looping|JobProcessing|JobPopping $event): void
    {
        if ($event instanceof JobProcessing) {
            $this->health->record(
                (string) $event->connectionName,
                $this->health->parseQueueList((string) $event->job->getQueue()),
                mergeExisting: true,
            );

            return;
        }

        $queue = (string) ($event->queue ?? '');

        if ($queue === '') {
            return;
        }

        $this->health->record(
            (string) $event->connectionName,
            $this->health->parseQueueList($queue),
        );
    }
}
