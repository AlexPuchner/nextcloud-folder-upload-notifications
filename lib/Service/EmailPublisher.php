<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Service;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCP\Files\File;
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
			try {
				$this->publishForUser($file, $recipientUserId, $actorUserId);
			} catch (\Throwable $exception) {
				// Email delivery must never make the upload itself fail or block
				// delivery to the remaining recipients.
				$this->logger->error('Failed to send folder upload email', [
					'app' => Application::APP_ID,
					'userId' => $recipientUserId,
					'exception' => $exception,
				]);
			}
		}
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
}
