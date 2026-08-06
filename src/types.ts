/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface Subscription {
	id: number
	folderFileId: number
	displayPath: string
	recursive: boolean
	notifyOwnUploads: boolean
	createdAt: number
	updatedAt: number
}

export interface OcsResponse<T> {
	ocs: {
		meta: {
			status: string
			statuscode: number
			message: string
		}
		data: T
	}
}
