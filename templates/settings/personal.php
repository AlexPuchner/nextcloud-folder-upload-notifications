<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCP\Util;

Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');
Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');
?>

<div id="folder-upload-notifications-settings"></div>
