<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Dto;

final class ResolvedFolder {
	public function __construct(
		public readonly string $storageId,
		public readonly int $fileId,
		public readonly string $displayPath,
	) {
	}
}
