<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the door-sync webhook wire contract.
 *
 * The golden vector below is the same one asserted on the other side of the
 * wire, in door-webhook/test/index.spec.ts ("CiviCRM producer contract"). Both
 * repos must agree on it byte-for-byte; if this test fails, CiviCRM's webhooks
 * would start 401ing against the Worker in production.
 *
 * Regenerate with `php bin/gen-vector.php` and update BOTH repos together.
 */
final class ContractTest extends TestCase {

  /** Shared with door-webhook's spec — a test secret, never a real one. */
  private const SECRET = 'unit-civicrm-secret-0123456789';

  private const VECTOR_CONTACT_ID = 42;
  private const VECTOR_TIMESTAMP = '1789000000';
  private const VECTOR_BODY = '{"contact_id":42,"occurred_at":1789000000}';
  private const VECTOR_SIGNATURE = '9db87308e8a5697097b17d481bfad994acacc71be62710b522114c1e9f0e36f7';

  public function testGoldenVectorMatchesTheWorkerContract(): void {
    $payload = CRM_CivirulesActions_DoorSync_Contract::membershipPayload(
      self::VECTOR_CONTACT_ID,
      (int) self::VECTOR_TIMESTAMP
    );
    $body = CRM_CivirulesActions_DoorSync_Contract::encodeBody($payload);

    // Byte-for-byte: the signature covers these exact bytes, so key order and
    // the absence of whitespace are both part of the contract.
    $this->assertSame(self::VECTOR_BODY, $body);
    $this->assertSame(
      self::VECTOR_SIGNATURE,
      CRM_CivirulesActions_DoorSync_Contract::sign(self::SECRET, self::VECTOR_TIMESTAMP, $body)
    );
  }

  public function testSignatureCoversBothTimestampAndBody(): void {
    $sign = fn(string $ts, string $body) => CRM_CivirulesActions_DoorSync_Contract::sign(self::SECRET, $ts, $body);

    // A different timestamp or a tampered body must change the signature —
    // this is what makes the Worker's replay window and integrity check work.
    $this->assertNotSame($sign('1789000000', '{}'), $sign('1789000001', '{}'));
    $this->assertNotSame($sign('1789000000', '{}'), $sign('1789000000', '{"a":1}'));
  }

  public function testHeadersCarryTheSignedContract(): void {
    $headers = CRM_CivirulesActions_DoorSync_Contract::headers(
      self::SECRET,
      self::VECTOR_TIMESTAMP,
      self::VECTOR_BODY
    );

    // Header names and the sha256= prefix are what the Worker reads.
    $this->assertSame('application/json', $headers['Content-Type']);
    $this->assertSame(self::VECTOR_TIMESTAMP, $headers['X-Door-Sync-Timestamp']);
    $this->assertSame('sha256=' . self::VECTOR_SIGNATURE, $headers['X-Door-Sync-Signature']);
  }

  /**
   * A contact id the trigger could not supply must serialize as JSON null.
   *
   * The Worker reads a non-integer contact_id as "unknown", which is true;
   * 0 would name a contact that cannot exist and would be logged as if real.
   */
  #[DataProvider('absentContactIds')]
  public function testAbsentContactIdIsNullNotZero($contactId): void {
    $payload = CRM_CivirulesActions_DoorSync_Contract::membershipPayload($contactId, 1789000000);

    $this->assertNull($payload['contact_id']);
    $this->assertSame(
      '{"contact_id":null,"occurred_at":1789000000}',
      CRM_CivirulesActions_DoorSync_Contract::encodeBody($payload)
    );
  }

  public static function absentContactIds(): array {
    return [
      'null' => [NULL],
      'empty string' => [''],
      'zero int' => [0],
      'zero string' => ['0'],
      'false' => [FALSE],
      'negative' => [-1],
    ];
  }

  /**
   * CiviCRM hands back ids as numeric strings in places. They must serialize
   * as a JSON number, matching the golden vector's shape.
   */
  public function testNumericStringContactIdBecomesAnInteger(): void {
    $payload = CRM_CivirulesActions_DoorSync_Contract::membershipPayload('42', 1789000000);

    $this->assertSame(42, $payload['contact_id']);
    $this->assertSame(
      self::VECTOR_BODY,
      CRM_CivirulesActions_DoorSync_Contract::encodeBody($payload)
    );
  }

  public function testEncodeBodyDoesNotEscapeSlashes(): void {
    // JSON_UNESCAPED_SLASHES is signature-relevant: escaping would change the
    // signed bytes for any future payload carrying a path or URL.
    $this->assertSame(
      '{"path":"/civicrm/membership-changed"}',
      CRM_CivirulesActions_DoorSync_Contract::encodeBody(['path' => '/civicrm/membership-changed'])
    );
  }

}
