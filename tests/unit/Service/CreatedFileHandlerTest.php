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
use OCA\FolderUploadNotifications\Service\EmailPublisher;
use OCA\FolderUploadNotifications\Service\FileAccessUserResolver;
use OCA\FolderUploadNotifications\Service\NotificationPublisher;
use OCA\FolderUploadNotifications\Service\SubscriptionAccessValidator;
use OCA\FolderUploadNotifications\Service\SubscriptionPathMatcher;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CreatedFileHandlerTest extends TestCase {
	public function testFiltersOwnUploadsAndDeduplicatesRecipients(): void {
		$file = $this->createMock(File::class);
		$ancestors = [new AncestorFolder('home::alice', 42, true)];
		$own = $this->subscription('alice', false);
		$bobDirect = $this->subscription('bob', false);
		$bobRecursive = $this->subscription('bob', false);

		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')->with($file)->willReturn($ancestors);
		$mapper = $this->createMock(SubscriptionMapper::class);
		$mapper->method('findMatchingForAncestors')
			->with($ancestors)
			->willReturn([$own, $bobDirect, $bobRecursive]);
		$actor = $this->createMock(ActorResolver::class);
		$actor->method('resolveUserId')->willReturn('alice');
		$validator = $this->createMock(SubscriptionAccessValidator::class);
		$validator->expects(self::exactly(2))->method('canReceive')->willReturn(true);
		$publisher = $this->createMock(NotificationPublisher::class);
		$publisher->expects(self::once())
			->method('publish')
			->with($file, ['bob'], 'alice');
		$emailPublisher = $this->createMock(EmailPublisher::class);
		$emailPublisher->expects(self::once())
			->method('publish')
			->with($file, [], 'alice');

		$this->handler(
			$collector,
			$mapper,
			$actor,
			$validator,
			$publisher,
			null,
			null,
			null,
			$emailPublisher,
		)->handle($file);
	}

	public function testOwnUploadCanBeEnabled(): void {
		$file = $this->createMock(File::class);
		$subscription = $this->subscription('alice', true);
		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')
			->willReturn([new AncestorFolder('home::alice', 42, true)]);
		$mapper = $this->createMock(SubscriptionMapper::class);
		$mapper->method('findMatchingForAncestors')->willReturn([$subscription]);
		$actor = $this->createMock(ActorResolver::class);
		$actor->method('resolveUserId')->willReturn('alice');
		$validator = $this->createMock(SubscriptionAccessValidator::class);
		$validator->method('canReceive')->willReturn(true);
		$publisher = $this->createMock(NotificationPublisher::class);
		$publisher->expects(self::once())
			->method('publish')
			->with($file, ['alice'], 'alice');
		$emailPublisher = $this->createMock(EmailPublisher::class);
		$emailPublisher->expects(self::once())
			->method('publish')
			->with($file, [], 'alice');

		$this->handler(
			$collector,
			$mapper,
			$actor,
			$validator,
			$publisher,
			null,
			null,
			null,
			$emailPublisher,
		)->handle($file);
	}

	public function testMatchesSharedFileThroughSubscribersVirtualParent(): void {
		$file = $this->createMock(File::class);
		$subscription = $this->subscription('alex', false);
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
		$publisher = $this->createMock(NotificationPublisher::class);
		$publisher->expects(self::once())
			->method('publish')
			->with($file, ['alex'], 'sabi');
		$emailPublisher = $this->createMock(EmailPublisher::class);
		$emailPublisher->expects(self::once())
			->method('publish')
			->with($file, [], 'sabi');

		$this->handler(
			$collector,
			$mapper,
			$actor,
			$validator,
			$publisher,
			null,
			$fileAccessUsers,
			$pathMatcher,
			$emailPublisher,
		)->handle($file);
	}

	public function testRoutesAndCombinesDeliveryChannelsPerUser(): void {
		$file = $this->createMock(File::class);
		$pushOnly = $this->subscription('bob', false);
		$emailOnly = $this->subscription('carol', false);
		$emailOnly->setNotifyPush(false);
		$emailOnly->setNotifyEmail(true);
		$bothFromSecondMatch = $this->subscription('bob', false);
		$bothFromSecondMatch->setNotifyPush(false);
		$bothFromSecondMatch->setNotifyEmail(true);

		$collector = $this->createMock(AncestorCollector::class);
		$collector->method('collect')
			->willReturn([new AncestorFolder('home::alice', 42, true)]);
		$mapper = $this->createMock(SubscriptionMapper::class);
		$mapper->method('findMatchingForAncestors')
			->willReturn([$pushOnly, $emailOnly, $bothFromSecondMatch]);
		$actor = $this->createMock(ActorResolver::class);
		$actor->method('resolveUserId')->willReturn('alice');
		$validator = $this->createMock(SubscriptionAccessValidator::class);
		$validator->method('canReceive')->willReturn(true);
		$pushPublisher = $this->createMock(NotificationPublisher::class);
		$pushPublisher->expects(self::once())
			->method('publish')
			->with($file, ['bob'], 'alice');
		$emailPublisher = $this->createMock(EmailPublisher::class);
		$emailPublisher->expects(self::once())
			->method('publish')
			->with($file, ['carol', 'bob'], 'alice');

		$this->handler(
			$collector,
			$mapper,
			$actor,
			$validator,
			$pushPublisher,
			null,
			null,
			null,
			$emailPublisher,
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
			$this->createMock(NotificationPublisher::class),
			$logger,
		)->handle($file);

		self::assertTrue(true);
	}

	private function handler(
		AncestorCollector $collector,
		SubscriptionMapper $mapper,
		ActorResolver $actor,
		SubscriptionAccessValidator $validator,
		NotificationPublisher $publisher,
		?LoggerInterface $logger = null,
		?FileAccessUserResolver $fileAccessUserResolver = null,
		?SubscriptionPathMatcher $subscriptionPathMatcher = null,
		?EmailPublisher $emailPublisher = null,
	): CreatedFileHandler {
		return new CreatedFileHandler(
			$collector,
			$mapper,
			$actor,
			$fileAccessUserResolver ?? $this->createMock(FileAccessUserResolver::class),
			$subscriptionPathMatcher ?? $this->createMock(SubscriptionPathMatcher::class),
			$validator,
			$publisher,
			$emailPublisher ?? $this->createMock(EmailPublisher::class),
			$logger ?? $this->createMock(LoggerInterface::class),
		);
	}

	private function subscription(string $userId, bool $notifyOwnUploads): Subscription {
		$subscription = new Subscription();
		$subscription->setUserId($userId);
		$subscription->setNotifyOwnUploads($notifyOwnUploads);
		$subscription->setNotifyPush(true);
		$subscription->setNotifyEmail(false);

		return $subscription;
	}
}
