<?php

/**
 * Settings for the door-sync CiviRules webhook.
 *
 * doorsync_webhook_url:    base URL of the door-webhook Cloudflare Worker.
 * doorsync_webhook_secret: shared HMAC secret; must equal CIVICRM_WEBHOOK_SECRET
 *                          set on the Worker.
 *
 * Set them with cv, e.g.:
 *   cv api4 Setting.set +v doorsync_webhook_url='https://door-webhook.example.workers.dev'
 *   cv api4 Setting.set +v doorsync_webhook_secret='...'
 */

return [
  'doorsync_webhook_url' => [
    'name' => 'doorsync_webhook_url',
    'title' => ts('door-sync webhook URL'),
    'description' => ts('Base URL of the door-webhook Cloudflare Worker, e.g. https://door-webhook.example.workers.dev'),
    'group_name' => 'Domain Preferences',
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
    'description' => ts('Shared HMAC secret; must match CIVICRM_WEBHOOK_SECRET on the Worker.'),
    'group_name' => 'Domain Preferences',
    'type' => 'String',
    'html_type' => 'text',
    'default' => '',
    'is_domain' => 1,
    'is_contact' => 0,
    'add' => '5.75',
  ],
];
