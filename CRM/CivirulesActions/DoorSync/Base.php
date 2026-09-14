<?php
// SPDX-FileCopyrightText: 2026 Tech Valley Center of Gravity
// SPDX-License-Identifier: AGPL-3.0-only

/**
 * Base CiviRules action that POSTs an HMAC-signed payload to the door-webhook
 * Cloudflare Worker. Subclasses supply the endpoint path and the payload.
 *
 * The wire format — payload shape, JSON encoding, signature and headers — lives
 * in CRM_CivirulesActions_DoorSync_Contract, which is dependency-free so it can
 * be unit-tested and regenerated against the Worker's golden vector without a
 * CiviCRM bootstrap. This class only handles settings, transport and logging.
 *
 * No PII in logs — contact_id only (matches the door-sync no-PII convention).
 */
abstract class CRM_CivirulesActions_DoorSync_Base extends CRM_Civirules_Action {

  /**
   * Path appended to the configured Worker base URL,
   * e.g. '/civicrm/membership-changed'.
   *
   * @return string
   */
  abstract protected function getEventPath();

  /**
   * Build the JSON-serializable payload from the trigger data.
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array
   */
  abstract protected function buildPayload(CRM_Civirules_TriggerData_TriggerData $triggerData);

  /**
   * This action has no extra configuration form.
   *
   * @param int $ruleActionId
   * @return bool
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
  }

  /**
   * Fire the webhook. Failures are logged, never thrown: the Worker's queue
   * owns delivery durability once the request lands, and a webhook hiccup must
   * not break the CiviCRM request (e.g. the membership edit) that triggered it.
   *
   * Note that a request that never lands is dropped here — the event is not
   * retried from CiviCRM. Door access for that contact then stays stale until
   * door-sync's next scheduled reconcile.
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $baseUrl = rtrim((string) Civi::settings()->get('doorsync_webhook_url'), '/');
    $secret = (string) Civi::settings()->get('doorsync_webhook_secret');
    if ($baseUrl === '' || $secret === '') {
      Civi::log()->warning('door-sync webhook not configured; skipping', [
        'action' => get_class($this),
      ]);
      return;
    }

    // Encode once: the signature covers these exact bytes, so the same string
    // must be both signed and sent.
    $body = CRM_CivirulesActions_DoorSync_Contract::encodeBody($this->buildPayload($triggerData));
    $timestamp = (string) time();
    $url = $baseUrl . $this->getEventPath();

    try {
      $client = new GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 3]);
      $client->post($url, [
        'body' => $body,
        'headers' => CRM_CivirulesActions_DoorSync_Contract::headers($secret, $timestamp, $body),
      ]);
    }
    catch (Throwable $e) {
      Civi::log()->error('door-sync webhook post failed', [
        'contact_id' => $triggerData->getContactId(),
        'error' => $e->getMessage(),
      ]);
    }
  }

}
