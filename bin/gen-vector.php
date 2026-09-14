#!/usr/bin/env php
<?php

/**
 * Regenerate the cross-repo golden vector from the real contract code.
 *
 * The door-webhook Worker pins this extension's signing behaviour with a fixed
 * vector. After changing CRM_CivirulesActions_DoorSync_Contract, run:
 *
 *   php bin/gen-vector.php
 *
 * then paste the output into the "CiviCRM producer contract" block of
 * door-webhook/test/index.spec.ts, and update the matching constants in
 * tests/phpunit/ContractTest.php. Both repos must move together.
 *
 * The inputs are fixed on purpose: a vector with a live timestamp would churn
 * on every run and could not be asserted on.
 */

require_once __DIR__ . '/../CRM/CivirulesActions/DoorSync/Contract.php';

/** Shared with both test suites — a test secret, never a real one. */
const SECRET = 'unit-civicrm-secret-0123456789';
const CONTACT_ID = 42;
const TIMESTAMP = '1789000000';

$payload = CRM_CivirulesActions_DoorSync_Contract::membershipPayload(CONTACT_ID, (int) TIMESTAMP);
$body = CRM_CivirulesActions_DoorSync_Contract::encodeBody($payload);
$signature = CRM_CivirulesActions_DoorSync_Contract::sign(SECRET, TIMESTAMP, $body);

echo "PHP " . PHP_VERSION . " — door-sync producer golden vector\n";
echo "secret: " . SECRET . "\n\n";

echo "--- door-webhook/test/index.spec.ts ---\n";
echo "const PHP_BODY = '" . $body . "';\n";
echo "const PHP_TIMESTAMP = '" . TIMESTAMP . "';\n";
echo "const PHP_SIGNATURE = 'sha256=" . $signature . "';\n\n";

echo "--- tests/phpunit/ContractTest.php ---\n";
echo "private const VECTOR_BODY = '" . $body . "';\n";
echo "private const VECTOR_TIMESTAMP = '" . TIMESTAMP . "';\n";
echo "private const VECTOR_SIGNATURE = '" . $signature . "';\n";
