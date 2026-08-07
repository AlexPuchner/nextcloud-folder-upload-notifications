# Experimental subscription API

> This developer API is experimental. Routes and response fields may change during the alpha phase without backward-compatibility guarantees. Normal use of the app does not require direct API access.

The app exposes an authenticated OCS API. Every route is scoped to the logged-in Nextcloud user, and CSRF protection remains enabled for browser requests.

Base path:

```text
/ocs/v2.php/apps/folder_upload_notifications/api/v1
```

Send `OCS-APIRequest: true` and request JSON with `Accept: application/json` or `?format=json`.

## List subscriptions

```http
GET /subscriptions
```

Returns only subscriptions owned by the current user.

## Create or replace a subscription

```http
POST /subscriptions
Content-Type: application/json

{
  "folderFileId": 42,
  "recursive": true,
  "notifyOwnUploads": false,
  "notifyPush": true,
  "notifyEmail": false
}
```

`folderFileId` must identify a folder visible in the authenticated user's Nextcloud file tree. Repeating the request for the same folder updates its options instead of creating a duplicate.

## Update options

```http
PATCH /subscriptions/{id}
Content-Type: application/json

{
  "recursive": false,
  "notifyOwnUploads": true,
  "notifyPush": false,
  "notifyEmail": true
}
```

## Delete a subscription

```http
DELETE /subscriptions/{id}
```

Unknown IDs and subscriptions owned by another user both return `404`.

## Response object

```json
{
  "id": 7,
  "folderFileId": 42,
  "displayPath": "/Poster",
  "recursive": true,
  "notifyOwnUploads": false,
  "notifyPush": true,
  "notifyEmail": false,
  "createdAt": 1786053600,
  "updatedAt": 1786053600
}
```

Internal storage and owner IDs are intentionally omitted. `notifyPush` creates a native Nextcloud notification for the web interface and registered mobile clients. `notifyEmail` sends mail through Nextcloud's configured mailer. Both channels are dispatched after the two-minute batching window.
