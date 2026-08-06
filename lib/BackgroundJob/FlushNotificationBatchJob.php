<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\BackgroundJob;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Db\NotificationBatchMapper;
use OCA\FolderUploadNotifications\Service\NotificationBatchDispatcher;
use OCA\FolderUploadNotifications\Service\NotificationBatchQueue;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class FlushNotificationBatchJob extends QueuedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private readonly NotificationBatchMapper $batchMapper,
		private readonly NotificationBatchDispatcher $dispatcher,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setAllowParallelRuns(false);
	}

	/** @param mixed $argument */
	protected function run($argument): void {
		$batchId = is_array($argument) && is_int($argument['batchId'] ?? null)
			? $argument['batchId']
			: 0;
		if ($batchId <= 0) {
			return;
		}

		try {
			$this->flush($batchId);
		} catch (\Throwable $exception) {
			$this->logger->error('Failed to flush folder upload notification batch', [
				'app' => Application::APP_ID,
				'batchId' => $batchId,
				'exception' => $exception,
			]);
			$this->jobList->scheduleAfter(
				self::class,
				$this->time->getTime() + 60,
				['batchId' => $batchId],
			);
		}
	}

	private function flush(int $batchId): void {
		try {
			$batch = $this->batchMapper->find($batchId);
		} catch (DoesNotExistException) {
			return;
		}

		$dueAt = $batch->getCreatedAt() + NotificationBatchQueue::WINDOW_SECONDS;
		$now = $this->time->getTime();
		if ($now < $dueAt) {
			$this->jobList->scheduleAfter(self::class, $dueAt, ['batchId' => $batchId]);

			return;
		}

		// Claim an immutable snapshot. If an upload races with this job, reload
		// the incremented row instead of dropping that file from the batch.
		for ($attempt = 0; $attempt < 3; $attempt++) {
			if ($this->batchMapper->deleteIfRevision($batchId, $batch->getRevision()) > 0) {
				$this->dispatcher->dispatch($batch);

				return;
			}

			try {
				$batch = $this->batchMapper->find($batchId);
			} catch (DoesNotExistException) {
				return;
			}
		}

		$this->logger->warning('Folder upload notification batch changed repeatedly while being flushed', [
			'app' => Application::APP_ID,
			'batchId' => $batchId,
		]);
		$this->jobList->scheduleAfter(self::class, $now + 5, ['batchId' => $batchId]);
	}
}
