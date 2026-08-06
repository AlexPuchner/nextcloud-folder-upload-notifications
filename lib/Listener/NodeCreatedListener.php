<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Listener;

use OCA\FolderUploadNotifications\Service\CreatedFileHandler;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\File;

/** @template-implements IEventListener<NodeCreatedEvent> */
final class NodeCreatedListener implements IEventListener {
	public function __construct(
		private readonly CreatedFileHandler $handler,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeCreatedEvent) {
			return;
		}

		$node = $event->getNode();
		if (!$node instanceof File) {
			return;
		}

		$this->handler->handle($node);
	}
}
