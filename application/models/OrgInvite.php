<?php

/**
 * This is the model class for table "{{org_invites}}".
 *
 * An org-admin's invitation of a user by email to join their organization
 * (Phase 1 multi-tenancy, Workflow C). See docs/multitenancy/PHASE1-DESIGN.md.
 *
 * @property integer $id
 * @property integer $org_id
 * @property string $email
 * @property string $token
 * @property integer $invited_by
 * @property string $status pending|accepted|revoked
 * @property string $created
 * @property string $expires
 * @property string $accepted_at
 */
class OrgInvite extends LSActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REVOKED = 'revoked';

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{org_invites}}';
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
            array('org_id, email, token', 'required'),
            array('org_id, invited_by', 'numerical', 'integerOnly' => true),
            array('email', 'email'),
            array('email', 'length', 'max' => 254),
            array('token', 'length', 'max' => 64),
            array('status', 'in', 'range' => array(self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REVOKED)),
            array('status', 'default', 'value' => self::STATUS_PENDING),
            array('created, expires, accepted_at', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'organization' => array(self::BELONGS_TO, 'Organization', 'org_id'),
            'inviter' => array(self::BELONGS_TO, 'User', 'invited_by'),
        );
    }

    /**
     * @inheritdoc
     * @return OrgInvite
     */
    public static function model($className = __CLASS__)
    {
        /** @var OrgInvite $model */
        $model = parent::model($className);
        return $model;
    }

    /**
     * True only when the invite is still pending and has not expired.
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        if (empty($this->expires)) {
            return true;
        }
        return $this->expires > gmdate('Y-m-d H:i:s');
    }

    /**
     * @param string $token
     * @return OrgInvite|null
     */
    public static function findByToken(string $token): ?OrgInvite
    {
        return self::model()->findByAttributes(['token' => $token]);
    }
}
