<?php

/**
 * The door-sync webhook wire contract, in one dependency-free place.
 *
 * Everything here is signature-relevant: the payload shape and key order, the
 * exact JSON serialization, the HMAC construction, and the header names. The
 * door-webhook Worker pins all of it with a golden vector (see the "CiviCRM
 * producer contract" block in door-webhook/test/index.spec.ts). After changing
 * anything in this class, regenerate that vector with `php bin/gen-vector.php`
 * and paste it into the Worker's spec — never edit the Worker's expected values
 * to match new behaviour here, or the two ends drift apart silently and
 * production webhooks start returning 401.
 *
 * This class deliberately extends nothing and calls no CiviCRM API, so both the
 * unit tests and bin/gen-vector.php can exercise the real contract code without
 * bootstrapping CiviCRM.
 */
final class CRM_CivirulesActions_DoorSync_Contract {

  const TIMESTAMP_HEADER = 'X-Door-Sync-Timestamp';
  const SIGNATURE_HEADER = 'X-Door-Sync-Signature';
  const SIGNATURE_PREFIX = 'sha256=';

  /**
   * Build the payload for a membership-change event.
   *
   * Only contact_id is sent: door-sync always runs a whole-population
   * reconcile, so no other member data is needed (and none is logged).
   *
   * A missing or non-positive contact id is sent as JSON null, not 0. The
   * Worker reads a non-integer contact_id as "unknown", which is accurate;
   * 0 would name a contact that cannot exist and would land in the Worker's
   * logs as if it were real.
   *
   * @param mixed $contactId Raw id from the trigger data; may be NULL, '', 0 or a numeric string.
   * @param int $occurredAt Unix seconds.
   * @return array
   */
  public static function membershipPayload($contactId, $occurredAt) {
    $id = NULL;
    if (is_numeric($contactId) && (int) $contactId > 0) {
      $id = (int) $contactId;
    }
    return [
      'contact_id' => $id,
      'occurred_at' => (int) $occurredAt,
    ];
  }

  /**
   * Serialize a payload to the exact bytes that get signed and sent.
   *
   * The signature covers these bytes, so the body must be encoded once and
   * reused for both signing and the request body — never re-encoded.
   *
   * @param array $payload
   * @return string
   */
  public static function encodeBody(array $payload) {
    return json_encode($payload, JSON_UNESCAPED_SLASHES);
  }

  /**
   * HMAC-SHA256 over "<timestamp>.<body>", as lowercase hex with no prefix.
   *
   * @param string $secret
   * @param string $timestamp Unix seconds, as sent in the timestamp header.
   * @param string $body The encoded body, byte-for-byte as sent.
   * @return string
   */
  public static function sign($secret, $timestamp, $body) {
    return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
  }

  /**
   * The full header set for a signed request.
   *
   * @param string $secret
   * @param string $timestamp
   * @param string $body
   * @return array
   */
  public static function headers($secret, $timestamp, $body) {
    return [
      'Content-Type' => 'application/json',
      self::TIMESTAMP_HEADER => $timestamp,
      self::SIGNATURE_HEADER => self::SIGNATURE_PREFIX . self::sign($secret, $timestamp, $body),
    ];
  }

}
