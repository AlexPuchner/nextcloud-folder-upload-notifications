import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'node:path'

export default createAppConfig(
	{
		settings: resolve(join('src', 'settings.ts')),
	},
	{
		createEmptyCSSEntryPoints: true,
		extractLicenseInformation: true,
		thirdPartyLicense: false,
		config: {
			build: {
				sourcemap: false,
			},
		},
	},
)
