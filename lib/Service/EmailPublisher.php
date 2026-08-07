<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

class EmailPublisher {
	public function __construct(
		private readonly IMailer $mailer,
		private readonly IUserManager $userManager,
		private readonly IRootFolder $rootFolder,
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<string> $recipientUserIds
	 */
	public function publish(File $file, array $recipientUserIds, ?string $actorUserId): void {
		foreach ($recipientUserIds as $recipientUserId) {
			$this->publishForUser($file, $recipientUserId, $actorUserId);
		}
	}

	public function publishBatch(NotificationBatch $batch): void {
		$this->publishBatchForUser($batch);
	}

	private function publishForUser(
		File $file,
		string $recipientUserId,
		?string $actorUserId,
	): void {
		$recipient = $this->userManager->get($recipientUserId);
		$email = $recipient?->getEMailAddress();
		if (!$recipient instanceof IUser || $email === null || $email === '') {
			$this->logger->warning('Folder upload email skipped because the user has no email address', [
				'app' => Application::APP_ID,
				'userId' => $recipientUserId,
			]);

			return;
		}

		[$visibleFile, $displayPath] = $this->resolveAccessibleFile($recipientUserId, $file);
		$languageCode = $this->l10nFactory->getUserLanguage($recipient);
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$actor = $actorUserId !== null && $actorUserId !== ''
			? $this->userManager->get($actorUserId)
			: null;
		$fileName = $visibleFile->getName();
		$link = $this->urlGenerator->linkToRouteAbsolute(
			'files.viewcontroller.showFile',
			['fileid' => $visibleFile->getId()],
		);

		if ($actor instanceof IUser) {
			$subject = $l->t(
				'%1$s uploaded %2$s',
				[$actor->getDisplayName(), $fileName],
			);
		} else {
			$subject = $l->t('A new file was uploaded: %s', [$fileName]);
		}

		$body = [
			$l->t('A new file was uploaded to a folder you monitor.'),
			'',
			$l->t('File: %s', [$fileName]),
			$l->t('Path: %s', [$displayPath]),
		];
		if ($actor instanceof IUser) {
			$body[] = $l->t('Uploaded by: %s', [$actor->getDisplayName()]);
		}
		$body[] = '';
		$body[] = $l->t('Open file: %s', [$link]);

		$message = $this->mailer->createMessage();
		$message
			->setTo([$email => $recipient->getDisplayName()])
			->setSubject($subject)
			->setPlainBody(implode("\n", $body));
		$this->mailer->send($message);
	}

	private function publishBatchForUser(NotificationBatch $batch): void {
		$recipientUserId = $batch->getRecipientUserId();
		$recipient = $this->userManager->get($recipientUserId);
		$email = $recipient?->getEMailAddress();
		if (!$recipient instanceof IUser || $email === null || $email === '') {
			$this->logger->warning('Folder upload batch email skipped because the user has no email address', [
				'app' => Application::APP_ID,
				'userId' => $recipientUserId,
			]);

			return;
		}

		[$folder, $displayPath] = $this->resolveAccessibleFolder(
			$recipientUserId,
			$batch->getFolderFileId(),
		);
		$languageCode = $this->l10nFactory->getUserLanguage($recipient);
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$actor = $batch->getActorUserId() !== ''
			? $this->userManager->get($batch->getActorUserId())
			: null;
		$fileCount = $batch->getFileCount();
		$link = $this->urlGenerator->linkToRouteAbsolute(
			'files.viewcontroller.showFile',
			['fileid' => $folder->getId()],
		);

		if ($actor instanceof IUser) {
			$subject = $l->t(
				'%1$s uploaded %2$d files to %3$s',
				[$actor->getDisplayName(), $fileCount, $folder->getName()],
			);
		} else {
			$subject = $l->t(
				'%1$d new files were uploaded to %2$s',
				[$fileCount, $folder->getName()],
			);
		}

		$body = [
			$l->t('New files were uploaded to a folder you monitor.'),
			'',
			$l->t('Number of files: %d', [$fileCount]),
			$l->t('Folder: %s', [$folder->getName()]),
			$l->t('Path: %s', [$displayPath]),
		];
		if ($actor instanceof IUser) {
			$body[] = $l->t('Uploaded by: %s', [$actor->getDisplayName()]);
		}
		$body[] = '';
		$body[] = $l->t('Open folder: %s', [$link]);

		$message = $this->mailer->createMessage();
		$message
			->setTo([$email => $recipient->getDisplayName()])
			->setSubject($subject)
			->setPlainBody(implode("\n", $body));
		$this->mailer->send($message);
	}

	/**
	 * @return array{File, string}
	 */
	private function resolveAccessibleFile(string $userId, File $file): array {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		foreach ($userFolder->getById($file->getId()) as $node) {
			if (!$node instanceof File || !$node->isReadable()) {
				continue;
			}

			$relativePath = $userFolder->getRelativePath($node->getPath());
			if ($relativePath === null) {
				continue;
			}

			return [$node, '/' . ltrim($relativePath, '/')];
		}

		throw new \RuntimeException('Uploaded file is not accessible to the email recipient');
	}

	/**
	 * @return array{Folder, string}
	 */
	private function resolveAccessibleFolder(string $userId, int $folderFileId): array {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		foreach ($userFolder->getById($folderFileId) as $node) {
			if (!$node instanceof Folder || !$node->isReadable()) {
				continue;
			}

			$relativePath = $userFolder->getRelativePath($node->getPath());
			if ($relativePath === null) {
				continue;
			}

			return [$node, '/' . ltrim($relativePath, '/')];
		}

		throw new \RuntimeException('Upload folder is not accessible to the email recipient');
	}
}
