<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<NotificationBatch> */
class NotificationBatchMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'folder_upload_batches', NotificationBatch::class);
	}

	/**
	 * Atomically increments an existing batch or creates its first row.
	 *
	 * @return array{NotificationBatch, bool} batch and whether it was created
	 */
	public function enqueue(
		string $groupKey,
		string $recipientUserId,
		string $actorUserId,
		string $folderStorageId,
		int $folderFileId,
		int $lastFileId,
		bool $notifyPush,
		bool $notifyEmail,
		int $now,
	): array {
		if ($this->incrementExisting(
			$groupKey,
			$lastFileId,
			$notifyPush,
			$notifyEmail,
			$now,
		) > 0) {
			return [$this->findByGroupKey($groupKey), false];
		}

		$batch = new NotificationBatch();
		$batch->setGroupKey($groupKey);
		$batch->setRecipientUserId($recipientUserId);
		$batch->setActorUserId($actorUserId);
		$batch->setFolderStorageId($folderStorageId);
		$batch->setFolderFileId($folderFileId);
		$batch->setLastFileId($lastFileId);
		$batch->setFileCount(1);
		$batch->setNotifyPush($notifyPush);
		$batch->setNotifyEmail($notifyEmail);
		$batch->setRevision(1);
		$batch->setCreatedAt($now);
		$batch->setUpdatedAt($now);

		try {
			return [$this->insert($batch), true];
		} catch (\Throwable $exception) {
			// A concurrent upload may have inserted the same unique group between
			// the update and insert. In that case increment the winner's row.
			if ($this->incrementExisting(
				$groupKey,
				$lastFileId,
				$notifyPush,
				$notifyEmail,
				$now,
			) === 0) {
				throw $exception;
			}

			return [$this->findByGroupKey($groupKey), false];
		}
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): NotificationBatch {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			));

		return $this->findEntity($qb);
	}

	/**
	 * Deletes a batch only if no upload changed it after it was read.
	 */
	public function deleteIfRevision(int $id, int $revision): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq(
				'id',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			))
			->andWhere($qb->expr()->eq(
				'revision',
				$qb->createNamedParameter($revision, IQueryBuilder::PARAM_INT),
			));

		return $qb->executeStatement();
	}

	private function findByGroupKey(string $groupKey): NotificationBatch {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'group_key',
				$qb->createNamedParameter($groupKey, IQueryBuilder::PARAM_STR),
			));

		return $this->findEntity($qb);
	}

	private function incrementExisting(
		string $groupKey,
		int $lastFileId,
		bool $notifyPush,
		bool $notifyEmail,
		int $now,
	): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set(
				'file_count',
				$qb->createFunction($qb->getColumnName('file_count') . ' + 1'),
			)
			->set(
				'revision',
				$qb->createFunction($qb->getColumnName('revision') . ' + 1'),
			)
			->set(
				'last_file_id',
				$qb->createNamedParameter($lastFileId, IQueryBuilder::PARAM_INT),
			)
			->set(
				'updated_at',
				$qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			)
			->where($qb->expr()->eq(
				'group_key',
				$qb->createNamedParameter($groupKey, IQueryBuilder::PARAM_STR),
			));

		if ($notifyPush) {
			$qb->set(
				'notify_push',
				$qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			);
		}
		if ($notifyEmail) {
			$qb->set(
				'notify_email',
				$qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			);
		}

		return $qb->executeStatement();
	}
}
