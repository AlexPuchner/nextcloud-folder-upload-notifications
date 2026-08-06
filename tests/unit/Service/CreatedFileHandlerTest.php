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
use OCA\FolderUploadNotifications\Service\NotificationPublisher;
use OCA\FolderUploadNotifications\Service\SubscriptionAccessValidator;
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

		$this->handler($collector, $mapper, $actor, $validator, $publisher)->handle($file);
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

		$this->handler($collector, $mapper, $actor, $validator, $publisher)->handle($file);
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
	): CreatedFileHandler {
		return new CreatedFileHandler(
			$collector,
			$mapper,
			$actor,
			$validator,
			$publisher,
			$logger ?? $this->createMock(LoggerInterface::class),
		);
	}

	private function subscription(string $userId, bool $notifyOwnUploads): Subscription {
		$subscription = new Subscription();
		$subscription->setUserId($userId);
		$subscription->setNotifyOwnUploads($notifyOwnUploads);

		return $subscription;
	}
}
