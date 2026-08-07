<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Db\SubscriptionMapper;
use OCA\FolderUploadNotifications\Exception\SubscriptionNotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DbException;

class SubscriptionService {
	public function __construct(
		private readonly SubscriptionMapper $mapper,
		private readonly FolderResolver $folderResolver,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @return list<Subscription>
	 */
	public function findAllForUser(string $userId): array {
		return $this->mapper->findAllForUser($userId);
	}

	public function createForUser(
		string $userId,
		int $folderFileId,
		bool $recursive,
		bool $notifyOwnUploads,
		bool $notifyPush,
		bool $notifyEmail,
	): Subscription {
		if ($folderFileId <= 0) {
			throw new \InvalidArgumentException('Folder file ID must be positive');
		}

		$folder = $this->folderResolver->resolveAccessibleFolder($userId, $folderFileId);
		$subscription = $this->mapper->findByFolderForUser(
			$userId,
			$folder->storageId,
			$folder->fileId,
		);
		$timestamp = $this->timeFactory->getTime();

		if ($subscription === null) {
			$subscription = new Subscription();
			$subscription->setUserId($userId);
			$subscription->setStorageId($folder->storageId);
			$subscription->setFolderFileId($folder->fileId);
			$subscription->setCreatedAt($timestamp);
		}

		$subscription->setDisplayPath($folder->displayPath);
		$subscription->setRecursive($recursive);
		$subscription->setNotifyOwnUploads($notifyOwnUploads);
		$subscription->setNotifyPush($notifyPush);
		$subscription->setNotifyEmail($notifyEmail);
		$subscription->setUpdatedAt($timestamp);

		if ($subscription->getId() === null) {
			try {
				return $this->mapper->insert($subscription);
			} catch (DbException $exception) {
				if ($exception->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $exception;
				}

				$subscription = $this->mapper->findByFolderForUser(
					$userId,
					$folder->storageId,
					$folder->fileId,
				);
				if ($subscription === null) {
					throw $exception;
				}

				$subscription->setDisplayPath($folder->displayPath);
				$subscription->setRecursive($recursive);
				$subscription->setNotifyOwnUploads($notifyOwnUploads);
				$subscription->setNotifyPush($notifyPush);
				$subscription->setNotifyEmail($notifyEmail);
				$subscription->setUpdatedAt($timestamp);

				return $this->mapper->update($subscription);
			}
		}

		return $this->mapper->update($subscription);
	}

	public function updateForUser(
		string $userId,
		int $id,
		bool $recursive,
		bool $notifyOwnUploads,
		bool $notifyPush,
		bool $notifyEmail,
	): Subscription {
		$subscription = $this->findForUser($id, $userId);
		$subscription->setRecursive($recursive);
		$subscription->setNotifyOwnUploads($notifyOwnUploads);
		$subscription->setNotifyPush($notifyPush);
		$subscription->setNotifyEmail($notifyEmail);
		$subscription->setUpdatedAt($this->timeFactory->getTime());

		return $this->mapper->update($subscription);
	}

	public function deleteForUser(string $userId, int $id): void {
		$this->mapper->delete($this->findForUser($id, $userId));
	}

	private function findForUser(int $id, string $userId): Subscription {
		if ($id <= 0) {
			throw new SubscriptionNotFoundException('Subscription not found');
		}

		try {
			return $this->mapper->findForUser($id, $userId);
		} catch (DoesNotExistException $exception) {
			throw new SubscriptionNotFoundException('Subscription not found', 0, $exception);
		}
	}
}
