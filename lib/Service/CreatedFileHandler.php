<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Db\SubscriptionMapper;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

class CreatedFileHandler {
	public function __construct(
		private readonly AncestorCollector $ancestorCollector,
		private readonly SubscriptionMapper $subscriptionMapper,
		private readonly ActorResolver $actorResolver,
		private readonly FileAccessUserResolver $fileAccessUserResolver,
		private readonly SubscriptionPathMatcher $subscriptionPathMatcher,
		private readonly SubscriptionAccessValidator $accessValidator,
		private readonly NotificationPublisher $notificationPublisher,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(File $file): void {
		try {
			$this->process($file);
		} catch (\Throwable $exception) {
			// A notification failure must never make the upload itself fail.
			$this->logger->error('Failed to process created file notification', [
				'app' => Application::APP_ID,
				'exception' => $exception,
			]);
		}
	}

	private function process(File $file): void {
		$ancestors = $this->ancestorCollector->collect($file);
		if ($ancestors === []) {
			return;
		}

		$actorUserId = $this->actorResolver->resolveUserId();
		$subscriptions = $this->subscriptionMapper->findMatchingForAncestors($ancestors);
		/** @var array<string, Subscription> $subscriptionsById */
		$subscriptionsById = [];
		foreach ($subscriptions as $subscription) {
			$subscriptionsById[$this->subscriptionKey($subscription)] = $subscription;
		}

		$candidateUserIds = $this->fileAccessUserResolver->resolve($file);
		foreach ($this->subscriptionMapper->findAllForUsers($candidateUserIds) as $subscription) {
			$key = $this->subscriptionKey($subscription);
			if (isset($subscriptionsById[$key])) {
				continue;
			}

			if ($this->subscriptionPathMatcher->matches($subscription, $file)) {
				$subscriptionsById[$key] = $subscription;
			}
		}

		if ($subscriptionsById === []) {
			return;
		}

		/** @var array<string, true> $recipients */
		$recipients = [];

		foreach ($subscriptionsById as $subscription) {
			$userId = $subscription->getUserId();

			if ($actorUserId === $userId && !$subscription->getNotifyOwnUploads()) {
				continue;
			}

			if (!$this->accessValidator->canReceive($subscription)) {
				continue;
			}

			$recipients[$userId] = true;
		}

		$this->notificationPublisher->publish(
			$file,
			array_keys($recipients),
			$actorUserId,
		);
	}

	private function subscriptionKey(Subscription $subscription): string {
		$id = $subscription->getId();

		return $id === null
			? 'object:' . spl_object_id($subscription)
			: 'id:' . $id;
	}
}
