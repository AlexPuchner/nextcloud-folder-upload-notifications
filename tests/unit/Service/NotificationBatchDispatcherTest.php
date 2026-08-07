<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCA\FolderUploadNotifications\Service\EmailPublisher;
use OCA\FolderUploadNotifications\Service\NotificationBatchDispatcher;
use OCA\FolderUploadNotifications\Service\NotificationPublisher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationBatchDispatcherTest extends TestCase {
	public function testDispatchesMultipleFilesOncePerEnabledChannel(): void {
		$batch = $this->batch(50);
		$push = $this->createMock(NotificationPublisher::class);
		$push->expects(self::once())->method('publishBatch')->with($batch);
		$email = $this->createMock(EmailPublisher::class);
		$email->expects(self::once())->method('publishBatch')->with($batch);

		$dispatcher = new NotificationBatchDispatcher(
			$this->createMock(IRootFolder::class),
			$push,
			$email,
			$this->createMock(LoggerInterface::class),
		);
		$dispatcher->dispatchPush($batch);
		$dispatcher->dispatchEmail($batch);
	}

	public function testSingleFileKeepsExistingPublishersAndContent(): void {
		$batch = $this->batch(1);
		$file = $this->createMock(File::class);
		$file->method('isReadable')->willReturn(true);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(99)->willReturn([$file]);
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alex')->willReturn($userFolder);
		$push = $this->createMock(NotificationPublisher::class);
		$push->expects(self::once())
			->method('publish')
			->with($file, ['alex'], 'sabi');
		$push->expects(self::never())->method('publishBatch');
		$email = $this->createMock(EmailPublisher::class);
		$email->expects(self::once())
			->method('publish')
			->with($file, ['alex'], 'sabi');

		$dispatcher = new NotificationBatchDispatcher(
			$rootFolder,
			$push,
			$email,
			$this->createMock(LoggerInterface::class),
		);
		$dispatcher->dispatchPush($batch);
		$dispatcher->dispatchEmail($batch);
	}

	public function testDeliveryFailureIsPropagatedForRetry(): void {
		$batch = $this->batch(50);
		$push = $this->createMock(NotificationPublisher::class);
		$push->method('publishBatch')->willThrowException(new \RuntimeException('Push unavailable'));
		$dispatcher = new NotificationBatchDispatcher(
			$this->createMock(IRootFolder::class),
			$push,
			$this->createMock(EmailPublisher::class),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(\RuntimeException::class);
		$dispatcher->dispatchPush($batch);
	}

	private function batch(int $fileCount): NotificationBatch {
		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setRecipientUserId('alex');
		$batch->setActorUserId('sabi');
		$batch->setFolderFileId(42);
		$batch->setLastFileId(99);
		$batch->setFileCount($fileCount);
		$batch->setNotifyPush(true);
		$batch->setNotifyEmail(true);

		return $batch;
	}
}
