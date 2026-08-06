# Contributing

Thank you for helping improve Folder Upload Notifications.

## Development setup

The backend targets PHP 8.1 or newer and Nextcloud 32 APIs. The frontend uses Node.js 24 and npm 11.

```bash
composer install
npm ci
npm run build
```

## Quality checks

```bash
composer lint
composer cs:check
composer psalm
composer test:unit
npm run typecheck
npm run build
```

Add or update unit tests for behavioral changes. Keep public API changes documented in `docs/API.md` and user-visible changes in `CHANGELOG.md`.

## Pull requests

- Keep changes focused and explain the user impact.
- Do not commit private keys, certificates, credentials, personal data, or generated local configuration.
- Use the existing SPDX copyright and license headers for source files.
- Report security vulnerabilities privately as described in `SECURITY.md`.
