<?php

namespace LimeSurvey\Helpers\Update;

/**
 * Multi-tenancy Phase 1: organization team invites (Workflow C).
 *
 * Adds the `org_invites` table: an org-admin invites a user by email, the
 * invitee accepts via a tokenized link and becomes a member User of that
 * org. See docs/multitenancy/PHASE1-DESIGN.md Workflow C.
 */
class Update_712 extends DatabaseUpdateBase
{
    public function up()
    {
        $this->db->createCommand()->createTable('{{org_invites}}', array(
            'id' => 'pk',
            'org_id' => 'integer NOT NULL',
            'email' => 'string(254) NOT NULL',
            'token' => 'string(64) NOT NULL',
            'invited_by' => 'integer NULL',
            'status' => "string(20) NOT NULL DEFAULT 'pending'",
            'created' => 'datetime NULL',
            'expires' => 'datetime NULL',
            'accepted_at' => 'datetime NULL',
        ), $this->options);

        $this->db->createCommand()->createIndex('{{idx1_org_invites_token}}', '{{org_invites}}', 'token', true);
        $this->db->createCommand()->createIndex('{{idx2_org_invites_org}}', '{{org_invites}}', 'org_id');
    }
}
