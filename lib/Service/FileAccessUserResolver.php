<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCP\Files\File;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;

class FileAccessUserResolver {
	public function __construct(
		private readonly IManager $shareManager,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Resolves local users who can currently see the file through a share.
	 *
	 * @return list<string>
	 */
	public function resolve(File $file): array {
		/** @var array<string, true> $userIds */
		$userIds = [];

		try {
			$accessList = $this->shareManager->getAccessList($file, true, true);
			$users = is_array($accessList['users'] ?? null)
				? $accessList['users']
				: [];

			foreach ($users as $userId => $access) {
				if (is_string($userId) && $userId !== '') {
					$userIds[$userId] = true;
				} elseif (is_string($access) && $access !== '') {
					// Keep compatibility with providers returning the non-detailed form.
					$userIds[$access] = true;
				}
			}
		} catch (\Throwable $exception) {
			// Indexed ancestor matching still works if a share provider fails.
			$this->logger->warning('Could not resolve users with shared access to created file', [
				'app' => Application::APP_ID,
				'exception' => $exception,
			]);
		}

		return array_keys($userIds);
	}
}
