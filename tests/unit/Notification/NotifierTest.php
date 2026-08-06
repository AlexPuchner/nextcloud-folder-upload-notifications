<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Tests\Unit\Notification;

use OCA\FolderUploadNotifications\AppInfo\Application;
use OCA\FolderUploadNotifications\Notification\Notifier;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

final class NotifierTest extends TestCase {
	public function testPreparesNotificationWithActorAndAccessibleFile(): void {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('track.wav');
		$file->method('getPath')->willReturn('/alice/files/Shared/track.wav');
		$file->method('isReadable')->willReturn(true);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(99)->willReturn([$file]);
		$userFolder->method('getRelativePath')->willReturn('/Shared/track.wav');
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('bob')->willReturn($userFolder);

		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('alice');
		$actor->method('getDisplayName')->willReturn('Alice');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('alice')->willReturn($actor);

		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://cloud.example/f/99');
		$url->method('imagePath')->willReturn('/apps/folder_upload_notifications/img/app.svg');
		$url->method('getAbsoluteURL')->willReturn('https://cloud.example/apps/folder_upload_notifications/img/app.svg');

		$notification = $this->notification();
		$notification->expects(self::once())
			->method('setParsedSubject')
			->with('Alice uploaded track.wav')
			->willReturnSelf();
		$notification->expects(self::once())
			->method('setRichSubject')
			->with('{user} uploaded {file}', self::callback(
				static fn (array $parameters): bool => $parameters['user']['id'] === 'alice'
					&& $parameters['file']['path'] === '/Shared/track.wav',
			))
			->willReturnSelf();
		$notification->expects(self::once())
			->method('setLink')
			->with('https://cloud.example/f/99')
			->willReturnSelf();
		$notification->expects(self::once())
			->method('setIcon')
			->with('https://cloud.example/apps/folder_upload_notifications/img/app.svg')
			->willReturnSelf();

		$result = $this->notifier($rootFolder, $userManager, $url)->prepare($notification, 'en');

		self::assertSame($notification, $result);
	}

	public function testDropsNotificationWhenFileIsNoLongerAccessible(): void {
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->willReturn([]);
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($userFolder);

		$this->expectException(AlreadyProcessedException::class);
		$this->notifier(
			$rootFolder,
			$this->createMock(IUserManager::class),
			$this->createMock(IURLGenerator::class),
		)->prepare($this->notification(), 'en');
	}

	private function notification(): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getObjectType')->willReturn('file');
		$notification->method('getObjectId')->willReturn('99');
		$notification->method('getSubject')->willReturn(Notifier::SUBJECT_FILE_CREATED);
		$notification->method('getSubjectParameters')->willReturn(['actorUserId' => 'alice']);
		$notification->method('getUser')->willReturn('bob');

		return $notification;
	}

	private function notifier(
		IRootFolder $rootFolder,
		IUserManager $userManager,
		IURLGenerator $url,
	): Notifier {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => $parameters === []
				? $text
				: vsprintf($text, $parameters),
		);
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);

		return new Notifier($factory, $rootFolder, $userManager, $url);
	}
}
