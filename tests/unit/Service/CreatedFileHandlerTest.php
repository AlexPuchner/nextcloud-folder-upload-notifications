<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Db\SubscriptionMapper;
use OCA\FolderUploadNotifications\Dto\AncestorFolder;
use OCA\FolderUploadNotifications\Service\ActorResolver;
use OCA\FolderUploadNotifications\Service\AncestorCollector;
use OCA\FolderUploadNotifications\Service\CreatedFileHandler;
use OCA\FolderUploadNotifications\Service\FileAccessUserResolver;
use OCA\FolderUploadNotifications\Service\NotificationBatchQueue;
use OCA\FolderUploadNotifications\Service\SubscriptionAccessValidator;
use OCA\FolderUploadNotifications\Service\SubscriptionPathMatcher;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CreatedFileHandlerTest extends TestCase {
	public function testFiltersOwnUploadsAndCombinesChannelsBeforeEnqueueing(): void {
		$file = $this->createMock(File::class);
		$ancestors = [new AncestorFolder('home::alice', 42, true)];
		$own = $this->subscription('alice', false, true, true);
		$bobPush = $this->subscription('bob', false, true, false);
		$bobEmail = $this->subscription('bob', false, false, true);

		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')->with($file)->willReturn($ancestors);
		$mapper = $this->createMock(SubscriptionMapper::class);
		$mapper->method('findMatchingForAncestors')
			->with($ancestors)
			->willReturn([$own, $bobPush, $bobEmail]);
		$actor = $this->createMock(ActorResolver::class);
		$actor->method('resolveUserId')->willReturn('alice');
		$validator = $this->createMock(SubscriptionAccessValidator::class);
		$validator->expects(self::exactly(2))->method('canReceive')->willReturn(true);
		$batchQueue = $this->createMock(NotificationBatchQueue::class);
		$batchQueue->expects(self::once())
			->method('enqueue')
			->with($file, ['bob' => ['push' => true, 'email' => true]], 'alice');

		$this->handler($collector, $mapper, $actor, $validator, $batchQueue)->handle($file);
	}

	public function testOwnUploadCanBeEnabled(): void {
		$file = $this->createMock(File::class);
		$subscription = $this->subscription('alice', true, true, false);
		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')
			->willReturn([new AncestorFolder('home::alice', 42, true)]);
		$mapper = $this->createMock(SubscriptionMapper::class);
		$mapper->method('findMatchingForAncestors')->willReturn([$subscription]);
		$actor = $this->createMock(ActorResolver::class);
		$actor->method('resolveUserId')->willReturn('alice');
		$validator = $this->createMock(SubscriptionAccessValidator::class);
		$validator->method('canReceive')->willReturn(true);
		$batchQueue = $this->createMock(NotificationBatchQueue::class);
		$batchQueue->expects(self::once())
			->method('enqueue')
			->with($file, ['alice' => ['push' => true, 'email' => false]], 'alice');

		$this->handler($collector, $mapper, $actor, $validator, $batchQueue)->handle($file);
	}

	public function testMatchesSharedFileThroughSubscribersVirtualParent(): void {
		$file = $this->createMock(File::class);
		$subscription = $this->subscription('alex', false, true, false);
		$subscription->setId(77);

		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')
			->willReturn([new AncestorFolder('home::sabi', 100, true)]);
		$mapper = $this->createMock(SubscriptionMapper::class);
		$mapper->method('findMatchingForAncestors')->willReturn([]);
		$mapper->expects(self::once())
			->method('findAllForUsers')
			->with(['alex'])
			->willReturn([$subscription]);
		$actor = $this->createMock(ActorResolver::class);
		$actor->method('resolveUserId')->willReturn('sabi');
		$fileAccessUsers = $this->createMock(FileAccessUserResolver::class);
		$fileAccessUsers->method('resolve')->with($file)->willReturn(['alex']);
		$pathMatcher = $this->createMock(SubscriptionPathMatcher::class);
		$pathMatcher->expects(self::once())
			->method('matches')
			->with($subscription, $file)
			->willReturn(true);
		$validator = $this->createMock(SubscriptionAccessValidator::class);
		$validator->method('canReceive')->with($subscription)->willReturn(true);
		$batchQueue = $this->createMock(NotificationBatchQueue::class);
		$batchQueue->expects(self::once())
			->method('enqueue')
			->with($file, ['alex' => ['push' => true, 'email' => false]], 'sabi');

		$this->handler(
			$collector,
			$mapper,
			$actor,
			$validator,
			$batchQueue,
			null,
			$fileAccessUsers,
			$pathMatcher,
		)->handle($file);
	}

	public function testFailureIsLoggedAndDoesNotEscapeListener(): void {
		$file = $this->createMock(File::class);
		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')->willThrowException(new \RuntimeException('broken'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('error');

		$this->handler(
			$collector,
			$this->createMock(SubscriptionMapper::class),
			$this->createMock(ActorResolver::class),
			$this->createMock(SubscriptionAccessValidator::class),
			$this->createMock(NotificationBatchQueue::class),
			$logger,
		)->handle($file);

		self::assertTrue(true);
	}

	private function handler(
		AncestorCollector $collector,
		SubscriptionMapper $mapper,
		ActorResolver $actor,
		SubscriptionAccessValidator $validator,
		NotificationBatchQueue $batchQueue,
		?LoggerInterface $logger = null,
		?FileAccessUserResolver $fileAccessUserResolver = null,
		?SubscriptionPathMatcher $subscriptionPathMatcher = null,
	): CreatedFileHandler {
		return new CreatedFileHandler(
			$collector,
			$mapper,
			$actor,
			$fileAccessUserResolver ?? $this->createMock(FileAccessUserResolver::class),
			$subscriptionPathMatcher ?? $this->createMock(SubscriptionPathMatcher::class),
			$validator,
			$batchQueue,
			$logger ?? $this->createMock(LoggerInterface::class),
		);
	}

	private function subscription(
		string $userId,
		bool $notifyOwnUploads,
		bool $notifyPush,
		bool $notifyEmail,
	): Subscription {
		$subscription = new Subscription();
		$subscription->setUserId($userId);
		$subscription->setNotifyOwnUploads($notifyOwnUploads);
		$subscription->setNotifyPush($notifyPush);
		$subscription->setNotifyEmail($notifyEmail);

		return $subscription;
	}
}
