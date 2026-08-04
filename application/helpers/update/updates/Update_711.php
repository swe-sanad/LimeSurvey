<?php

namespace LimeSurvey\Helpers\Update;

/**
 * Multi-tenancy Phase 1: per-survey visibility.
 *
 * Adds a `visibility` enum column to `surveys` (draft/public/invite/private),
 * defaulting to and backfilled as 'public' so every existing survey keeps
 * behaving exactly as it does today (public = pass-through, no new gate).
 *
 * @see docs/multitenancy/PHASE1-DESIGN.md Per-survey visibility
 */
class Update_711 extends DatabaseUpdateBase
{
    public function up()
    {
        addColumn('{{surveys}}', 'visibility', "string(10) NOT NULL DEFAULT 'public'");
        $this->db->createCommand()->update('{{surveys}}', array('visibility' => 'public'));
        $this->db->createCommand()->createIndex('{{idx1_surveys_visibility}}', '{{surveys}}', 'visibility');
    }
}
