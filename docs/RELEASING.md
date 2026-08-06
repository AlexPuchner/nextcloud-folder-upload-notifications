# App Store release process

This document describes the release process for the Nextcloud app ID `folder_upload_notifications`.

## One-time setup

1. Generate a private key and certificate signing request. The private key must never be committed or shared:

```bash
umask 077
mkdir -p ~/.nextcloud/certificates
openssl req -nodes -newkey rsa:4096 \
  -keyout ~/.nextcloud/certificates/folder_upload_notifications.key \
  -out ~/.nextcloud/certificates/folder_upload_notifications.csr \
  -subj "/CN=folder_upload_notifications"
```

2. Submit the CSR to the official [`nextcloud/app-certificate-requests`](https://github.com/nextcloud/app-certificate-requests) repository.
3. After approval, keep the signed `folder_upload_notifications.crt` certificate together with the private key.
4. Register the app at <https://apps.nextcloud.com/developer/apps/new>. Paste the public certificate and create the required app-ID signature with:

```bash
echo -n "folder_upload_notifications" \
  | openssl dgst -sha512 \
      -sign ~/.nextcloud/certificates/folder_upload_notifications.key \
  | openssl base64
```

5. Add the private key as the GitHub Actions secret `APP_PRIVATE_KEY` and an App Store API token as `APPSTORE_TOKEN`.

The expected local certificate layout is:

```text
~/.nextcloud/certificates/folder_upload_notifications.key
~/.nextcloud/certificates/folder_upload_notifications.csr
~/.nextcloud/certificates/folder_upload_notifications.crt
```

## Prepare a release

1. Update `appinfo/info.xml` and `package.json` to the same semantic version.
2. Update `CHANGELOG.md`. App Store pre-releases use the `Unreleased` section.
3. Run all backend and frontend quality checks.
4. Build and inspect the unsigned production archive:

```bash
npm ci
npm run typecheck
npm run build
make appstore
tar -tzf build/artifacts/folder_upload_notifications.tar.gz
```

The archive must contain exactly one top-level folder named `folder_upload_notifications`. Development dependencies, tests, source maps, credentials, and repository metadata must not be included.

## Publish

1. Merge the release commit into `main`.
2. Create a tag matching the app version with a leading `v`, for example `v0.4.0-alpha.4`.
3. Create and publish a GitHub pre-release from that tag.
4. The `appstore-release.yml` workflow rebuilds the archive, adds Nextcloud's integrity signature, attaches the archive to the GitHub release, and submits it to the App Store.

Versions containing a prerelease suffix such as `-alpha.4` are handled as prereleases by the App Store. They are not offered to normal stable-channel installations.

## Verify

- Confirm the workflow completed successfully.
- Check the App Store release page for Nextcloud 32, 33, and 34 compatibility.
- Install the release from the App Store on a beta-channel test instance.
- Run `occ integrity:check-app folder_upload_notifications`.
- Test a single upload, a batched multi-file upload, a shared-folder upload, push delivery, and email delivery.
