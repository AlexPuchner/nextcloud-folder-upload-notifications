<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Controller;

use OCA\FolderUploadNotifications\Controller\SubscriptionController;
use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Exception\SubscriptionNotFoundException;
use OCA\FolderUploadNotifications\Service\SubscriptionService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class SubscriptionControllerTest extends TestCase {
	public function testListsOnlyCurrentUsersSubscriptions(): void {
		$subscription = new Subscription();
		$subscription->setId(7);
		$subscription->setFolderFileId(42);
		$subscription->setDisplayPath('/Poster');
		$subscription->setRecursive(true);
		$subscription->setNotifyOwnUploads(false);
		$subscription->setCreatedAt(1000);
		$subscription->setUpdatedAt(1000);

		$service = $this->createMock(SubscriptionService::class);
		$service->expects(self::once())
			->method('findAllForUser')
			->with('alice')
			->willReturn([$subscription]);

		$response = $this->controller($service, 'alice')->index();

		self::assertSame([7], array_column($response->getData(), 'id'));
	}

	public function testForeignSubscriptionReturnsNotFound(): void {
		$service = $this->createMock(SubscriptionService::class);
		$service->expects(self::once())
			->method('updateForUser')
			->with('alice', 99, true, false)
			->willThrowException(new SubscriptionNotFoundException());

		$this->expectException(OCSNotFoundException::class);
		$this->controller($service, 'alice')->update(99, true, false);
	}

	public function testAnonymousAccessIsRejected(): void {
		$service = $this->createMock(SubscriptionService::class);
		$service->expects(self::never())->method('findAllForUser');

		$this->expectException(OCSForbiddenException::class);
		$this->controller($service, null)->index();
	}

	private function controller(
		SubscriptionService $service,
		?string $userId,
	): SubscriptionController {
		return new SubscriptionController(
			'folder_upload_notifications',
			$this->createMock(IRequest::class),
			$service,
			$userId,
		);
	}
}
