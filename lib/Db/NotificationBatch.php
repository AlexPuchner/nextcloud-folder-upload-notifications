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
 * @method string getGroupKey()
 * @method void setGroupKey(string $groupKey)
 * @method string getRecipientUserId()
 * @method void setRecipientUserId(string $recipientUserId)
 * @method string getActorUserId()
 * @method void setActorUserId(string $actorUserId)
 * @method string getFolderStorageId()
 * @method void setFolderStorageId(string $folderStorageId)
 * @method int getFolderFileId()
 * @method void setFolderFileId(int $folderFileId)
 * @method int getLastFileId()
 * @method void setLastFileId(int $lastFileId)
 * @method int getFileCount()
 * @method void setFileCount(int $fileCount)
 * @method bool getNotifyPush()
 * @method void setNotifyPush(bool $notifyPush)
 * @method bool getNotifyEmail()
 * @method void setNotifyEmail(bool $notifyEmail)
 * @method int getRevision()
 * @method void setRevision(int $revision)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class NotificationBatch extends Entity {
	protected string $groupKey = '';
	protected string $recipientUserId = '';
	protected string $actorUserId = '';
	protected string $folderStorageId = '';
	protected int $folderFileId = 0;
	protected int $lastFileId = 0;
	protected int $fileCount = 1;
	protected bool $notifyPush = false;
	protected bool $notifyEmail = false;
	protected int $revision = 1;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('folderFileId', Types::BIGINT);
		$this->addType('lastFileId', Types::BIGINT);
		$this->addType('fileCount', Types::INTEGER);
		$this->addType('notifyPush', Types::BOOLEAN);
		$this->addType('notifyEmail', Types::BOOLEAN);
		$this->addType('revision', Types::INTEGER);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}
}
