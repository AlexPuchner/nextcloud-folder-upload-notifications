<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Service\SubscriptionPathMatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\TestCase;

final class SubscriptionPathMatcherTest extends TestCase {
	public function testMatchesFileBelowVirtualSharedFolderInSubscribersTree(): void {
		[$matcher, $subscription, $eventFile] = $this->fixture('/Für Alex/test.md');

		self::assertTrue($matcher->matches($subscription, $eventFile));
	}

	public function testHonorsDisabledRecursionForNestedSharedFolder(): void {
		[$matcher, $subscription, $eventFile] = $this->fixture('/Für Alex/test.md');
		$subscription->setRecursive(false);

		self::assertFalse($matcher->matches($subscription, $eventFile));
	}

	public function testMatchesDirectChildWhenRecursionIsDisabled(): void {
		[$matcher, $subscription, $eventFile] = $this->fixture('/test.md');
		$subscription->setRecursive(false);

		self::assertTrue($matcher->matches($subscription, $eventFile));
	}

	/**
	 * @return array{SubscriptionPathMatcher, Subscription, File}
	 */
	private function fixture(string $relativeFilePath): array {
		$storage = $this->createMock(IStorage::class);
		$storage->method('getId')->willReturn('home::alex');

		$subscribedFolder = $this->createMock(Folder::class);
		$subscribedFolder->method('isReadable')->willReturn(true);
		$subscribedFolder->method('getStorage')->willReturn($storage);
		$subscribedFolder->method('getRelativePath')
			->with('/alex/files/Sabrina_shared_folder' . $relativeFilePath)
			->willReturn($relativeFilePath);

		$visibleFile = $this->createMock(File::class);
		$visibleFile->method('isReadable')->willReturn(true);
		$visibleFile->method('getPath')
			->willReturn('/alex/files/Sabrina_shared_folder' . $relativeFilePath);

		$eventFile = $this->createMock(File::class);
		$eventFile->method('getId')->willReturn(1916189);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getId')->willReturn(10);
		$userFolder->method('getPath')->willReturn('/alex/files');

		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alex')->willReturn($userFolder);
		$rootFolder->method('getByIdInPath')
			->willReturnCallback(static fn (int $id, string $path): array => match ($id) {
				803230 => [$subscribedFolder],
				1916189 => [$visibleFile],
				default => [],
			});

		$subscription = new Subscription();
		$subscription->setUserId('alex');
		$subscription->setStorageId('home::alex');
		$subscription->setFolderFileId(803230);
		$subscription->setRecursive(true);

		return [new SubscriptionPathMatcher($rootFolder), $subscription, $eventFile];
	}
}
