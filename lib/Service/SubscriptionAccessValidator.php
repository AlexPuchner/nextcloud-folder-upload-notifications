<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\Db\Subscription;

class SubscriptionAccessValidator {
	public function __construct(
		private readonly FolderResolver $folderResolver,
	) {
	}

	public function canReceive(Subscription $subscription): bool {
		try {
			$folder = $this->folderResolver->resolveAccessibleFolder(
				$subscription->getUserId(),
				$subscription->getFolderFileId(),
			);
		} catch (\Throwable) {
			return false;
		}

		return $folder->storageId === $subscription->getStorageId();
	}
}
