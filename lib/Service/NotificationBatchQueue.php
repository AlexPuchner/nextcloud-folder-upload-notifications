<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\BackgroundJob\FlushNotificationBatchJob;
use OCA\FolderUploadNotifications\Db\NotificationBatchMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\Files\Folder;

class NotificationBatchQueue {
	public const WINDOW_SECONDS = 120;

	public function __construct(
		private readonly NotificationBatchMapper $batchMapper,
		private readonly IJobList $jobList,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @param array<string, array{push: bool, email: bool}> $deliveries
	 */
	public function enqueue(File $file, array $deliveries, ?string $actorUserId): void {
		if ($deliveries === []) {
			return;
		}

		$folder = $file->getParent();
		if (!$folder instanceof Folder) {
			return;
		}

		$folderFileId = $folder->getId();
		$lastFileId = $file->getId();
		if ($folderFileId <= 0 || $lastFileId <= 0) {
			return;
		}

		$folderStorageId = $folder->getStorage()->getId();
		$actorUserId ??= '';
		$now = $this->timeFactory->getTime();

		foreach ($deliveries as $recipientUserId => $channels) {
			if (!$channels['push'] && !$channels['email']) {
				continue;
			}

			$groupKey = hash('sha256', implode("\0", [
				$recipientUserId,
				$actorUserId,
				$folderStorageId,
				(string)$folderFileId,
			]));

			[$batch] = $this->batchMapper->enqueue(
				$groupKey,
				$recipientUserId,
				$actorUserId,
				$folderStorageId,
				$folderFileId,
				$lastFileId,
				$channels['push'],
				$channels['email'],
				$now,
			);

			$batchId = $batch->getId();
			if ($batchId === null) {
				continue;
			}

			$argument = ['batchId' => $batchId];
			if (!$this->jobList->has(FlushNotificationBatchJob::class, $argument)) {
				$this->jobList->scheduleAfter(
					FlushNotificationBatchJob::class,
					$batch->getCreatedAt() + self::WINDOW_SECONDS,
					$argument,
				);
			}
		}
	}
}
