<?php

/**
 * CiviRules action: POST a membership-change event to the door-webhook Worker.
 *
 * Configure a CiviRule with a Membership trigger (added / changed / status
 * change) and this action. The payload carries only contact_id — the door-sync
 * daemon runs a whole-population reconcile, so no other member data is needed
 * or sent.
 */
class CRM_CivirulesActions_DoorSync_MembershipWebhook extends CRM_CivirulesActions_DoorSync_Base {

  /**
   * @return string
   */
  protected function getEventPath() {
    return '/civicrm/membership-changed';
  }

  /**
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array
   */
  protected function buildPayload(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    return CRM_CivirulesActions_DoorSync_Contract::membershipPayload(
      $triggerData->getContactId(),
      time()
    );
  }

  /**
   * Human-readable summary shown on the CiviRule.
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    return ts('POST an HMAC-signed membership-changed event to the door-sync Worker.');
  }

}
