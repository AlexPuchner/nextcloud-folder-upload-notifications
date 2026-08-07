<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Service\FileAccessUserResolver;
use OCP\Files\File;
use OCP\Share\IManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FileAccessUserResolverTest extends TestCase {
	public function testReturnsCurrentSharedUsersWithoutDuplicates(): void {
		$file = $this->createMock(File::class);
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('getAccessList')
			->with($file, true, true)
			->willReturn([
				'users' => [
					'alex' => ['node_id' => 42, 'node_path' => '/Für Alex'],
					'sabi' => ['node_id' => 42, 'node_path' => '/Für Alex'],
				],
			]);

		$result = (new FileAccessUserResolver(
			$manager,
			$this->createMock(LoggerInterface::class),
		))->resolve($file);

		self::assertSame(['alex', 'sabi'], $result);
	}

	public function testReturnsNoUsersWhenShareResolutionFails(): void {
		$file = $this->createMock(File::class);
		$manager = $this->createMock(IManager::class);
		$manager->method('getAccessList')->willThrowException(new \RuntimeException('broken'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('warning');

		$result = (new FileAccessUserResolver($manager, $logger))->resolve($file);

		self::assertSame([], $result);
	}
}
