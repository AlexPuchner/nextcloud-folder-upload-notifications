<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int|null getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getStorageId()
 * @method void setStorageId(string $storageId)
 * @method int getFolderFileId()
 * @method void setFolderFileId(int $folderFileId)
 * @method string getDisplayPath()
 * @method void setDisplayPath(string $displayPath)
 * @method bool getRecursive()
 * @method void setRecursive(bool $recursive)
 * @method bool getNotifyOwnUploads()
 * @method void setNotifyOwnUploads(bool $notifyOwnUploads)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Subscription extends Entity {
	protected string $userId = '';
	protected string $storageId = '';
	protected int $folderFileId = 0;
	protected string $displayPath = '';
	protected bool $recursive = true;
	protected bool $notifyOwnUploads = false;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('storageId', Types::STRING);
		$this->addType('folderFileId', Types::BIGINT);
		$this->addType('recursive', Types::BOOLEAN);
		$this->addType('notifyOwnUploads', Types::BOOLEAN);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	/**
	 * @return array{
	 *     id: int|null,
	 *     folderFileId: int,
	 *     displayPath: string,
	 *     recursive: bool,
	 *     notifyOwnUploads: bool,
	 *     createdAt: int,
	 *     updatedAt: int
	 * }
	 */
	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'folderFileId' => $this->getFolderFileId(),
			'displayPath' => $this->getDisplayPath(),
			'recursive' => $this->getRecursive(),
			'notifyOwnUploads' => $this->getNotifyOwnUploads(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
