<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Db\SubscriptionMapper;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

class CreatedFileHandler {
	public function __construct(
		private readonly AncestorCollector $ancestorCollector,
		private readonly SubscriptionMapper $subscriptionMapper,
		private readonly ActorResolver $actorResolver,
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

		$subscriptions = $this->subscriptionMapper->findMatchingForAncestors($ancestors);
		if ($subscriptions === []) {
			return;
		}

		$actorUserId = $this->actorResolver->resolveUserId();
		/** @var array<string, true> $recipients */
		$recipients = [];

		foreach ($subscriptions as $subscription) {
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
}
