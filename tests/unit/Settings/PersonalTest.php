<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Settings;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Settings\Personal;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

final class PersonalTest extends TestCase {
	public function testRendersPersonalSettingsTemplate(): void {
		$settings = new Personal();
		$response = $settings->getForm();

		self::assertInstanceOf(TemplateResponse::class, $response);
		self::assertSame(Application::APP_ID, $response->getApp());
		self::assertSame('settings/personal', $response->getTemplateName());
		self::assertSame(Application::APP_ID, $settings->getSection());
		self::assertSame(10, $settings->getPriority());
	}
}
