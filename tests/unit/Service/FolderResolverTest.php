<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Exception\FolderNotFoundException;
use OCA\FolderUploadNotifications\Service\FolderResolver;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\TestCase;

final class FolderResolverTest extends TestCase {
	public function testResolvesOnlyFolderInsideUsersVisibleTree(): void {
		$storage = $this->createMock(IStorage::class);
		$storage->method('getId')->willReturn('home::alice');

		$target = $this->createMock(Folder::class);
		$target->method('getId')->willReturn(42);
		$target->method('getPath')->willReturn('/alice/files/Poster');
		$target->method('getStorage')->willReturn($storage);
		$target->method('getPermissions')->willReturn(Constants::PERMISSION_READ);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getId')->willReturn(10);
		$userFolder->expects(self::once())
			->method('getById')
			->with(42)
			->willReturn([$target]);
		$userFolder->method('getRelativePath')
			->with('/alice/files/Poster')
			->willReturn('/Poster');

		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->expects(self::once())
			->method('getUserFolder')
			->with('alice')
			->willReturn($userFolder);

		$result = (new FolderResolver($rootFolder))->resolveAccessibleFolder('alice', 42);

		self::assertSame('home::alice', $result->storageId);
		self::assertSame(42, $result->fileId);
		self::assertSame('/Poster', $result->displayPath);
	}

	public function testRejectsIdOutsideUsersVisibleTree(): void {
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getId')->willReturn(10);
		$userFolder->method('getById')->with(99)->willReturn([]);

		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		$this->expectException(FolderNotFoundException::class);
		(new FolderResolver($rootFolder))->resolveAccessibleFolder('alice', 99);
	}
}
