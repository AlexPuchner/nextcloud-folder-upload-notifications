# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0-alpha.5] - 2026-08-07

### Added

- Personal folder subscriptions with native Nextcloud folder selection.
- Optional recursive monitoring of subfolders.
- Optional notifications for uploads by the subscriber.
- Per-subscription push and email delivery settings.
- Native Nextcloud notifications for the web interface and registered mobile clients.
- Email notifications through Nextcloud's configured mailer.
- Support for folders shared between users, including virtual share mount paths.
- Persistent two-minute batching of multiple uploads by recipient, uploader, and target folder.
- English and German user-interface translations.

### Fixed

- Keep queued batches recoverable until every enabled delivery channel succeeds.
- Persist successful channels separately so an email retry does not duplicate an already delivered push notification.
- Produce checksums that reference the public GitHub release asset name.
- Exclude both `.git` files and directories from App Store archives.
- Align static analysis with the supported Nextcloud dependency-injection and file APIs.

### Security

- Subscription CRUD operations are scoped to the authenticated user.
- Folder access is revalidated before notifications are queued and delivered.
- The app does not read file contents or send telemetry to external services.
