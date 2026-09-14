# Door-Sync CiviRules Webhook (`net.tvcog.doorsync`)

A CiviCRM extension that adds a **CiviRules action** — *"Send door-sync
membership webhook"* — which POSTs an HMAC-signed event to the
[door-webhook](../door-webhook) Cloudflare Worker whenever a rule fires. The
Worker validates and buffers the event, then pushes it to the
[door-sync](../door-sync) daemon on the Pi, so door access reconciles within
seconds of a membership change instead of waiting for the next poll.

This is the **producer** end of the chain:

```
CiviRules action (this ext) ──HMAC POST──▶ door-webhook Worker ──▶ door-sync (Pi)
```

## Signing contract

Shared with the Worker and the Pi's Python receiver:

```
X-Door-Sync-Timestamp: <unix seconds>
X-Door-Sync-Signature: sha256=<hex>
hex = HMAC_SHA256(secret, "<timestamp>." + rawBody)
```

The payload is minimal — `{"contact_id": <int|null>, "occurred_at": <unix>}` —
because door-sync always runs a whole-population reconcile; no other member data
is sent. A contact id the trigger could not supply is sent as `null`, which the
Worker reads as "unknown"; it is never sent as `0`.

Note that the Worker overwrites `occurred_at` with its own receive time when it
normalizes the event onto the queue, so today the field is advisory only.

All of this lives in one dependency-free class,
[`CRM_CivirulesActions_DoorSync_Contract`](CRM/CivirulesActions/DoorSync/Contract.php) —
payload shape, key order, JSON flags, HMAC construction and header names. The
action classes only handle settings, transport and logging.

## Tests

The contract is pinned on both sides of the wire by the same golden vector: here
in `tests/phpunit/ContractTest.php`, and in the "CiviCRM producer contract" block
of `door-webhook/test/index.spec.ts`. They need no CiviCRM bootstrap and no
database — only PHP 8.2 or newer, which is phpunit 11's floor. That is a
development requirement only; it says nothing about the PHP the extension
itself supports:

```bash
composer install
composer test
```

After changing `Contract.php`, regenerate the vector and update **both** repos —
never edit the Worker's expected values to match new behaviour here, or the two
ends drift apart and production webhooks start returning 401:

```bash
php bin/gen-vector.php
```

## Requirements

- CiviRules (`org.civicoop.civirules`) installed and enabled **first**.
- CiviCRM 5.75+.

## Install

Drop this directory into your CiviCRM extensions directory (or `git clone` it
there) and enable it:

```bash
cv ext:enable net.tvcog.doorsync
```

Enabling runs `doorsync_civicrm_install()`, which registers the action from
`civirules_actions.json` via `CRM_Civirules_Utils_Upgrader::insertActionsFromJson`.

> If you prefer a civix-managed skeleton, run `civix generate:module net.tvcog.doorsync`
> and copy `CRM/CivirulesActions/DoorSync/*`, `civirules_actions.json`, and
> `settings/doorsync.setting.php` into it. The action classes and registration
> logic here are drop-in compatible.

## Configure

Set the Worker URL and the shared HMAC secret (must equal `CIVICRM_WEBHOOK_SECRET`
on the Worker):

```bash
cv api4 Setting.set +v doorsync_webhook_url='https://door-webhook.example.workers.dev'
cv api4 Setting.set +v doorsync_webhook_secret='<same as CIVICRM_WEBHOOK_SECRET on the Worker>'
```

## Create the CiviRule

1. **Administer → Automation → CiviRules → New Rule.**
2. **Trigger:** a Membership trigger — e.g. *Membership is added or changed*, or a
   status-change trigger — matching how your site records access-relevant changes.
3. *(Optional)* **Conditions** to scope which memberships matter.
4. **Action:** *Send door-sync membership webhook*. (No action form.)
5. Save and activate.

## Verify

This extension cannot be exercised without a running CiviCRM + CiviRules
instance, so verify after install:

- Trigger the rule (add/edit a membership for a test contact) and confirm the
  Worker receives it (`wrangler tail door-webhook` shows a `202`), the event
  buffers on the queue, and the Pi logs `membership webhook accepted`.
- Set a wrong `doorsync_webhook_secret` and confirm the Worker returns `401`
  (visible in `wrangler tail`) and nothing reaches the Pi.
- Failures are logged to `CiviCRM.log` (`contact_id` only, no PII) and never
  block the triggering CiviCRM request.

## Phase 2

A `DoorSync_DayPassWebhook` action (→ `/civicrm/day-pass`) will be added for the
visitor/day-pass flow, following the same `Base` pattern.
