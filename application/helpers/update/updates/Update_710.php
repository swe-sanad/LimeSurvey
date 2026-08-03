<?php

namespace LimeSurvey\Helpers\Update;

/**
 * Multi-tenancy Phase 0 foundation:
 * - creates the `organizations` table (the tenant entity) and seeds a "Default" org (org_id 1)
 * - adds a nullable, indexed `owner_org_id` column to `surveys` and `users`, backfilled to org 1
 * - creates the `org_auditor_grants` table (cross-org read/export grants)
 *
 * @see docs/multitenancy/PLAN.md Phase 0, tasks 0.2-0.4
 */
class Update_710 extends DatabaseUpdateBase
{
    public function up()
    {
        $this->createOrganizationsTable();
        $this->seedDefaultOrganization();
        $this->addOwnerOrgIdColumns();
        $this->createOrgAuditorGrantsTable();
    }

    private function createOrganizationsTable()
    {
        $this->db->createCommand()->createTable(
            '{{organizations}}',
            array(
                'org_id' => 'pk',
                'name' => 'string(200) NOT NULL',
                'slug' => 'string(200) NOT NULL',
                'status' => "string(20) NOT NULL DEFAULT 'active'",
                'created_by' => 'integer NULL',
                'created' => 'datetime NULL',
            ),
            $this->options
        );
        $this->db->createCommand()->createIndex(
            '{{idx1_organizations_slug}}',
            '{{organizations}}',
            'slug',
            true
        );
    }

    /**
     * Seeds org_id 1 as "Default" so existing single-tenant data has somewhere to belong.
     */
    private function seedDefaultOrganization()
    {
        $exists = $this->db->createCommand()
            ->select('org_id')
            ->from('{{organizations}}')
            ->where('org_id = 1')
            ->queryScalar();
        if ($exists) {
            return;
        }
        // Insert WITHOUT an explicit org_id so the pk/serial sequence advances to 1.
        // A hard-coded org_id = 1 leaves Postgres' sequence at 0, so creating the *second*
        // org later would collide on org_id = 1. On the freshly-created table this reliably
        // yields org_id = 1, which the owner_org_id backfill below relies on.
        $this->db->createCommand()->insert('{{organizations}}', array(
            'name' => 'Default',
            'slug' => 'default',
            'status' => 'active',
            'created_by' => null,
            'created' => date('Y-m-d H:i:s'),
        ));
    }

    private function addOwnerOrgIdColumns()
    {
        addColumn('{{surveys}}', 'owner_org_id', 'integer');
        addColumn('{{users}}', 'owner_org_id', 'integer');
        $this->db->createCommand()->createIndex('{{idx1_surveys_owner_org_id}}', '{{surveys}}', 'owner_org_id');
        $this->db->createCommand()->createIndex('{{idx1_users_owner_org_id}}', '{{users}}', 'owner_org_id');
        $this->db->createCommand()->update('{{surveys}}', array('owner_org_id' => 1), 'owner_org_id IS NULL');
        $this->db->createCommand()->update('{{users}}', array('owner_org_id' => 1), 'owner_org_id IS NULL');
    }

    private function createOrgAuditorGrantsTable()
    {
        $this->db->createCommand()->createTable(
            '{{org_auditor_grants}}',
            array(
                'id' => 'pk',
                'uid' => 'integer NOT NULL',
                'org_id' => 'integer NOT NULL',
                'granted_by' => 'integer NOT NULL',
                'scope' => "string(20) NOT NULL DEFAULT 'read'",
            ),
            $this->options
        );
        $this->db->createCommand()->createIndex(
            '{{idx1_org_auditor_grants_uid_org}}',
            '{{org_auditor_grants}}',
            array('uid', 'org_id')
        );
    }
}
