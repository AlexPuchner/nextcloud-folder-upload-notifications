<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCP\Files\File;

/**
 * M1 integration seam. Subscription matching is implemented in M3.
 */
class CreatedFileHandler {
	public function handle(File $file): void {
	}
}
