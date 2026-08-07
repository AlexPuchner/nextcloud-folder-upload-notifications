<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Service\AncestorCollector;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\TestCase;

final class AncestorCollectorTest extends TestCase {
	public function testCollectsDirectParentBeforeRecursiveAncestors(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('getId')->willReturn('home::alice');

		$root = $this->createMock(Folder::class);
		$root->method('getStorage')->willReturn($storage);
		$root->method('getId')->willReturn(10);
		$root->method('getParent')->willReturnSelf();

		$directParent = $this->createMock(Folder::class);
		$directParent->method('getStorage')->willReturn($storage);
		$directParent->method('getId')->willReturn(42);
		$directParent->method('getParent')->willReturn($root);

		$file = $this->createMock(File::class);
		$file->method('getParent')->willReturn($directParent);

		$result = (new AncestorCollector())->collect($file);

		self::assertCount(2, $result);
		self::assertSame(42, $result[0]->fileId);
		self::assertTrue($result[0]->direct);
		self::assertSame(10, $result[1]->fileId);
		self::assertFalse($result[1]->direct);
	}
}
