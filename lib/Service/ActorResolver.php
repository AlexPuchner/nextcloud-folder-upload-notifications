<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCP\IUser;
use OCP\IUserSession;

class ActorResolver {
	public function __construct(
		private readonly IUserSession $userSession,
	) {
	}

	public function resolveUserId(): ?string {
		$user = $this->userSession->getUser();

		return $user instanceof IUser ? $user->getUID() : null;
	}
}
