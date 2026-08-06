<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Db;

use OCA\FolderUploadNotifications\Dto\AncestorFolder;
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

	/**
	 * @param list<AncestorFolder> $ancestors
	 * @return list<Subscription>
	 */
	public function findMatchingForAncestors(array $ancestors): array {
		if ($ancestors === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$conditions = [];

		foreach ($ancestors as $ancestor) {
			$parts = [
				$qb->expr()->eq(
					'storage_id',
					$qb->createNamedParameter($ancestor->storageId, IQueryBuilder::PARAM_STR),
				),
				$qb->expr()->eq(
					'folder_file_id',
					$qb->createNamedParameter($ancestor->fileId, IQueryBuilder::PARAM_INT),
				),
			];

			if (!$ancestor->direct) {
				$parts[] = $qb->expr()->eq(
					'recursive',
					$qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
				);
			}

			$conditions[] = $qb->expr()->andX(...$parts);
		}

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->orX(...$conditions));

		return $this->findEntities($qb);
	}
}
