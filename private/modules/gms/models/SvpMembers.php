<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Security;

class SvpMembers extends \Reloday\Application\Models\SvpMembersExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /**
     * inititalize
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('service_provider_company_id', 'Reloday\Gms\Models\ServiceProviderCompany', 'id', [
            'alias' => 'ServiceProviderCompany',
            'cache' => [
                'key' => 'SERVICE_PROVIDER_COMPANY_' . $this->getServiceProviderCompanyId(),
                'lifetime' => CacheHelper::__TIME_24H
            ],
            'reusable' => true,
        ]);
    }

    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }


    /**
     * [loadList description]
     * @return [type] [description]
     */
    public function loadList($conditions = '', $params = [])
    {
        $result = [];
        // Load user profile
        $company = ModuleModel::$company;

        if (!$company->getCompanyTypeId() == Company::TYPE_GMS) {
            return [
                'success' => false,
                'message' => 'COMPANY_TYPE_DIFFERENT'
            ];
        } else {
            $binds = [];

            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\SvpMembers', 'SvpMembers');
            $queryBuilder->distinct(true);
            $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = SvpMembers.service_provider_company_id', 'ServiceProviderCompany');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\UserGroup', 'UserGroup.id = SvpMembers.user_group_id', 'UserGroup');
            $queryBuilder->columns([
                "SvpMembers.id",
                "SvpMembers.uuid",
                "SvpMembers.service_provider_company_id",
                "SvpMembers.created_at",
                "SvpMembers.updated_at",
                "SvpMembers.firstname",
                "SvpMembers.lastname",
                "SvpMembers.work_email",
                "SvpMembers.private_email",
                "SvpMembers.language",
                "SvpMembers.login",
                "SvpMembers.user_group_id",
                "SvpMembers.home_phone",
                "SvpMembers.home_mobile",
                "SvpMembers.fax",
                "SvpMembers.private_fax",
                "SvpMembers.nickname",
                "SvpMembers.jobtitle",
                "SvpMembers.title",
                "SvpMembers.active_user",
                "SvpMembers.work_phone",
                "SvpMembers.status",
                "SvpMembers.sex",
                "user_group_name" => "UserGroup.label",
                "svp_company_name" => "ServiceProviderCompany.name"
            ]);
            $queryBuilder->where('ServiceProviderCompany.company_id = ' . $company->getId());
            $queryBuilder->andwhere('SvpMembers.status <> ' . self::STATUS_ARCHIVED);
            if ($conditions != '') {
                $queryBuilder->andwhere($conditions);
            }

            if(isset($params['service_provider_ids']) && is_array($params['service_provider_ids']) && count($params['service_provider_ids']) > 0){
                $queryBuilder->andwhere('SvpMembers.service_provider_company_id IN ({service_provider_ids:array})');

                $binds['service_provider_ids'] =  $params['service_provider_ids'];
            }

            $svp_members =  $queryBuilder->getQuery()->execute($binds);

            return [
                'success' => true,
                'data' => $svp_members,
            ];
        }
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        $company = ModuleModel::$company;
        if ($company) {
            $provider = $this->getServiceProviderCompany();
            if ($provider && $provider->getCompanyId() == $company->getId()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * @param $options
     * [loadList description]
     * @return [array]
     */
    public static function __loadListSvpMembers($options = [])
    {
        $di = \Phalcon\DI::getDefault();

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\SvpMembers', 'SvpMembers');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = SvpMembers.service_provider_company_id', 'ServiceProviderCompany');
        $queryBuilder->where('ServiceProviderCompany.company_id = ' . ModuleModel::$company->getId());
        $queryBuilder->andwhere('SvpMembers.status <> ' . self::STATUS_ARCHIVED);
        if (isset($options['svpCompanyId']) && Helpers::__isValidId($options['svpCompanyId']) && intval($options['svpCompanyId']) > 0) {
            $queryBuilder->andwhere('ServiceProviderCompany.id = :svp_company_id:', [
                'svp_company_id' => $options['svpCompanyId']
            ]);
        }

        if (isset($options['svpMemberId']) && Helpers::__isValidId($options['svpMemberId']) && intval($options['svpMemberId']) > 0) {
            $queryBuilder->andwhere('SvpMembers.id = :svp_member_id:', [
                'svp_member_id' => $options['svpMemberId']
            ]);
        }

        $queryBuilder->orderBy(['SvpMembers.firstname ASC']);

        $svp_members = $queryBuilder->getQuery()->execute();

        $items = [];
        if (count($svp_members) > 0) {
            foreach ($svp_members as $svp_member) {
                $items[$svp_member->getId()] = $svp_member->toArray();
                $items[$svp_member->getId()]['user_group_name'] = ($svp_member->getUserGroup() ? $svp_member->getUserGroup()->getLabel() : '');
                $items[$svp_member->getId()]['svp_company_name'] = $svp_member->getServiceProviderCompany() ? $svp_member->getServiceProviderCompany()->getName() : '';
            }
        }

        return array_values($items);
    }
}
