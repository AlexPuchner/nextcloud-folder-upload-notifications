<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class NotificationBatchDispatcher {
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly NotificationPublisher $notificationPublisher,
		private readonly EmailPublisher $emailPublisher,
		private readonly LoggerInterface $logger,
	) {
	}

	public function dispatchPush(NotificationBatch $batch): void {
		if ($batch->getFileCount() > 1) {
			$this->notificationPublisher->publishBatch($batch);

			return;
		}

		$file = $this->resolveAccessibleFile($batch);
		if ($file instanceof File) {
			$this->notificationPublisher->publish(
				$file,
				[$batch->getRecipientUserId()],
				$this->actorUserId($batch),
			);
		}
	}

	public function dispatchEmail(NotificationBatch $batch): void {
		if ($batch->getFileCount() > 1) {
			$this->emailPublisher->publishBatch($batch);

			return;
		}

		$file = $this->resolveAccessibleFile($batch);
		if ($file instanceof File) {
			$this->emailPublisher->publish(
				$file,
				[$batch->getRecipientUserId()],
				$this->actorUserId($batch),
			);
		}
	}

	private function resolveAccessibleFile(NotificationBatch $batch): ?File {
		$userFolder = $this->rootFolder->getUserFolder($batch->getRecipientUserId());

		foreach ($userFolder->getById($batch->getLastFileId()) as $node) {
			if ($node instanceof File && $node->isReadable()) {
				return $node;
			}
		}

		$this->logger->warning('Folder upload notification skipped because the file is no longer accessible', [
			'app' => Application::APP_ID,
			'batchId' => $batch->getId(),
			'userId' => $batch->getRecipientUserId(),
		]);

		return null;
	}

	private function actorUserId(NotificationBatch $batch): ?string {
		return $batch->getActorUserId() !== '' ? $batch->getActorUserId() : null;
	}
}
