# SPDX-FileCopyrightText: 2026 AlexPuchner
# SPDX-License-Identifier: AGPL-3.0-or-later

app_name := folder_upload_notifications
build_dir := $(CURDIR)/build/artifacts
stage_dir := $(build_dir)/stage
package := $(build_dir)/$(app_name).tar.gz

.PHONY: appstore clean

appstore: clean
	@test -f js/folder_upload_notifications-settings.mjs || (echo "Missing production frontend assets; run npm ci && npm run build first." >&2; exit 1)
	mkdir -p $(stage_dir)/$(app_name)
	rsync -a \
		--exclude='/.git' \
		--exclude='/.github/' \
		--exclude='/build/' \
		--exclude='/docs/' \
		--exclude='/node_modules/' \
		--exclude='/src/' \
		--exclude='/tests/' \
		--exclude='/vendor/' \
		--exclude='/vendor-bin/' \
		--exclude='/.gitignore' \
		--exclude='/.php-cs-fixer.dist.php' \
		--exclude='/composer.json' \
		--exclude='/composer.lock' \
		--exclude='/CONTRIBUTING.md' \
		--exclude='/Makefile' \
		--exclude='/package.json' \
		--exclude='/package-lock.json' \
		--exclude='/psalm.xml' \
		--exclude='/SECURITY.md' \
		--exclude='/tsconfig.json' \
		--exclude='/vite.config.ts' \
		./ $(stage_dir)/$(app_name)/
	tar -czf $(package) -C $(stage_dir) $(app_name)
	@echo "Created $(package)"

clean:
	rm -rf $(CURDIR)/build
