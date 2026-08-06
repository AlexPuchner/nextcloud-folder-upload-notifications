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

/** @extends QBMapper<Subscription> */
class SubscriptionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'folder_upload_subs', Subscription::class);
	}

	/**
	 * @return list<Subscription>
	 */
	public function findAllForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'user_id',
				$qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
			))
			->orderBy('display_path', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findForUser(int $id, string $userId): Subscription {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			))
			->andWhere($qb->expr()->eq(
				'user_id',
				$qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
			));

		return $this->findEntity($qb);
	}

	public function findByFolderForUser(
		string $userId,
		string $storageId,
		int $folderFileId,
	): ?Subscription {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'user_id',
				$qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
			))
			->andWhere($qb->expr()->eq(
				'storage_id',
				$qb->createNamedParameter($storageId, IQueryBuilder::PARAM_STR),
			))
			->andWhere($qb->expr()->eq(
				'folder_file_id',
				$qb->createNamedParameter($folderFileId, IQueryBuilder::PARAM_INT),
			));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
