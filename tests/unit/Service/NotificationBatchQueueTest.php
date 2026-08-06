<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\BackgroundJob\FlushNotificationBatchJob;
use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCA\FolderUploadNotifications\Db\NotificationBatchMapper;
use OCA\FolderUploadNotifications\Service\NotificationBatchQueue;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\TestCase;

final class NotificationBatchQueueTest extends TestCase {
	public function testEnqueuesOneGroupAndSchedulesOneDelayedJob(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('getId')->willReturn('home::sabi');
		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(42);
		$folder->method('getStorage')->willReturn($storage);
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(99);
		$file->method('getParent')->willReturn($folder);

		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setCreatedAt(1000);
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->expects(self::once())
			->method('enqueue')
			->with(
				self::callback(static fn (string $key): bool => strlen($key) === 64),
				'alex',
				'sabi',
				'home::sabi',
				42,
				99,
				true,
				true,
				1000,
			)
			->willReturn([$batch, true]);

		$jobList = $this->createMock(IJobList::class);
		$jobList->method('has')
			->with(FlushNotificationBatchJob::class, ['batchId' => 7])
			->willReturn(false);
		$jobList->expects(self::once())
			->method('scheduleAfter')
			->with(FlushNotificationBatchJob::class, 1120, ['batchId' => 7]);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(1000);

		(new NotificationBatchQueue($mapper, $jobList, $clock))->enqueue(
			$file,
			['alex' => ['push' => true, 'email' => true]],
			'sabi',
		);
	}

	public function testExistingScheduledJobIsNotDuplicated(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('getId')->willReturn('home::sabi');
		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(42);
		$folder->method('getStorage')->willReturn($storage);
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(100);
		$file->method('getParent')->willReturn($folder);

		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setCreatedAt(1000);
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('enqueue')->willReturn([$batch, false]);
		$jobList = $this->createMock(IJobList::class);
		$jobList->method('has')->willReturn(true);
		$jobList->expects(self::never())->method('scheduleAfter');
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(1010);

		(new NotificationBatchQueue($mapper, $jobList, $clock))->enqueue(
			$file,
			['alex' => ['push' => true, 'email' => false]],
			'sabi',
		);
	}
}
