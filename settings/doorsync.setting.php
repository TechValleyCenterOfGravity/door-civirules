<?php

/**
 * Settings for the door-sync CiviRules webhook.
 *
 * doorsync_webhook_url:    base URL of the door-webhook Cloudflare Worker.
 * doorsync_webhook_secret: shared HMAC secret; must equal CIVICRM_WEBHOOK_SECRET
 *                          set on the Worker.
 *
 * Both carry `group => 'doorsync'`, which is what the setting-admin mixin keys
 * on: it collects every setting in that group onto an auto-generated page at
 * Administer > System Settings > Door-Sync CiviRules Webhook Settings. Adding a
 * setting here puts it on that page — no form class or template needed.
 *
 * Or set them with cv, e.g.:
 *   cv api4 Setting.set +v doorsync_webhook_url='https://door-webhook.example.workers.dev'
 *   cv api4 Setting.set +v doorsync_webhook_secret='...'
 */

return [
  'doorsync_webhook_url' => [
    'name' => 'doorsync_webhook_url',
    'title' => ts('door-sync webhook URL'),
    'description' => ts('Base URL of the door-webhook Cloudflare Worker, with no trailing path, e.g. https://door-webhook.example.workers.dev — use https, since the signed payload and headers would otherwise cross the network in the clear.'),
    'group_name' => 'Domain Preferences',
    'group' => 'doorsync',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'add' => '5.75',
  ],
  'doorsync_webhook_secret' => [
    'name' => 'doorsync_webhook_secret',
    'title' => ts('door-sync webhook HMAC secret'),
    'description' => ts('Shared HMAC secret. Must match CIVICRM_WEBHOOK_SECRET on the Worker exactly, or the Worker rejects every webhook with a 401.'),
    'group_name' => 'Domain Preferences',
    'group' => 'doorsync',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'add' => '5.75',
  ],
];
