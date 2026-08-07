<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Dto\ResolvedFolder;
use OCA\FolderUploadNotifications\Exception\FolderNotFoundException;
use OCA\FolderUploadNotifications\Service\FolderResolver;
use OCA\FolderUploadNotifications\Service\SubscriptionAccessValidator;
use PHPUnit\Framework\TestCase;

final class SubscriptionAccessValidatorTest extends TestCase {
	public function testAcceptsMatchingAccessibleFolder(): void {
		$resolver = $this->createMock(FolderResolver::class);
		$resolver->method('resolveAccessibleFolder')
			->with('alice', 42, 'home::alice')
			->willReturn(new ResolvedFolder('home::alice', 42, '/Poster'));

		self::assertTrue((new SubscriptionAccessValidator($resolver))->canReceive(
			$this->subscription(),
		));
	}

	public function testRejectsRevokedAccess(): void {
		$resolver = $this->createMock(FolderResolver::class);
		$resolver->method('resolveAccessibleFolder')
			->willThrowException(new FolderNotFoundException());

		self::assertFalse((new SubscriptionAccessValidator($resolver))->canReceive(
			$this->subscription(),
		));
	}

	public function testRejectsStorageMismatch(): void {
		$resolver = $this->createMock(FolderResolver::class);
		$resolver->method('resolveAccessibleFolder')
			->willReturn(new ResolvedFolder('different-storage', 42, '/Poster'));

		self::assertFalse((new SubscriptionAccessValidator($resolver))->canReceive(
			$this->subscription(),
		));
	}

	private function subscription(): Subscription {
		$subscription = new Subscription();
		$subscription->setUserId('alice');
		$subscription->setStorageId('home::alice');
		$subscription->setFolderFileId(42);

		return $subscription;
	}
}
