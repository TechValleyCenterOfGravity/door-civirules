<?php

/**
 * Base CiviRules action that POSTs an HMAC-signed payload to the door-webhook
 * Cloudflare Worker. Subclasses supply the endpoint path and the payload.
 *
 * The signature scheme matches the Worker and the door-sync Python receiver:
 *   X-Door-Sync-Timestamp: <unix seconds>
 *   X-Door-Sync-Signature: sha256=<hex>
 *   hex = HMAC_SHA256(secret, "<timestamp>." + rawBody)
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
   * owns delivery durability, and a webhook hiccup must not break the CiviCRM
   * request (e.g. the membership edit) that triggered it.
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

    $body = json_encode($this->buildPayload($triggerData), JSON_UNESCAPED_SLASHES);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $url = $baseUrl . $this->getEventPath();

    try {
      $client = new GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 3]);
      $client->post($url, [
        'body' => $body,
        'headers' => [
          'Content-Type' => 'application/json',
          'X-Door-Sync-Timestamp' => $timestamp,
          'X-Door-Sync-Signature' => 'sha256=' . $signature,
        ],
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
