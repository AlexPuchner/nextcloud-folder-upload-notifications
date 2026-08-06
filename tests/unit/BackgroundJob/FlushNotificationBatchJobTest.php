<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\BackgroundJob;

use OCA\FolderUploadNotifications\BackgroundJob\FlushNotificationBatchJob;
use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCA\FolderUploadNotifications\Db\NotificationBatchMapper;
use OCA\FolderUploadNotifications\Service\NotificationBatchDispatcher;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FlushNotificationBatchJobTest extends TestCase {
	public function testClaimsAndDispatchesDueBatch(): void {
		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setRevision(3);
		$batch->setCreatedAt(1000);

		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('find')->with(7)->willReturn($batch);
		$mapper->expects(self::once())
			->method('deleteIfRevision')
			->with(7, 3)
			->willReturn(1);
		$dispatcher = $this->createMock(NotificationBatchDispatcher::class);
		$dispatcher->expects(self::once())->method('dispatch')->with($batch);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(1120);

		$job = new TestableFlushNotificationBatchJob(
			$clock,
			$mapper,
			$dispatcher,
			$this->createMock(IJobList::class),
			$this->createMock(LoggerInterface::class),
		);
		$job->runNow(['batchId' => 7]);
	}

	public function testEarlyExecutionIsRescheduledForWindowEnd(): void {
		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setCreatedAt(1000);
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('find')->with(7)->willReturn($batch);
		$mapper->expects(self::never())->method('deleteIfRevision');
		$dispatcher = $this->createMock(NotificationBatchDispatcher::class);
		$dispatcher->expects(self::never())->method('dispatch');
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects(self::once())
			->method('scheduleAfter')
			->with(FlushNotificationBatchJob::class, 1120, ['batchId' => 7]);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(1100);

		$job = new TestableFlushNotificationBatchJob(
			$clock,
			$mapper,
			$dispatcher,
			$jobList,
			$this->createMock(LoggerInterface::class),
		);
		$job->runNow(['batchId' => 7]);
	}
}

final class TestableFlushNotificationBatchJob extends FlushNotificationBatchJob {
	/** @param mixed $argument */
	public function runNow($argument): void {
		parent::run($argument);
	}
}
