<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Service\ActorResolver;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ActorResolverTest extends TestCase {
	public function testReturnsCurrentUserId(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		self::assertSame('alice', (new ActorResolver($session))->resolveUserId());
	}

	public function testReturnsNullWithoutAuthenticatedUser(): void {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn(null);

		self::assertNull((new ActorResolver($session))->resolveUserId());
	}
}
