/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type { OcsResponse, Subscription } from '../types'

const subscriptionsUrl = generateOcsUrl(
	'/apps/folder_upload_notifications/api/v1/subscriptions',
)

const requestConfig = {
	headers: {
		Accept: 'application/json',
		'OCS-APIRequest': 'true',
	},
}

export async function listSubscriptions(): Promise<Subscription[]> {
	const response = await axios.get<OcsResponse<Subscription[]>>(
		subscriptionsUrl,
		requestConfig,
	)

	return response.data.ocs.data
}

export async function createSubscription(
	folderFileId: number,
): Promise<Subscription> {
	const response = await axios.post<OcsResponse<Subscription>>(
		subscriptionsUrl,
		{
			folderFileId,
			recursive: true,
			notifyOwnUploads: false,
			notifyPush: true,
			notifyEmail: false,
		},
		requestConfig,
	)

	return response.data.ocs.data
}

export async function updateSubscription(
	subscription: Subscription,
): Promise<Subscription> {
	const response = await axios.patch<OcsResponse<Subscription>>(
		`${subscriptionsUrl}/${subscription.id}`,
		{
			recursive: subscription.recursive,
			notifyOwnUploads: subscription.notifyOwnUploads,
			notifyPush: subscription.notifyPush,
			notifyEmail: subscription.notifyEmail,
		},
		requestConfig,
	)

	return response.data.ocs.data
}

export async function deleteSubscription(id: number): Promise<void> {
	await axios.delete(`${subscriptionsUrl}/${id}`, requestConfig)
}
