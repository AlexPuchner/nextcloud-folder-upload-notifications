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
	public function testDispatchesAndClearsEachChannelBeforeDeletingBatch(): void {
		$batch = $this->batch();
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('find')->with(7)->willReturn($batch);
		$mapper->expects(self::once())->method('clearPushIfRevision')->with(7, 3)->willReturn(1);
		$mapper->expects(self::once())->method('clearEmailIfRevision')->with(7, 3)->willReturn(1);
		$mapper->expects(self::once())->method('deleteIfRevision')->with(7, 3)->willReturn(1);
		$dispatcher = $this->createMock(NotificationBatchDispatcher::class);
		$dispatcher->expects(self::once())->method('dispatchPush')->with($batch);
		$dispatcher->expects(self::once())->method('dispatchEmail')->with($batch);

		$this->job($mapper, $dispatcher)->runNow(['batchId' => 7]);
	}

	public function testSuccessfulPushIsNotRepeatedWhenEmailRetryIsNeeded(): void {
		$batch = $this->batch();
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('find')->with(7)->willReturn($batch);
		$mapper->expects(self::once())->method('clearPushIfRevision')->with(7, 3)->willReturn(1);
		$mapper->expects(self::once())->method('clearEmailIfRevision')->with(7, 3)->willReturn(1);
		$mapper->expects(self::once())->method('deleteIfRevision')->with(7, 3)->willReturn(1);
		$dispatcher = $this->createMock(NotificationBatchDispatcher::class);
		$dispatcher->expects(self::once())->method('dispatchPush')->with($batch);
		$emailAttempts = 0;
		$dispatcher->expects(self::exactly(2))
			->method('dispatchEmail')
			->with($batch)
			->willReturnCallback(static function () use (&$emailAttempts): void {
				$emailAttempts++;
				if ($emailAttempts === 1) {
					throw new \RuntimeException('Temporary SMTP failure');
				}
			});
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects(self::once())
			->method('scheduleAfter')
			->with(FlushNotificationBatchJob::class, 1180, ['batchId' => 7]);
		$job = $this->job($mapper, $dispatcher, $jobList);

		$job->runNow(['batchId' => 7]);
		$job->runNow(['batchId' => 7]);
	}

	public function testConcurrentUploadReschedulesWithoutClearingAnotherChannel(): void {
		$batch = $this->batch();
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('find')->with(7)->willReturn($batch);
		$mapper->expects(self::once())->method('clearPushIfRevision')->with(7, 3)->willReturn(0);
		$mapper->expects(self::never())->method('clearEmailIfRevision');
		$mapper->expects(self::never())->method('deleteIfRevision');
		$dispatcher = $this->createMock(NotificationBatchDispatcher::class);
		$dispatcher->expects(self::once())->method('dispatchPush')->with($batch);
		$dispatcher->expects(self::never())->method('dispatchEmail');
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects(self::once())
			->method('scheduleAfter')
			->with(FlushNotificationBatchJob::class, 1125, ['batchId' => 7]);

		$this->job($mapper, $dispatcher, $jobList)->runNow(['batchId' => 7]);
	}

	public function testEarlyExecutionIsRescheduledForWindowEnd(): void {
		$batch = $this->batch();
		$mapper = $this->createMock(NotificationBatchMapper::class);
		$mapper->method('find')->with(7)->willReturn($batch);
		$mapper->expects(self::never())->method('clearPushIfRevision');
		$mapper->expects(self::never())->method('clearEmailIfRevision');
		$mapper->expects(self::never())->method('deleteIfRevision');
		$dispatcher = $this->createMock(NotificationBatchDispatcher::class);
		$dispatcher->expects(self::never())->method('dispatchPush');
		$dispatcher->expects(self::never())->method('dispatchEmail');
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects(self::once())
			->method('scheduleAfter')
			->with(FlushNotificationBatchJob::class, 1120, ['batchId' => 7]);

		$this->job($mapper, $dispatcher, $jobList, 1100)->runNow(['batchId' => 7]);
	}

	private function batch(): NotificationBatch {
		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setRevision(3);
		$batch->setCreatedAt(1000);
		$batch->setNotifyPush(true);
		$batch->setNotifyEmail(true);

		return $batch;
	}

	private function job(
		NotificationBatchMapper $mapper,
		NotificationBatchDispatcher $dispatcher,
		?IJobList $jobList = null,
		int $now = 1120,
	): TestableFlushNotificationBatchJob {
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn($now);

		return new TestableFlushNotificationBatchJob(
			$clock,
			$mapper,
			$dispatcher,
			$jobList ?? $this->createMock(IJobList::class),
			$this->createMock(LoggerInterface::class),
		);
	}
}

final class TestableFlushNotificationBatchJob extends FlushNotificationBatchJob {
	/** @param mixed $argument */
	public function runNow($argument): void {
		parent::run($argument);
	}
}
