<?php

class ConstantTranslation extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=12, nullable=false)
     */
    public $id;

    /**
     *
     * @var string
     * @Column(type="string", length=2, nullable=false)
     */
    public $language;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=false)
     */
    public $value;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    public $constant_id;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    public $created_at;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    public $updated_at;

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('constant_id', 'Constant', 'id', ['alias' => 'Constant']);
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'constant_translation';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ConstantTranslation[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ConstantTranslation
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
