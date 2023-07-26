<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

use Phalcon\Mvc\Model\Behavior\Timestampable;
use Phalcon\Mvc\Model\Relation;
use Phalcon\Validation;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class Constant extends \SMXD\Application\Models\ConstantExt
{
    const LIMIT_PER_PAGE = 10;


    public function initialize()
    {
        parent::initialize();

        $this->hasMany('id', 'SMXD\App\Models\ConstantTranslation', 'constant_id', [
            'alias' => 'ConstantTranslations',
            'foreignKey' => [
                'action' => Relation::ACTION_CASCADE,
            ]
        ]);
    }


    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Constant', 'Constant');
        $queryBuilder->distinct(true);
        $queryBuilder->leftJoin('\SMXD\App\Models\ConstantTranslation', "Constant.id = ConstantTranslationVI.constant_id AND ConstantTranslationVI.language = 'vi'", 'ConstantTranslationVI');
        $queryBuilder->leftJoin('\SMXD\App\Models\ConstantTranslation', "Constant.id = ConstantTranslationEN.constant_id AND ConstantTranslationEN.language = 'en'", 'ConstantTranslationEN');
        $queryBuilder->groupBy('Constant.id');

        $queryBuilder->columns([
            'Constant.id',
            'Constant.name',
            'Constant.value',
            'ConstantTranslationVI.value as value_vi',
            'ConstantTranslationEN.value as value_en',
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Constant.name LIKE :search: OR Constant.value LIKE :search: OR ConstantTranslationVI.value LIKE :search: OR ConstantTranslationEN.value LIKE :search: ", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $constant_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $constant) {
                    $constant_array[] = $constant;
                }
            }

            return [
                //'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'params' => $options,
                'page' => $page,
                'data' => $constant_array,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param array $translatedItems
     */
    public function createTranslatedData($translatedItems = [])
    {
        $result = [
            'success' => true
        ];

        if (is_array($translatedItems) & !empty($translatedItems)) {
            // Get current data translated
            $existingTranslatedItems = ConstantTranslation::find('constant_id=' . $this->getId());
            if (count($existingTranslatedItems)) {
                foreach ($existingTranslatedItems as $item) {
                    $is_break = false;
                    foreach ($translatedItems as $index => $translated) {
                        if (isset($translated['id'])) {
                            if ($translated['id'] == $item->getId()) {
                                // try update this translated
                                $item->setLanguage($translated['language']);
                                $item->setValue($translated['value']);
                                $resultSaveItem = $item->__quickSave();
                                if ($resultSaveItem['success'] == false) {
                                    $result = [
                                        'success' => false,
                                        'message' => 'Try update constant translate to ' . strtoupper($item->getLanguage()) . ' was error'
                                    ];
                                    goto end;
                                }
                                unset($translatedItems[$index]);
                                $is_break = true;
                                break;
                            }
                        }
                    }
                    if (!$is_break) {
                        // Delete current translated, because, it was not found in list posted
                        $resultSaveItem = $item->__quickRemove();
                        if ($resultSaveItem['success'] == false) {
                            $result = [
                                'success' => false,
                                'message' => 'Try unset constant translate was error'
                            ];
                            goto end;
                        }
                    }
                }
            }

            // Try to add translate data if has new
            if (count($translatedItems)) {
                foreach ($translatedItems as $item) {
                    $object = new ConstantTranslation();
                    $object->setLanguage($item['language']);
                    $object->setValue($item['value']);
                    $object->setConstantId($this->getId());
                    $resultSave = $object->__quickCreate();
                    if ($resultSave['success'] == false) {
                        $result = [
                            'success' => false,
                            'message' => 'Try add new constant translate to ' . strtoupper($item['language']) . ' was error'
                        ];
                        goto end;
                    }
                }
            }
            $result = [
                'success' => true
            ];
        }

        end:
        return $result;
    }
}
