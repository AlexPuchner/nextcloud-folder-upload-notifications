<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Service;

use OCA\FolderUploadNotifications\Db\NotificationBatch;
use OCA\FolderUploadNotifications\Service\EmailPublisher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EmailPublisherTest extends TestCase {
	public function testSendsLocalizedEmailToConfiguredAddress(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(99);
		$file->method('getName')->willReturn('report.pdf');
		$file->method('getPath')->willReturn('/bob/files/Shared/report.pdf');
		$file->method('isReadable')->willReturn(true);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(99)->willReturn([$file]);
		$userFolder->method('getRelativePath')
			->with('/bob/files/Shared/report.pdf')
			->willReturn('Shared/report.pdf');
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('bob')->willReturn($userFolder);

		$recipient = $this->createMock(IUser::class);
		$recipient->method('getEMailAddress')->willReturn('bob@example.com');
		$recipient->method('getDisplayName')->willReturn('Bob');
		$actor = $this->createMock(IUser::class);
		$actor->method('getDisplayName')->willReturn('Alice');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnMap([
			['bob', $recipient],
			['alice', $actor],
		]);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => $parameters === []
				? $text
				: vsprintf($text, $parameters),
		);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('getUserLanguage')->with($recipient)->willReturn('en');
		$l10nFactory->method('get')->with('folder_upload_notifications', 'en')->willReturn($l10n);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')
			->with('files.viewcontroller.showFile', ['fileid' => 99])
			->willReturn('https://cloud.example/files/99');
		$message = $this->createMock(IMessage::class);
		$message->expects(self::once())
			->method('setTo')
			->with(['bob@example.com' => 'Bob'])
			->willReturnSelf();
		$message->expects(self::once())
			->method('setSubject')
			->with('Alice uploaded report.pdf')
			->willReturnSelf();
		$message->expects(self::once())
			->method('setPlainBody')
			->with(self::callback(static fn (string $body): bool
				=> str_contains($body, 'Path: /Shared/report.pdf')
				&& str_contains($body, 'Open file: https://cloud.example/files/99')
			))
			->willReturnSelf();
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects(self::once())->method('send')->with($message);

		(new EmailPublisher(
			$mailer,
			$userManager,
			$rootFolder,
			$l10nFactory,
			$urlGenerator,
			$this->createMock(LoggerInterface::class),
		))->publish($file, ['bob'], 'alice');
	}

	public function testMissingEmailAddressIsSkipped(): void {
		$recipient = $this->createMock(IUser::class);
		$recipient->method('getEMailAddress')->willReturn(null);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('bob')->willReturn($recipient);
		$mailer = $this->createMock(IMailer::class);
		$mailer->expects(self::never())->method('createMessage');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('warning');

		(new EmailPublisher(
			$mailer,
			$userManager,
			$this->createMock(IRootFolder::class),
			$this->createMock(IFactory::class),
			$this->createMock(IURLGenerator::class),
			$logger,
		))->publish($this->createMock(File::class), ['bob'], null);
	}

	public function testMailerFailureIsPropagatedForBackgroundRetry(): void {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(99);
		$file->method('getName')->willReturn('report.pdf');
		$file->method('getPath')->willReturn('/bob/files/report.pdf');
		$file->method('isReadable')->willReturn(true);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(99)->willReturn([$file]);
		$userFolder->method('getRelativePath')->willReturn('report.pdf');
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('bob')->willReturn($userFolder);
		$recipient = $this->createMock(IUser::class);
		$recipient->method('getEMailAddress')->willReturn('bob@example.com');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('bob')->willReturn($recipient);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);
		$message = $this->createMock(IMessage::class);
		$message->method('setTo')->willReturnSelf();
		$message->method('setSubject')->willReturnSelf();
		$message->method('setPlainBody')->willReturnSelf();
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->method('send')->willThrowException(new \RuntimeException('SMTP unavailable'));
		$publisher = new EmailPublisher(
			$mailer,
			$userManager,
			$rootFolder,
			$l10nFactory,
			$this->createMock(IURLGenerator::class),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(\RuntimeException::class);
		$publisher->publish($file, ['bob'], null);
	}

	public function testSendsOneSummaryEmailForBatch(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(42);
		$folder->method('getName')->willReturn('Photos');
		$folder->method('getPath')->willReturn('/bob/files/Shared/Photos');
		$folder->method('isReadable')->willReturn(true);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(42)->willReturn([$folder]);
		$userFolder->method('getRelativePath')->willReturn('/Shared/Photos');
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('bob')->willReturn($userFolder);

		$recipient = $this->createMock(IUser::class);
		$recipient->method('getEMailAddress')->willReturn('bob@example.com');
		$recipient->method('getDisplayName')->willReturn('Bob');
		$actor = $this->createMock(IUser::class);
		$actor->method('getDisplayName')->willReturn('Alice');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnMap([
			['bob', $recipient],
			['alice', $actor],
		]);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => $parameters === []
				? $text
				: vsprintf($text, $parameters),
		);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('getUserLanguage')->willReturn('en');
		$l10nFactory->method('get')->willReturn($l10n);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')
			->willReturn('https://cloud.example/files/42');
		$message = $this->createMock(IMessage::class);
		$message->method('setTo')->willReturnSelf();
		$message->expects(self::once())
			->method('setSubject')
			->with('Alice uploaded 50 files to Photos')
			->willReturnSelf();
		$message->expects(self::once())
			->method('setPlainBody')
			->with(self::callback(static fn (string $body): bool
				=> str_contains($body, 'Number of files: 50')
					&& str_contains($body, 'Path: /Shared/Photos')
					&& str_contains($body, 'Open folder: https://cloud.example/files/42')
			))
			->willReturnSelf();
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects(self::once())->method('send')->with($message);
		$batch = new NotificationBatch();
		$batch->setId(7);
		$batch->setRecipientUserId('bob');
		$batch->setActorUserId('alice');
		$batch->setFolderFileId(42);
		$batch->setFileCount(50);

		(new EmailPublisher(
			$mailer,
			$userManager,
			$rootFolder,
			$l10nFactory,
			$urlGenerator,
			$this->createMock(LoggerInterface::class),
		))->publishBatch($batch);
	}
}
