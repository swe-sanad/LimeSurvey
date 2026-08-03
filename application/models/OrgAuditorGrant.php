<?php

/**
 * This is the model class for table "{{org_auditor_grants}}".
 *
 * The one sanctioned cross-org exception: a grant letting a user with the
 * `auditor` role read/export another org's data. See docs/multitenancy/SPEC.md §4/§5.
 *
 * @property integer $id
 * @property integer $uid
 * @property integer $org_id
 * @property integer $granted_by
 * @property string $scope
 */
class OrgAuditorGrant extends LSActiveRecord
{
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{org_auditor_grants}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return 'id';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('uid, org_id, granted_by', 'required'),
            array('uid, org_id, granted_by', 'numerical', 'integerOnly' => true),
            array('scope', 'in', 'range' => array('read', 'export')),
            array('scope', 'default', 'value' => 'read'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'User', 'uid'),
            'organization' => array(self::BELONGS_TO, 'Organization', 'org_id'),
            'grantedByUser' => array(self::BELONGS_TO, 'User', 'granted_by'),
        );
    }

    /**
     * @inheritdoc
     * @return OrgAuditorGrant
     */
    public static function model($className = __CLASS__)
    {
        /** @var OrgAuditorGrant $model */
        $model = parent::model($className);
        return $model;
    }

    /**
     * Whether $uid holds an auditor grant (any scope) into $orgId.
     *
     * @param int $uid
     * @param int $orgId
     * @return bool
     */
    public static function hasGrant($uid, $orgId)
    {
        return self::model()->exists('uid = :uid AND org_id = :orgId', array(
            ':uid' => $uid,
            ':orgId' => $orgId,
        ));
    }
}
