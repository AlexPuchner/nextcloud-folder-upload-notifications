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

	public function dispatch(NotificationBatch $batch): void {
		if ($batch->getFileCount() <= 1) {
			$this->dispatchSingleFile($batch);

			return;
		}

		if ($batch->getNotifyPush()) {
			try {
				$this->notificationPublisher->publishBatch($batch);
			} catch (\Throwable $exception) {
				$this->logFailure('push', $batch, $exception);
			}
		}

		if ($batch->getNotifyEmail()) {
			try {
				$this->emailPublisher->publishBatch($batch);
			} catch (\Throwable $exception) {
				$this->logFailure('email', $batch, $exception);
			}
		}
	}

	private function dispatchSingleFile(NotificationBatch $batch): void {
		$file = $this->resolveAccessibleFile($batch);
		if (!$file instanceof File) {
			$this->logger->warning('Folder upload notification skipped because the file is no longer accessible', [
				'app' => Application::APP_ID,
				'batchId' => $batch->getId(),
				'userId' => $batch->getRecipientUserId(),
			]);

			return;
		}

		if ($batch->getNotifyPush()) {
			try {
				$this->notificationPublisher->publish(
					$file,
					[$batch->getRecipientUserId()],
					$this->actorUserId($batch),
				);
			} catch (\Throwable $exception) {
				$this->logFailure('push', $batch, $exception);
			}
		}

		if ($batch->getNotifyEmail()) {
			try {
				$this->emailPublisher->publish(
					$file,
					[$batch->getRecipientUserId()],
					$this->actorUserId($batch),
				);
			} catch (\Throwable $exception) {
				$this->logFailure('email', $batch, $exception);
			}
		}
	}

	private function resolveAccessibleFile(NotificationBatch $batch): ?File {
		$userFolder = $this->rootFolder->getUserFolder($batch->getRecipientUserId());

		foreach ($userFolder->getById($batch->getLastFileId()) as $node) {
			if ($node instanceof File && $node->isReadable()) {
				return $node;
			}
		}

		return null;
	}

	private function actorUserId(NotificationBatch $batch): ?string {
		return $batch->getActorUserId() !== '' ? $batch->getActorUserId() : null;
	}

	private function logFailure(
		string $channel,
		NotificationBatch $batch,
		\Throwable $exception,
	): void {
		$this->logger->error('Failed to dispatch folder upload notification batch', [
			'app' => Application::APP_ID,
			'batchId' => $batch->getId(),
			'userId' => $batch->getRecipientUserId(),
			'channel' => $channel,
			'exception' => $exception,
		]);
	}
}
