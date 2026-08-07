<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\Dto\ResolvedFolder;
use OCA\FolderUploadNotifications\Exception\FolderNotFoundException;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

class FolderResolver {
	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	/**
	 * Resolves a file ID only inside the current user's visible file tree.
	 */
	public function resolveAccessibleFolder(
		string $userId,
		int $folderFileId,
		?string $requiredStorageId = null,
	): ResolvedFolder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$nodes = $userFolder->getId() === $folderFileId
			? [$userFolder]
			: $userFolder->getById($folderFileId);

		foreach ($nodes as $node) {
			if (!$node instanceof Folder
				|| ($node->getPermissions() & Constants::PERMISSION_READ) === 0
				|| ($requiredStorageId !== null && $node->getStorage()->getId() !== $requiredStorageId)) {
				continue;
			}

			$relativePath = $userFolder->getRelativePath($node->getPath());
			if ($relativePath === null) {
				continue;
			}

			$displayPath = '/' . ltrim($relativePath, '/');

			return new ResolvedFolder(
				$node->getStorage()->getId(),
				$node->getId(),
				$displayPath,
			);
		}

		throw new FolderNotFoundException('Folder not found or not accessible');
	}
}
