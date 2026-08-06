# Subscription API

The backend exposes an authenticated OCS API. All routes require a logged-in
Nextcloud user. CSRF protection remains enabled for browser requests.

Base path:

```text
/ocs/v2.php/apps/folder_upload_notifications/api/v1
```

Set `OCS-APIRequest: true` and request JSON with either `Accept:
application/json` or `?format=json`.

## List subscriptions

```http
GET /subscriptions
```

Only subscriptions owned by the current user are returned.

## Subscribe to a folder

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

`folderFileId` must identify a folder that is currently visible inside the
authenticated user's Nextcloud file tree. Repeating the request for the same
folder updates the options instead of creating a duplicate subscription.

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

Unknown IDs and IDs owned by another user both return `404`. This avoids
revealing whether another user's subscription exists.

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

The internal storage ID and owner ID are intentionally not returned.

`notifyPush` creates a native Nextcloud notification. It is shown in the web
notification menu and is forwarded to registered mobile clients by Nextcloud's
notification app. `notifyEmail` sends an immediate message through Nextcloud's
configured mailer to the email address in the subscription owner's profile.
