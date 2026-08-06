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

		(new NotificationBatchDispatcher(
			$this->createMock(IRootFolder::class),
			$push,
			$email,
			$this->createMock(LoggerInterface::class),
		))->dispatch($batch);
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

		(new NotificationBatchDispatcher(
			$rootFolder,
			$push,
			$email,
			$this->createMock(LoggerInterface::class),
		))->dispatch($batch);
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
