<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Db;

use OCA\FolderUploadNotifications\Db\NotificationBatch;
use PHPUnit\Framework\TestCase;

final class NotificationBatchTest extends TestCase {
	public function testUsesTypedBatchFields(): void {
		$batch = new NotificationBatch();
		$batch->setFileCount(50);
		$batch->setNotifyPush(true);
		$batch->setNotifyEmail(false);
		$batch->setRevision(3);

		self::assertSame(50, $batch->getFileCount());
		self::assertTrue($batch->getNotifyPush());
		self::assertFalse($batch->getNotifyEmail());
		self::assertSame(3, $batch->getRevision());
	}
}
