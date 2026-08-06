<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version0003Date20260806233000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('folder_upload_subs')) {
			return null;
		}

		$table = $schema->getTable('folder_upload_subs');
		$changed = false;

		if (!$table->hasColumn('notify_push')) {
			$table->addColumn('notify_push', Types::BOOLEAN, [
				'notnull' => true,
				'default' => true,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('notify_email')) {
			$table->addColumn('notify_email', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
