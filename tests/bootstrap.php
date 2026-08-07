<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists(\OCP\BackgroundJob\QueuedJob::class)) {
	require_once __DIR__ . '/stubs/queued_job.php';
}
