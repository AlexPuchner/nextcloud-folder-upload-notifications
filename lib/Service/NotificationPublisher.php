<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCA\FolderUploadNotifications\Notification\Notifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Notification\IManager;

class NotificationPublisher {
	public function __construct(
		private readonly IManager $notificationManager,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @param list<string> $recipientUserIds
	 */
	public function publish(File $file, array $recipientUserIds, ?string $actorUserId): void {
		if ($recipientUserIds === []) {
			return;
		}

		$shouldFlush = $this->notificationManager->defer();

		try {
			foreach ($recipientUserIds as $recipientUserId) {
				$notification = $this->notificationManager->createNotification();
				$notification
					->setApp(Application::APP_ID)
					->setUser($recipientUserId)
					->setDateTime($this->timeFactory->getDateTime())
					->setObject('file', (string)$file->getId())
					->setSubject(Notifier::SUBJECT_FILE_CREATED, [
						'actorUserId' => $actorUserId ?? '',
					]);

				$this->notificationManager->notify($notification);
			}
		} finally {
			if ($shouldFlush) {
				$this->notificationManager->flush();
			}
		}
	}

	public function publishBatch(NotificationBatch $batch): void {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(Application::APP_ID)
			->setUser($batch->getRecipientUserId())
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('folder', (string)$batch->getFolderFileId())
			->setSubject(Notifier::SUBJECT_FILES_CREATED, [
				'actorUserId' => $batch->getActorUserId(),
				'fileCount' => $batch->getFileCount(),
				'batchId' => $batch->getId() ?? 0,
			]);

		$this->notificationManager->notify($notification);
	}
}
