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
	table: 'folder_upload_batches',
	description: 'Stores short-lived upload notification batches',
)]
final class Version0004Date20260807000000 extends SimpleMigrationStep {
	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options,
	): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('folder_upload_batches')) {
			return null;
		}

		$table = $schema->createTable('folder_upload_batches');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('group_key', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('recipient_user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('actor_user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
			'default' => '',
		]);
		$table->addColumn('folder_storage_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('folder_file_id', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('last_file_id', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('file_count', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 1,
		]);
		$table->addColumn('notify_push', Types::BOOLEAN, [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('notify_email', Types::BOOLEAN, [
			'notnull' => true,
			'default' => false,
		]);
		$table->addColumn('revision', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 1,
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
		$table->addUniqueIndex(['group_key'], 'fub_group_uniq');
		$table->addIndex(['created_at'], 'fub_created_idx');

		return $schema;
	}
}
