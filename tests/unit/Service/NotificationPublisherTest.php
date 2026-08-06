<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Notification\Notifier;
use OCA\FolderUploadNotifications\Service\NotificationPublisher;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

final class NotificationPublisherTest extends TestCase {
	public function testPublishesOneNotificationPerRecipientAndFlushes(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(99);
		$notification = $this->createMock(INotification::class);
		$notification->method('setDateTime')->willReturnSelf();
		$notification->expects(self::once())
			->method('setApp')
			->with(Application::APP_ID)
			->willReturnSelf();
		$notification->expects(self::once())
			->method('setUser')
			->with('bob')
			->willReturnSelf();
		$notification->expects(self::once())
			->method('setObject')
			->with('file', '99')
			->willReturnSelf();
		$notification->expects(self::once())
			->method('setSubject')
			->with(Notifier::SUBJECT_FILE_CREATED, ['actorUserId' => 'alice'])
			->willReturnSelf();

		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())->method('defer')->willReturn(true);
		$manager->expects(self::once())->method('createNotification')->willReturn($notification);
		$manager->expects(self::once())->method('notify')->with($notification);
		$manager->expects(self::once())->method('flush');
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getDateTime')->willReturn(new \DateTime('2026-08-06 20:00:00'));

		(new NotificationPublisher($manager, $clock))->publish($file, ['bob'], 'alice');
	}
}
