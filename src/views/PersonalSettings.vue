<!--
  SPDX-FileCopyrightText: 2026 AlexPuchner
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { FilePickerClosed, getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import FolderOutlineIcon from 'vue-material-design-icons/FolderOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import { computed, onMounted, ref } from 'vue'
import {
	createSubscription,
	deleteSubscription,
	listSubscriptions,
	updateSubscription,
} from '../services/subscriptions'
import type { Subscription } from '../types'

const APP_ID = 'folder_upload_notifications'

type PickerNode = {
	fileid?: number
}

const subscriptions = ref<Subscription[]>([])
const loading = ref(true)
const adding = ref(false)
const savingIds = ref(new Set<number>())
const subscriptionToDelete = ref<Subscription | null>(null)

const sortedSubscriptions = computed(() => [...subscriptions.value].sort((left, right) => (
	left.displayPath.localeCompare(right.displayPath, undefined, { sensitivity: 'base' })
)))

onMounted(load)

async function load(): Promise<void> {
	loading.value = true

	try {
		subscriptions.value = await listSubscriptions()
	} catch (error) {
		console.error('Failed to load folder subscriptions', error)
		showError(t(APP_ID, 'Could not load folder subscriptions'))
	} finally {
		loading.value = false
	}
}

async function openFolderPicker(): Promise<void> {
	let selectedFileId: number | null = null
	const picker = getFilePickerBuilder(t(APP_ID, 'Choose a folder'))
		.setMultiSelect(false)
		.allowDirectories(true)
		.addMimeTypeFilter('httpd/unix-directory')
		.addButton({
			label: t(APP_ID, 'Subscribe'),
			callback: (nodes) => {
				selectedFileId = (nodes[0] as PickerNode | undefined)?.fileid ?? null
			},
		})
		.build()

	try {
		await picker.pick()
	} catch (error) {
		if (error instanceof FilePickerClosed) {
			return
		}

		console.error('Folder picker failed', error)
		showError(t(APP_ID, 'Could not open the folder picker'))
		return
	}

	if (selectedFileId === null) {
		return
	}

	adding.value = true

	try {
		const saved = await createSubscription(selectedFileId)
		const index = subscriptions.value.findIndex(({ id }) => id === saved.id)

		if (index === -1) {
			subscriptions.value.push(saved)
		} else {
			subscriptions.value[index] = saved
		}

		showSuccess(t(APP_ID, 'Folder subscribed'))
	} catch (error) {
		console.error('Failed to create folder subscription', error)
		showError(t(APP_ID, 'Could not subscribe to the folder'))
	} finally {
		adding.value = false
	}
}

async function changeOption(
	subscription: Subscription,
	changes: Partial<Pick<Subscription, 'recursive' | 'notifyOwnUploads'>>,
): Promise<void> {
	const previous = { ...subscription }
	Object.assign(subscription, changes)
	setSaving(subscription.id, true)

	try {
		const saved = await updateSubscription(subscription)
		const index = subscriptions.value.findIndex(({ id }) => id === subscription.id)
		if (index !== -1) {
			subscriptions.value[index] = saved
		}
		showSuccess(t(APP_ID, 'Settings saved'))
	} catch (error) {
		Object.assign(subscription, previous)
		console.error('Failed to update folder subscription', error)
		showError(t(APP_ID, 'Could not save the settings'))
	} finally {
		setSaving(subscription.id, false)
	}
}

function setSaving(id: number, saving: boolean): void {
	const updated = new Set(savingIds.value)

	if (saving) {
		updated.add(id)
	} else {
		updated.delete(id)
	}

	savingIds.value = updated
}

async function confirmDelete(): Promise<void> {
	const subscription = subscriptionToDelete.value
	if (subscription === null) {
		return
	}

	setSaving(subscription.id, true)

	try {
		await deleteSubscription(subscription.id)
		subscriptions.value = subscriptions.value.filter(({ id }) => id !== subscription.id)
		subscriptionToDelete.value = null
		showSuccess(t(APP_ID, 'Folder subscription removed'))
	} catch (error) {
		console.error('Failed to delete folder subscription', error)
		showError(t(APP_ID, 'Could not remove the folder subscription'))
	} finally {
		setSaving(subscription.id, false)
	}
}
</script>

<template>
	<NcSettingsSection
		:name="t(APP_ID, 'Folder upload notifications')"
		:description="t(APP_ID, 'Choose the folders for which you want to be notified when new files are uploaded.')">
		<div class="settings-actions">
			<NcButton
				variant="primary"
				:disabled="adding || loading"
				@click="openFolderPicker">
				<template #icon>
					<NcLoadingIcon v-if="adding" :size="20" />
					<PlusIcon v-else :size="20" />
				</template>
				{{ t(APP_ID, 'Add folder') }}
			</NcButton>
		</div>

		<div v-if="loading" class="loading-state">
			<NcLoadingIcon :size="32" />
			<span>{{ t(APP_ID, 'Loading folder subscriptions …') }}</span>
		</div>

		<NcEmptyContent
			v-else-if="subscriptions.length === 0"
			:name="t(APP_ID, 'No folders subscribed yet')"
			:description="t(APP_ID, 'Add a folder to receive a notification when someone uploads a new file.')">
			<template #icon>
				<BellOutlineIcon />
			</template>
		</NcEmptyContent>

		<ul v-else class="subscription-list">
			<li
				v-for="subscription in sortedSubscriptions"
				:key="subscription.id"
				class="subscription-card">
				<div class="subscription-header">
					<FolderOutlineIcon :size="28" aria-hidden="true" />
					<div class="subscription-title">
						<h3>{{ subscription.displayPath }}</h3>
						<span>{{ t(APP_ID, 'Notifications enabled') }}</span>
					</div>
					<NcButton
						variant="tertiary"
						:disabled="savingIds.has(subscription.id)"
						:ariaLabel="t(APP_ID, 'Remove subscription for {folder}', { folder: subscription.displayPath })"
						@click="subscriptionToDelete = subscription">
						<template #icon>
							<DeleteOutlineIcon :size="20" />
						</template>
					</NcButton>
				</div>

				<div class="subscription-options">
					<NcCheckboxRadioSwitch
						:model-value="subscription.recursive"
						type="switch"
						:disabled="savingIds.has(subscription.id)"
						@update:model-value="changeOption(subscription, { recursive: $event })">
						{{ t(APP_ID, 'Include subfolders') }}
					</NcCheckboxRadioSwitch>
					<p>{{ t(APP_ID, 'Also notify about new files in folders below this folder.') }}</p>

					<NcCheckboxRadioSwitch
						:model-value="subscription.notifyOwnUploads"
						type="switch"
						:disabled="savingIds.has(subscription.id)"
						@update:model-value="changeOption(subscription, { notifyOwnUploads: $event })">
						{{ t(APP_ID, 'Notify about my own uploads') }}
					</NcCheckboxRadioSwitch>
					<p>{{ t(APP_ID, 'By default, only uploads from other users trigger a notification.') }}</p>
				</div>
			</li>
		</ul>
	</NcSettingsSection>

	<NcDialog
		v-if="subscriptionToDelete !== null"
		:name="t(APP_ID, 'Remove folder subscription?')"
		@closing="subscriptionToDelete = null">
		<p class="delete-description">
			{{ t(APP_ID, 'You will no longer receive upload notifications for {folder}.', {
				folder: subscriptionToDelete.displayPath,
			}) }}
		</p>
		<template #actions>
			<NcButton @click="subscriptionToDelete = null">
				{{ t(APP_ID, 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				:disabled="savingIds.has(subscriptionToDelete.id)"
				@click="confirmDelete">
				<template #icon>
					<NcLoadingIcon
						v-if="savingIds.has(subscriptionToDelete.id)"
						:size="20" />
					<DeleteOutlineIcon v-else :size="20" />
				</template>
				{{ t(APP_ID, 'Remove subscription') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.settings-actions {
	display: flex;
	margin-block: 20px 24px;
}

.loading-state {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 120px;
	color: var(--color-text-maxcontrast);
}

.subscription-list {
	display: grid;
	gap: 16px;
	max-width: 720px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.subscription-card {
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.subscription-header {
	display: flex;
	align-items: center;
	gap: 12px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.subscription-title {
	min-width: 0;
	flex: 1;
}

.subscription-title h3 {
	overflow: hidden;
	margin: 0;
	font-size: 1rem;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.subscription-title span,
.subscription-options p {
	color: var(--color-text-maxcontrast);
}

.subscription-title span {
	font-size: 0.875rem;
}

.subscription-options {
	display: grid;
	gap: 4px;
	padding-top: 16px;
}

.subscription-options p {
	margin: 0 0 12px 44px;
	font-size: 0.875rem;
}

.subscription-options p:last-child {
	margin-bottom: 0;
}

.delete-description {
	margin: 8px 4px 20px;
}

@media (max-width: 600px) {
	.subscription-card {
		padding: 16px;
	}

	.subscription-options p {
		margin-left: 0;
	}
}
</style>
