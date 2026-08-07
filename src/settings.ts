/**
 * SPDX-FileCopyrightText: 2026 AlexPuchner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import '@nextcloud/dialogs/style.css'

import { createApp } from 'vue'
import PersonalSettings from './views/PersonalSettings.vue'

createApp(PersonalSettings).mount('#folder-upload-notifications-settings')
