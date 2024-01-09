<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;
use SMXD\Application\Models\Category;
use SMXD\Application\Lib\CacheHelper;

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
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
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

        $this->belongsTo('parent_category_id', 'SMXD\Application\Models\CategoryExt', 'id', [
            'alias' => 'Parent'
        ]);

        $this->hasMany('id', 'SMXD\Application\Models\CategoryExt', 'parent_category_id', [
            'alias' => 'Children',
            'params' => [
                'order' => 'position ASC'
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
     * @return Category[]
     */
    public static function __getChildrenItems($categoryId)
    {
        return self::find([
            'conditions' => 'parent_category_id = :parent_category_id:',
            'bind' => [
                'parent_category_id' => $categoryId
            ],
            'order' => 'position ASC',
            'cache' => [
                'key' => CacheHelper::__getCacheName('CHILDREN_CATEGORIES_', $categoryId),
                'lifetime' => 86400 * 30
            ]
        ]);
    }

    /**
     * @return Category[]
     */
    public function getAllLeafs()
    {
        $ids = [];
        if($this->getLvl() == 4){
            return $ids;
        }
        if($this->getLvl() == 3){
            $childrens = self::__getChildrenItems($this->getId());
            if(count($childrens) > 0){
                foreach ($childrens as $children){
                    $ids[] = $children->getId();
                }
            }
            return $ids;
        }
        if($this->getLvl() == 2){
            $childrens = self::__getChildrenItems($this->getId());
            if(count($childrens) > 0){
                foreach ($childrens as $children){
                    $ids[] = $children->getId();
                    $children4_s = self::__getChildrenItems($children->getId());
                    if(count($children4_s) > 0){
                        foreach ($children4_s as $children4){
                            $ids[] = $children4->getId();
                        }
                    }
                }
            }
            return $ids;
        }
        if($this->getLvl() == 1){
            $childrens = self::__getChildrenItems($this->getId());
            if(count($childrens) > 0){
                foreach ($childrens as $children){
                    $ids[] = $children->getId();
                    $children3_s = self::__getChildrenItems($children->getId());
                    if(count($children3_s) > 0){
                        foreach ($children3_s as $children3){
                            $ids[] = $children3->getId();
                            $children4_s = self::__getChildrenItems($children3->getId());
                            if(count($children4_s) > 0){
                                foreach ($children4_s as $children4){
                                    $ids[] = $children4->getId();
                                }
                            }
                        }
                    }
                }
            }
            return $ids;
        }

    }

    /**
     * @return Category[]
     */
    public static function __getLevel1Items($subject)
    {
        return self::find([
            'conditions' => 'parent_category_id IS NULL AND lvl = 1 and subject = '.$subject,
            'order' => 'position ASC',
            'cache' => [
                'key' => CacheHelper::__getCacheName('SUBJECT_CATEGORIES_LEVEL_1_', $subject),
                'lifetime' => 86400 * 30
            ]
        ]);
    }

    /**
     *
     */
    public function setNextPosition()
    {
        if ($this->getPreviousSibling()) {
            $this->setPosition($this->getPreviousSibling()->getPosition() + 1);
        } else {
            $this->setPosition($this->countTotalSibilings() ? $this->countTotalSibilings() + 1 : 1);
        }
    }


    /**
     * @return Acl
     */
    public function getPreviousSibling()
    {
        return $this->getParentCategoryId() > 0 ? self::findFirst([
            'conditions' => 'parent_category_id = :parent_category_id: AND id <> :id:',
            'bind' => [
                'parent_category_id' => $this->getParentCategoryId(),
                'id' => $this->getId()
            ],
            'order' => 'position DESC'
        ]) : self::findFirst([
            'conditions' => 'parent_category_id IS NULL OR parent_category_id = 0 AND id <> :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'position DESC'
        ]);
    }

    /**
     * @return Acl
     */
    public function getNextSibling()
    {
        return $this->getParentCategoryId() > 0 ? self::findFirst([
            'conditions' => 'parent_category_id = :parent_category_id: AND id <> :id:',
            'bind' => [
                'parent_category_id' => $this->getParentCategoryId(),
                'id' => $this->getId()
            ],
            'order' => 'position ASC'
        ]) : self::findFirst([
            'conditions' => 'parent_category_id IS parent_category_id OR acl_id = 0 AND id <> :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'position ASC'
        ]);
    }


    /**
     * @return mixed
     */
    public function countTotalSibilings()
    {
        return $this->getParent() ? $this->getParent()->countChildren() : self::count([
            'conditions' => 'parent_category_id IS NULL OR parent_category_id = 0'
        ]);
    }

    /**
     * @return int
     */
    public function getLevelCalculated()
    {
        $parent = $this->getParent();

        if ($parent) {
            $calculatedLevel = $parent->getLvl() + 1;
            return $calculatedLevel;
        } else {
            return 1;
        }
    }
}
