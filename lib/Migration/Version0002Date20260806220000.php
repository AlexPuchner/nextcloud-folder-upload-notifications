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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[CreateTable(
	table: 'folder_upload_subs',
	description: 'Stores per-user folder notification subscriptions',
)]
final class Version0002Date20260806220000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('folder_upload_subs')) {
			return null;
		}

		$table = $schema->createTable('folder_upload_subs');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('storage_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('folder_file_id', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('display_path', Types::STRING, [
			'notnull' => true,
			'length' => 4000,
		]);
		$table->addColumn('recursive', Types::BOOLEAN, [
			'notnull' => true,
			'default' => true,
		]);
		$table->addColumn('notify_own_uploads', Types::BOOLEAN, [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_at', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(
			['user_id', 'storage_id', 'folder_file_id'],
			'fus_user_folder_uniq',
		);
		$table->addIndex(
			['storage_id', 'folder_file_id', 'recursive'],
			'fus_folder_match_idx',
		);
		$table->addIndex(['user_id'], 'fus_user_idx');

		return $schema;
	}
}
