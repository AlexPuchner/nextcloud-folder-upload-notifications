<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FolderUploadNotifications\Controller;

use OCA\FolderUploadNotifications\Db\Subscription;
use OCA\FolderUploadNotifications\Exception\FolderNotFoundException;
use OCA\FolderUploadNotifications\Exception\SubscriptionNotFoundException;
use OCA\FolderUploadNotifications\Service\SubscriptionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class SubscriptionController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly SubscriptionService $service,
		private readonly ?string $userId = null,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/subscriptions')]
	public function index(): DataResponse {
		$subscriptions = array_map(
			static fn (Subscription $subscription): array => $subscription->toArray(),
			$this->service->findAllForUser($this->requireUserId()),
		);

		return new DataResponse($subscriptions);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/subscriptions')]
	public function create(
		int $folderFileId,
		bool $recursive = true,
		bool $notifyOwnUploads = false,
	): DataResponse {
		try {
			$subscription = $this->service->createForUser(
				$this->requireUserId(),
				$folderFileId,
				$recursive,
				$notifyOwnUploads,
			);
		} catch (FolderNotFoundException) {
			throw new OCSNotFoundException('Folder not found or not accessible');
		} catch (\InvalidArgumentException) {
			throw new OCSBadRequestException('Folder file ID must be positive');
		}

		return new DataResponse($subscription->toArray(), Http::STATUS_OK);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/subscriptions/{id}')]
	public function update(
		int $id,
		bool $recursive,
		bool $notifyOwnUploads,
	): DataResponse {
		try {
			$subscription = $this->service->updateForUser(
				$this->requireUserId(),
				$id,
				$recursive,
				$notifyOwnUploads,
			);
		} catch (SubscriptionNotFoundException) {
			throw new OCSNotFoundException('Subscription not found');
		}

		return new DataResponse($subscription->toArray());
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/subscriptions/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$this->service->deleteForUser($this->requireUserId(), $id);
		} catch (SubscriptionNotFoundException) {
			throw new OCSNotFoundException('Subscription not found');
		}

		return new DataResponse([]);
	}

	private function requireUserId(): string {
		if ($this->userId === null || $this->userId === '') {
			throw new OCSForbiddenException('Authentication required');
		}

		return $this->userId;
	}
}
