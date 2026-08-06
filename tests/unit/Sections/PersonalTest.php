<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Sections;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Sections\Personal;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class PersonalTest extends TestCase {
	public function testExposesPersonalSettingsSection(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects(self::once())
			->method('t')
			->with('Folder upload notifications')
			->willReturn('Folder upload notifications');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects(self::once())
			->method('imagePath')
			->with(Application::APP_ID, 'app.svg')
			->willReturn('/apps/folder_upload_notifications/img/app.svg');

		$section = new Personal($l10n, $urlGenerator);

		self::assertSame(Application::APP_ID, $section->getID());
		self::assertSame('Folder upload notifications', $section->getName());
		self::assertSame('/apps/folder_upload_notifications/img/app.svg', $section->getIcon());
		self::assertSame(55, $section->getPriority());
	}
}
