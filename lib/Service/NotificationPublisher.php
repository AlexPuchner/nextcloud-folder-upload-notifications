<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
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
}
