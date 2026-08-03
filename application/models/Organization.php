<?php

/**
 * This is the model class for table "{{organizations}}".
 *
 * The tenant entity for multi-tenancy. See docs/multitenancy/PLAN.md and SPEC.md.
 *
 * @property integer $org_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property integer $created_by
 * @property string $created
 */
class Organization extends LSActiveRecord
{
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{organizations}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return 'org_id';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('name, slug', 'required'),
            array('name', 'length', 'max' => 200),
            array('slug', 'length', 'max' => 200),
            array('slug', 'unique'),
            array('status', 'in', 'range' => array('active', 'suspended')),
            array('status', 'default', 'value' => 'active'),
            array('created_by, created', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'surveys' => array(self::HAS_MANY, 'Survey', 'owner_org_id'),
            'users' => array(self::HAS_MANY, 'User', 'owner_org_id'),
            'creator' => array(self::BELONGS_TO, 'User', 'created_by'),
        );
    }

    /**
     * @inheritdoc
     * @return Organization
     */
    public static function model($className = __CLASS__)
    {
        /** @var Organization $model */
        $model = parent::model($className);
        return $model;
    }
}
