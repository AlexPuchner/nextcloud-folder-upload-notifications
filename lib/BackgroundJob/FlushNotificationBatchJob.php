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
		$batchId = 0;
		if (is_array($argument) && is_int($argument['batchId'] ?? null)) {
			$batchId = $argument['batchId'];
		}
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

		$revision = $batch->getRevision();
		if ($batch->getNotifyPush()) {
			$this->dispatcher->dispatchPush($batch);
			if ($this->batchMapper->clearPushIfRevision($batchId, $revision) === 0) {
				$this->rescheduleChangedBatch($batchId, $now);

				return;
			}
			$batch->setNotifyPush(false);
		}

		if ($batch->getNotifyEmail()) {
			$this->dispatcher->dispatchEmail($batch);
			if ($this->batchMapper->clearEmailIfRevision($batchId, $revision) === 0) {
				$this->rescheduleChangedBatch($batchId, $now);

				return;
			}
			$batch->setNotifyEmail(false);
		}

		if ($this->batchMapper->deleteIfRevision($batchId, $revision) === 0) {
			$this->rescheduleChangedBatch($batchId, $now);
		}
	}

	private function rescheduleChangedBatch(int $batchId, int $now): void {
		$this->logger->info('Folder upload notification batch changed while being flushed', [
			'app' => Application::APP_ID,
			'batchId' => $batchId,
		]);
		$this->jobList->scheduleAfter(self::class, $now + 5, ['batchId' => $batchId]);
	}
}
