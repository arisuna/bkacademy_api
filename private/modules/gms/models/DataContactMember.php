<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;
use Reloday\Gms\Module;

class DataContactMember extends \Reloday\Application\Models\DataContactMemberExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 10;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * get viewer of data
     */


    /**
     * get viewer of data
     */
    public static function __findWithFilter(array $options)
    {
        $companyId = ModuleModel::$company->getId();
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Contact', 'Contact');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\DataContactMember', 'DataContactMember.contact_id = Contact.id', 'DataContactMember');
        $queryBuilder->distinct(true);
        $queryBuilder->where('Contact.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Contact.firstname LIKE :query: OR Contact.lastname LIKE :query: OR Contact.email LIKE :query:", ['query' => '%' . $options['query'] . '%']);
        }

        if (isset($options['contact_object_uuid']) && is_string($options['contact_object_uuid']) && $options['contact_object_uuid'] != '') {
            $queryBuilder->andwhere("Contact.object_uuid = :contact_object_uuid:", ['contact_object_uuid' => $options['contact_object_uuid']]);
        }

        if (isset($options['member_object_uuid']) && is_string($options['member_object_uuid']) && $options['member_object_uuid'] != '') {
            $queryBuilder->andwhere("DataContactMember.object_uuid = :member_object_uuid:", ['member_object_uuid' => $options['member_object_uuid']]);
        }

        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        $queryBuilder->orderBy('Contact.created_at DESC');
        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            return [
                'success' => true,
                'page' => $page,
                'data' => $pagination->items,
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
     * @param $options
     * @return array
     */
    public static function __add(array $options, Contact $contact)
    {
        $dataContactMember = self::findFirst([
            'conditions' => 'object_uuid = :object_uuid: AND contact_id = :contact_id: AND company_id = :company_id:',
            'bind' => [
                'object_uuid' => $options['object_uuid'],
                'contact_id' => $contact->getId(),
                'company_id' => ModuleModel::$company->getId(),
            ]
        ]);
        if (!$dataContactMember) {
            $dataContactMember = new self();
            $dataContactMember->setObjectUuid($options['object_uuid']);
            if (isset($options['object_id']) && $options['object_id'] > 0) {
                $dataContactMember->setObjectId($options['object_id']);
            }
            $dataContactMember->setCompanyId(ModuleModel::$company->getId());
            $dataContactMember->setContactId($contact->getId());
            $dataContactMember->setContactUuid($contact->getUuid());
            $dataContactMember->setObjectName($options['object_source']);
            $return = $dataContactMember->__quickCreate();
            if ($return['success'] == true) {
                $return['data'] = $dataContactMember;
                $return['message'] = 'ADD_CONTACT_SUCCESS_TEXT';
            }
        } else {
            return ['success' => true, 'message' => 'ADD_CONTACT_SUCCESS_TEXT', 'data' => $dataContactMember];
        }
        return $return;
    }

    /**
     * @param $options
     * @return array
     */
    public static function __remove(array $options, Contact $contact)
    {
        $dataContactMember = self::findFirst([
            'conditions' => 'object_uuid = :object_uuid: AND contact_id = :contact_id: AND company_id = :company_id:',
            'bind' => [
                'object_uuid' => $options['object_uuid'],
                'contact_id' => $contact->getId(),
                'company_id' => ModuleModel::$company->getId(),
            ]
        ]);
        if (!$dataContactMember) {
            return ['success' => true, 'message' => 'REMOVE_CONTACT_SUCCESS_TEXT'];
        } else {
            $return = $dataContactMember->__quickRemove();
            if ($return['success'] == true) {
                return ['success' => true, 'message' => 'REMOVE_CONTACT_SUCCESS_TEXT'];
            }
        }
        return $return;
    }
}
