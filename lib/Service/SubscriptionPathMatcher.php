<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

class SubscriptionPathMatcher {
	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	/**
	 * Matches in the subscriber's visible file tree. This covers mount points
	 * whose virtual parents are absent from the uploader's event-side tree.
	 */
	public function matches(Subscription $subscription, File $file): bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($subscription->getUserId());
			$userFolderPath = $userFolder->getPath();
			$folderNodes = $userFolder->getId() === $subscription->getFolderFileId()
				? [$userFolder]
				: $this->rootFolder->getByIdInPath(
					$subscription->getFolderFileId(),
					$userFolderPath,
				);
			$fileNodes = $this->rootFolder->getByIdInPath($file->getId(), $userFolderPath);
		} catch (\Throwable) {
			return false;
		}

		foreach ($folderNodes as $folderNode) {
			if (!$folderNode instanceof Folder
				|| !$folderNode->isReadable()
				|| $folderNode->getStorage()->getId() !== $subscription->getStorageId()) {
				continue;
			}

			foreach ($fileNodes as $fileNode) {
				if (!$fileNode instanceof File || !$fileNode->isReadable()) {
					continue;
				}

				$relativePath = $folderNode->getRelativePath($fileNode->getPath());
				if ($relativePath === null) {
					continue;
				}

				$relativePath = trim($relativePath, '/');
				if ($relativePath === '') {
					continue;
				}

				if ($subscription->getRecursive() || !str_contains($relativePath, '/')) {
					return true;
				}
			}
		}

		return false;
	}
}
