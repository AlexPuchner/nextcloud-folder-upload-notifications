<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Db;

use OCA\FolderUploadNotifications\Db\Subscription;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase {
	public function testSerializesOnlyUserFacingFields(): void {
		$subscription = new Subscription();
		$subscription->setId(7);
		$subscription->setUserId('alice');
		$subscription->setStorageId('home::alice');
		$subscription->setFolderFileId(42);
		$subscription->setDisplayPath('/Poster');
		$subscription->setRecursive(true);
		$subscription->setNotifyOwnUploads(false);
		$subscription->setNotifyPush(true);
		$subscription->setNotifyEmail(false);
		$subscription->setCreatedAt(1000);
		$subscription->setUpdatedAt(1100);

		self::assertSame([
			'id' => 7,
			'folderFileId' => 42,
			'displayPath' => '/Poster',
			'recursive' => true,
			'notifyOwnUploads' => false,
			'notifyPush' => true,
			'notifyEmail' => false,
			'createdAt' => 1000,
			'updatedAt' => 1100,
		], $subscription->toArray());
		self::assertArrayNotHasKey('userId', $subscription->toArray());
		self::assertArrayNotHasKey('storageId', $subscription->toArray());
	}
}
