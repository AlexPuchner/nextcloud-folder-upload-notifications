<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Db\SubscriptionMapper;
use OCA\FolderUploadNotifications\Dto\ResolvedFolder;
use OCA\FolderUploadNotifications\Exception\SubscriptionNotFoundException;
use OCA\FolderUploadNotifications\Service\FolderResolver;
use OCA\FolderUploadNotifications\Service\SubscriptionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

final class SubscriptionServiceTest extends TestCase {
	public function testCreatesSubscriptionForResolvedFolder(): void {
		$mapper = $this->createMock(SubscriptionMapper::class);
		$resolver = $this->createMock(FolderResolver::class);
		$clock = $this->createMock(ITimeFactory::class);

		$resolver->expects(self::once())
			->method('resolveAccessibleFolder')
			->with('alice', 42)
			->willReturn(new ResolvedFolder('home::alice', 42, '/Poster'));
		$mapper->expects(self::once())
			->method('findByFolderForUser')
			->with('alice', 'home::alice', 42)
			->willReturn(null);
		$clock->method('getTime')->willReturn(1000);
		$mapper->expects(self::once())
			->method('insert')
			->willReturnCallback(static function (Subscription $subscription): Subscription {
				$subscription->setId(7);

				return $subscription;
			});

		$result = (new SubscriptionService($mapper, $resolver, $clock))->createForUser(
			'alice',
			42,
			true,
			false,
			true,
			false,
		);

		self::assertSame(7, $result->getId());
		self::assertSame('alice', $result->getUserId());
		self::assertSame('home::alice', $result->getStorageId());
		self::assertSame('/Poster', $result->getDisplayPath());
		self::assertTrue($result->getNotifyPush());
		self::assertFalse($result->getNotifyEmail());
		self::assertSame(1000, $result->getCreatedAt());
		self::assertSame(1000, $result->getUpdatedAt());
	}

	public function testUpdatesExistingSubscriptionIdempotently(): void {
		$existing = $this->subscription(7, 'alice');
		$mapper = $this->createMock(SubscriptionMapper::class);
		$resolver = $this->createMock(FolderResolver::class);
		$clock = $this->createMock(ITimeFactory::class);

		$resolver->method('resolveAccessibleFolder')
			->willReturn(new ResolvedFolder('home::alice', 42, '/Renamed'));
		$mapper->method('findByFolderForUser')->willReturn($existing);
		$clock->method('getTime')->willReturn(1200);
		$mapper->expects(self::never())->method('insert');
		$mapper->expects(self::once())
			->method('update')
			->with($existing)
			->willReturn($existing);

		$result = (new SubscriptionService($mapper, $resolver, $clock))->createForUser(
			'alice',
			42,
			false,
			true,
			false,
			true,
		);

		self::assertSame(7, $result->getId());
		self::assertSame('/Renamed', $result->getDisplayPath());
		self::assertFalse($result->getRecursive());
		self::assertTrue($result->getNotifyOwnUploads());
		self::assertFalse($result->getNotifyPush());
		self::assertTrue($result->getNotifyEmail());
		self::assertSame(900, $result->getCreatedAt());
		self::assertSame(1200, $result->getUpdatedAt());
	}

	public function testForeignSubscriptionIsReportedAsNotFound(): void {
		$mapper = $this->createMock(SubscriptionMapper::class);
		$resolver = $this->createMock(FolderResolver::class);
		$clock = $this->createMock(ITimeFactory::class);

		$mapper->expects(self::once())
			->method('findForUser')
			->with(99, 'alice')
			->willThrowException(new DoesNotExistException('not found'));
		$mapper->expects(self::never())->method('update');

		$this->expectException(SubscriptionNotFoundException::class);
		(new SubscriptionService($mapper, $resolver, $clock))->updateForUser(
			'alice',
			99,
			true,
			false,
			true,
			false,
		);
	}

	private function subscription(int $id, string $userId): Subscription {
		$subscription = new Subscription();
		$subscription->setId($id);
		$subscription->setUserId($userId);
		$subscription->setStorageId('home::alice');
		$subscription->setFolderFileId(42);
		$subscription->setDisplayPath('/Poster');
		$subscription->setRecursive(true);
		$subscription->setNotifyOwnUploads(false);
		$subscription->setNotifyPush(true);
		$subscription->setNotifyEmail(false);
		$subscription->setCreatedAt(900);
		$subscription->setUpdatedAt(900);
		$subscription->resetUpdatedFields();

		return $subscription;
	}
}
