<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Notification;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public const SUBJECT_FILE_CREATED = 'file_created';

	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IRootFolder $rootFolder,
		private readonly IUserManager $userManager,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory
			->get(Application::APP_ID)
			->t('Folder upload notifications');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID
			|| $notification->getObjectType() !== 'file'
			|| $notification->getSubject() !== self::SUBJECT_FILE_CREATED) {
			throw new UnknownNotificationException('Unhandled notification');
		}

		$fileId = $notification->getObjectId();
		if (!ctype_digit($fileId) || (int)$fileId <= 0) {
			throw new UnknownNotificationException('Invalid file ID');
		}

		[$file, $displayPath] = $this->resolveAccessibleFile(
			$notification->getUser(),
			(int)$fileId,
		);
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$fileParameter = [
			'type' => 'file',
			'id' => $fileId,
			'name' => $file->getName(),
			'path' => $displayPath,
			'link' => $this->urlGenerator->linkToRouteAbsolute(
				'files.viewcontroller.showFile',
				['fileid' => $fileId],
			),
		];
		$parameters = $notification->getSubjectParameters();
		$actorUserId = is_string($parameters['actorUserId'] ?? null)
			? $parameters['actorUserId']
			: '';
		$actor = $actorUserId !== '' ? $this->userManager->get($actorUserId) : null;

		if ($actor instanceof IUser) {
			$notification
				->setParsedSubject($l->t(
					'%1$s uploaded %2$s',
					[$actor->getDisplayName(), $file->getName()],
				))
				->setRichSubject($l->t('{user} uploaded {file}'), [
					'user' => [
						'type' => 'user',
						'id' => $actor->getUID(),
						'name' => $actor->getDisplayName(),
					],
					'file' => $fileParameter,
				]);
		} else {
			$notification
				->setParsedSubject($l->t(
					'A new file was uploaded: %s',
					[$file->getName()],
				))
				->setRichSubject($l->t('A new file was uploaded: {file}'), [
					'file' => $fileParameter,
				]);
		}

		return $notification
			->setLink($fileParameter['link'])
			->setIcon($this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
			));
	}

	/**
	 * @return array{File, string}
	 */
	private function resolveAccessibleFile(string $userId, int $fileId): array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$nodes = $userFolder->getById($fileId);

			foreach ($nodes as $node) {
				if (!$node instanceof File || !$node->isReadable()) {
					continue;
				}

				$relativePath = $userFolder->getRelativePath($node->getPath());
				if ($relativePath === null) {
					continue;
				}

				return [$node, '/' . ltrim($relativePath, '/')];
			}
		} catch (\Throwable) {
			throw new AlreadyProcessedException();
		}

		throw new AlreadyProcessedException();
	}
}
