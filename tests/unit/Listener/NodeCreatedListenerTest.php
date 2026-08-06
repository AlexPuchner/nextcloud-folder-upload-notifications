<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Listener;

use OCA\FolderUploadNotifications\Listener\NodeCreatedListener;
use OCA\FolderUploadNotifications\Service\CreatedFileHandler;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\TestCase;

final class NodeCreatedListenerTest extends TestCase {
	public function testDelegatesCreatedFiles(): void {
		$file = $this->createMock(File::class);
		$handler = $this->createMock(CreatedFileHandler::class);
		$handler->expects(self::once())
			->method('handle')
			->with($file);

		$listener = new NodeCreatedListener($handler);
		$listener->handle(new NodeCreatedEvent($file));
	}

	public function testIgnoresCreatedFolders(): void {
		$folder = $this->createMock(Folder::class);
		$handler = $this->createMock(CreatedFileHandler::class);
		$handler->expects(self::never())
			->method('handle');

		$listener = new NodeCreatedListener($handler);
		$listener->handle(new NodeCreatedEvent($folder));
	}
}
