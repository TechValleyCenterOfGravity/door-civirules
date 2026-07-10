<?php

/**
 * door-sync CiviRules producer extension.
 *
 * Registers a CiviRules action ("Send door-sync membership webhook") that POSTs
 * an HMAC-signed payload to the door-webhook Cloudflare Worker whenever a
 * configured rule fires (e.g. a membership status change). The Worker validates
 * and buffers the event, then pushes it to the door-sync daemon on the Pi.
 *
 * The action class autoloads via the psr0 classloader declared in info.xml.
 * Settings (Worker URL + HMAC secret) load from settings/doorsync.setting.php
 * via the setting-php mixin. No PII in logs — contact_id only.
 */

/**
 * Register the CiviRules action(s) on install.
 *
 * @see hook_civicrm_install
 */
function doorsync_civicrm_install() {
  _doorsync_register_actions();
}

/**
 * Re-register on enable so re-enabling a disabled extension restores the action.
 *
 * @see hook_civicrm_enable
 */
function doorsync_civicrm_enable() {
  _doorsync_register_actions();
}

/**
 * Insert (or update) this extension's CiviRules actions from the JSON manifest.
 * Idempotent: insertActionsFromJson updates an action if it already exists.
 */
function _doorsync_register_actions() {
  if (!method_exists('CRM_Civirules_Utils_Upgrader', 'insertActionsFromJson')) {
    throw new CRM_Core_Exception(
      'The CiviRules extension (org.civicoop.civirules) must be installed and enabled before Door-Sync CiviRules Webhook.'
    );
  }
  CRM_Civirules_Utils_Upgrader::insertActionsFromJson(__DIR__ . '/civirules_actions.json');
}
