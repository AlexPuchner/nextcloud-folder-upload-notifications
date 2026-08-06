<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\Dto\AncestorFolder;
use OCP\Files\File;
use OCP\Files\NotFoundException;

class AncestorCollector {
	/**
	 * Collects only the parent chain. No folder contents are read or scanned.
	 *
	 * @return list<AncestorFolder>
	 */
	public function collect(File $file): array {
		$ancestors = [];
		$seen = [];
		$current = $file->getParent();
		$direct = true;

		while (true) {
			try {
				$storageId = $current->getStorage()->getId();
				$fileId = $current->getId();
			} catch (NotFoundException) {
				break;
			}

			$key = $storageId . "\0" . $fileId;
			if (isset($seen[$key])) {
				break;
			}

			$seen[$key] = true;
			if ($storageId !== '' && $fileId > 0) {
				$ancestors[] = new AncestorFolder($storageId, $fileId, $direct);
			}

			try {
				$next = $current->getParent();
			} catch (NotFoundException) {
				break;
			}

			if ($next === $current) {
				break;
			}

			$current = $next;
			$direct = false;
		}

		return $ancestors;
	}
}
