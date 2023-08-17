<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class CategoryExt extends Category
{

    use ModelTraits;

	/** status archived */
	const STATUS_ARCHIVED = -1;
	/** status active */
	const STATUS_ACTIVE = 1;
	/** status draft */
	const STATUS_DRAFT = 0;
	
	/**
	 * [initialize description]
	 * @return [type] [description]
	 */
	public function initialize(){
		parent::initialize();
		$this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));

//        $this->addBehavior(new SoftDelete([
//            'field' => 'status',
//            'value' => self::STATUS_ARCHIVED
//        ]));

        $this->belongsTo('parent_category_id', '\SMXD\Application\Models\CategoryExt', 'id', [
            'alias' => 'Parent',
            'reusable' => true,
            "foreignKey" => [
                "allowNulls" => true,
                "action" => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->hasMany('id', '\SMXD\Application\Models\CategoryExt', 'parent_category_id', [
            'alias' => 'Children',
            'reusable' => true,
            'params' => [
                'order' => 'pos ASC'
            ]
        ]);
	}



    /**
     * @param array $custom
     */
    public function setData( $custom = []){

         ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    /**
     * @param $pos
     * @return CategoryExt
     */
    public function setPosition($pos)
    {
        return $this->setPos($pos);
    }


    /**
     *
     */
    public function countTotalSibilings()
    {
        return $this->getParent() ? $this->getParent()->countChildren() : self::count([
            'conditions' => 'acl_id IS NULL OR acl_id = 0'
        ]);
    }


    /**
     * @return Category
     */
    public function getPreviousSibling()
    {
        return $this->getParentCategoryId() > 0 ? self::findFirst([
            'conditions' => 'parent_category_id = :parent_category_id: AND id <> :id:',
            'bind' => [
                'parent_category_id' => $this->getParentCategoryId(),
                'id' => $this->getId()
            ],
            'order' => 'pos DESC'
        ]) : self::findFirst([
            'conditions' => 'parent_category_id IS NULL OR parent_category_id = 0 AND id <> :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'pos DESC'
        ]);
    }

    /**
     * @return Category
     */
    public function getNextSibling()
    {
        return $this->getParentCategoryId() > 0 ? self::findFirst([
            'conditions' => 'parent_category_id = :parent_category_id: AND id <> :id:',
            'bind' => [
                'parent_category_id' => $this->getParentCategoryId(),
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
        ]) : self::findFirst([
            'conditions' => 'parent_category_id IS NULL OR parent_category_id = 0 AND id <> :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
        ]);
    }

    /**
     *
     */
    public function moveUp()
    {
        $previousSibling = $this->getPreviousSibling();
        if ($previousSibling) {
            $previousSibling->setPos($previousSibling->getPos() + 1 < $previousSibling->countTotalSibilings() ? $previousSibling->getPos() + 1 : $previousSibling->getPos());
            $resultSave = $previousSibling->__quickUpdate();
        }

        $this->setPos($this->getPos() - 1 > 0 ? $this->getPos() - 1 : $this->getPos());
        $resultSave = $this->__quickUpdate();
        return $resultSave;
    }

    /**
     *
     */
    public function moveDown()
    {
        $nextSibling = $this->getNextSibling();
        if ($nextSibling) {
            $nextSibling->setPos($nextSibling->getPos() - 1 > 0 ? $nextSibling->getPos() - 1 : $nextSibling->getPos());
            $resultSave = $nextSibling->__quickUpdate();
        }

        $this->setPos($this->getPos() + 1 > 0 ? $this->getPos() + 1 : $this->getPos());
        $resultSave = $this->__quickUpdate();
        return $resultSave;
    }

    /**
     * @return array
     */
    public function levelUp()
    {
        $parent = $this->getParent();
        if ($parent && $parent->getParentCategoryId() > 0 && $parent->getParentCategoryId()) {
            $this->setParentCategoryId($parent->getAclId());
            $this->setLevel($parent->getLevel());
            $this->setPos($parent->countTotalSibilings() + 1);
        }

        if (!$parent) {
            $this->setLevel(1);
        }
        return $resultSave = $this->__quickUpdate();
    }

    /**
     *
     */
    public function setNextPosition()
    {
        if ($this->getPreviousSibling()) {
            $this->setPos($this->getPreviousSibling()->getPos() + 1);
        } else {
            $this->setPos($this->countTotalSibilings() ? $this->countTotalSibilings() + 1 : 1);
        }
    }
}
