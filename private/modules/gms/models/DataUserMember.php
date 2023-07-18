<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Mvc\Model\Query\Builder;
use Reloday\Gms\Module;

class DataUserMember extends \Reloday\Application\Models\DataUserMemberExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('user_profile_uuid', '\Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'UserProfile',
            'reusable' => true,
            'cache' => [
                'key' => 'USER_PROFILE_' . $this->getUserProfileUuid(),
                'lifetime' => CacheHelper::__TIME_24H
            ]
        ]);
        $this->belongsTo('member_type_id', '\Reloday\Gms\Models\MemberType', 'id', [
            'alias' => 'MemberType',
            'reusable' => true,
            'cache' => [
                'key' => 'MEMBER_TYPE_' . $this->getMemberTypeId(),
                'lifetime' => CacheHelper::__TIME_24H
            ]
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
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __save($custom = [])
    {

        $req = new Request();
        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            //
            $data_id = isset($custom['data_id']) && $custom['data_id'] > 0 ? $custom['data_id'] : $req->getPut('id');

            if ($data_id > 0) {
                $model = $this->findFirstById($data_id);
                if (!$model instanceof $this) {
                    return [
                        'success' => false,
                        'message' => 'DATA_NOT_FOUND_TEXT',
                    ];
                }
            }
            $data = $req->getPut();
        }
        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
            if ($uuid == '') {
                $random = new Random;
                $uuid = $random->uuid();
            }
            if ($uuid != '') {
                $model->setUuid($uuid);
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && $field_name != "password"
            ) {

                if (!isset($fields_numeric[$field_name])) {
                    $model->set(
                        $field_name,
                        isset($custom[$field_name]) ? $custom[$field_name] :
                            (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name))
                    );
                } else {
                    $field_name_value = isset($custom[$field_name]) ? $custom[$field_name] :
                        (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name));
                    if (is_numeric($field_name_value) && $field_name_value != '' && !is_null($field_name_value) && !empty($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/
        if ($model->getId() > 0) {
            $result = $model->__quickUpdate();
        } else {
            $result = $model->__quickCreate();
        }

        if ($result['success'] == false) {
            return $result;
        } else {
            return $model;
        }
        /****** END YOUR CODE **/
    }

    /**
     * get viewer of data
     */
    public static function getViewersIds($object_uuid, $companyId = null)
    {
        if ($companyId == null) $companyId = ModuleModel::$company->getId();
        $data = self::find([
            "columns" => "id, user_profile_uuid, user_profile_id",
            "conditions" => "member_type_id = :member_type_id: AND object_uuid = :object_uuid: AND company_id = :company_id:",
            "bind" => [
                'company_id' => $companyId,
                'member_type_id' => self::MEMBER_TYPE_VIEWER,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $ids = [];
        foreach ($data as $item) {
            $ids[] = $item->user_profile_id;
        }
        return $ids;
    }

    /**
     * get viewer of data
     */
    public static function __getDataViewers(String $object_uuid, $company_id = null)
    {
        if ($company_id == null) $company_id = ModuleModel::$company->getId();
        $viewer_ids = self::getViewersIds($object_uuid, $company_id);
        try {
            $profiles = UserProfile::find([
                "conditions" => "id IN ({ids:array}) AND company_id = :company_id:",
                "bind" => [
                    'company_id' => $company_id,
                    'ids' => $viewer_ids,
                ]
            ]);
            return $profiles;
        } catch (\PDOException $e) {
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * @param $object_uuid
     */
    public static function getMembersUuids($object_uuid, $company_id = null)
    {
        if ($company_id == null) $company_id = ModuleModel::$company->getId();
        $data = self::find([
            "columns" => "id, user_profile_id, user_profile_uuid",
            "conditions" => "object_uuid = :object_uuid: AND company_id = :company_id:",
            "distinct" => true,
            "bind" => [
                //'member_type_ids' => [self::MEMBER_TYPE_VIEWER, self::MEMBER_TYPE_OWNER, self::MEMBER_TYPE_REPORTER],
                'company_id' => $company_id,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $ids = [];
        foreach ($data as $item) {
            $ids[$item->user_profile_uuid] = $item->user_profile_uuid;
        }
        return $ids;
    }

    /**
     * @param $object_uuid
     * @param null $company_id
     * @return array
     */
    public static function __getMembersUuids($object_uuid, $company_id = null)
    {
        if ($company_id == null) $company_id = ModuleModel::$company->getId();
        $data = self::find([
            "columns" => "id, user_profile_id, user_profile_uuid",
            "conditions" => "object_uuid = :object_uuid: AND company_id = :company_id:",
            "distinct" => true,
            "bind" => [
                //'member_type_ids' => [self::MEMBER_TYPE_VIEWER, self::MEMBER_TYPE_OWNER, self::MEMBER_TYPE_REPORTER],
                'company_id' => $company_id,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $ids = [];
        foreach ($data as $item) {
            $ids[$item->user_profile_uuid] = $item->user_profile_uuid;
        }
        return $ids;
    }

    /**
     * @param $object_uuid
     */
    public static function getMembersSimpleData($object_uuid, $company_id = null)
    {
        if ($company_id == null) $companyId = ModuleModel::$company->getId();
        $data = self::find([
            "columns" => "id, user_profile_id, user_profile_uuid",
            "conditions" => "object_uuid = :object_uuid: AND company_id = :company_id:",
            "distinct" => true,
            "bind" => [
                'company_id' => $company_id,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $ids = [];
        foreach ($data as $item) {
            $ids[$item->user_profile_uuid] = $item;
        }
        return $ids;
    }


    /**
     * @param $object_uuid
     */
    public static function getMembersObject($object_uuid, $company_id = null)
    {
        if ($company_id == null) $company_id = ModuleModel::$company->getId();
        $data = self::find([
            "conditions" => "object_uuid = :object_uuid: AND company_id = :company_id:",
            "distinct" => true,
            "bind" => [
                'company_id' => $company_id,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $return = [];
        foreach ($data as $item) {
            $return[$item->user_profile_uuid] = $item->getUserProfile();
        }
        return $return;
    }

    /**
     * get viewer of data
     */
    public static function getViewersUuids($object_uuid, $companyId = null)
    {
        if ($companyId == null) $companyId = ModuleModel::$company->getId();
        $data = self::find([
            "columns" => "id, user_profile_id, user_profile_uuid",
            "conditions" => "member_type_id = :member_type_id: AND object_uuid = :object_uuid: AND company_id = :company_id:",
            "bind" => [
                'company_id' => $companyId,
                'member_type_id' => self::MEMBER_TYPE_VIEWER,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $uuids = [];
        foreach ($data as $item) {
            $uuids[$item->user_profile_uuid] = $item->user_profile_uuid;
        }
        return $uuids;
    }

    /**
     * USE TRANSACTION TO CREATE
     * @param $model
     * @param null $transactionDb transaction object phalcon
     * @return bool
     */
    public function createOwnerFromModel($model, $transactionDb = null, $profile = '')
    {
        if ($profile == '' || !is_object($profile)) {
            $profile = ModuleModel::$user_profile;
        }
        $data_user_member = $this;
        $data_user_member->set('object_uuid', $model->getUuid());
        $data_user_member->set('object_name', $model->getSource());
        $data_user_member->set('user_profile_id', $profile->getId());
        $data_user_member->set('user_profile_uuid', $profile->getUuid());

        $data_user_member->set('company_id', ModuleModel::$company->getId());
        $data_user_member->set('company_uuid', ModuleModel::$company->getUuid());

        $data_user_member->set('member_type_id', DataUserMember::MEMBER_TYPE_OWNER);
        if (!is_null($transactionDb) && is_object($transactionDb)) {
            $data_user_member->setTransaction($transactionDb);
        }
        if ($data_user_member->save()) {
            return true;
        } else {
            if (!is_null($transactionDb) && is_object($transactionDb)) {
                $transactionDb->rollback('OWNER_SAVE_FAIL_TEXT');
            }
            return false;
        }
    }

    /**
     * USE TRANSACTION TO CREATE
     * @param $model
     * @param null $transactionDb transaction object phalcon
     * @return bool
     */
    public function createReporterFromModel($model, $transactionDb = null, $profile = '')
    {
        if ($profile == false || $profile == '' || !is_object($profile)) {
            $profile = ModuleModel::$user_profile;
        }
        $data_user_member = $this;
        $data_user_member->set('object_uuid', $model->getUuid());
        $data_user_member->set('object_name', $model->getSource());
        $data_user_member->set('user_profile_id', $profile->getId());
        $data_user_member->set('user_profile_uuid', $profile->getUuid());
        $data_user_member->set('company_id', ModuleModel::$company->getId());
        $data_user_member->set('company_uuid', ModuleModel::$company->getUuid());
        $data_user_member->set('member_type_id', DataUserMember::MEMBER_TYPE_OWNER);
        if (!is_null($transactionDb) && is_object($transactionDb)) {
            $data_user_member->setTransaction($transactionDb);
        }
        if ($data_user_member->save()) {
            return true;
        } else {
            if (!is_null($transactionDb) && is_object($transactionDb)) {
                $transactionDb->rollback('OWNER_SAVE_FAIL_TEXT');
            }
            return false;
        }
    }

    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public function addOwnerOfObject($object, $profile)
    {
        return $this->addNewMember($object, $profile, self::MEMBER_TYPE_REPORTER);
    }


    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public function addReporterOfObject($object, $profile)
    {
        return $this->addNewMember($object, $profile, self::MEMBER_TYPE_REPORTER);
    }


    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public static function __addOwnerWithUUID($object_uuid, $profile, $object_source)
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $object_source, self::MEMBER_TYPE_OWNER);
    }


    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public static function __addCreatorWithUUID($object_uuid, $profile, $object_source)
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $object_source, self::MEMBER_TYPE_INITIATOR);
    }

    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public static function __addReporterWithUUID($object_uuid, $profile, $object_source)
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $object_source, self::MEMBER_TYPE_REPORTER);
    }

    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public function addNewMember($object, $profile, $type = self::MEMBER_TYPE_OWNER)
    {
        if (method_exists($object, "getUuid")) {

            if (self::validateRole($type) == false) {
                return false;
            }

            if (is_object($profile)) $profile = (array)$profile;

            $data_user_member = $this;
            $data_user_member->set('object_uuid', $object->getUuid());
            $data_user_member->set('object_name', $object->getSource());
            $data_user_member->set('user_profile_id', $profile['id']);
            $data_user_member->set('user_profile_uuid', $profile['uuid']);
            $data_user_member->set('company_id', ModuleModel::$company->getId());
            $data_user_member->set('company_uuid', ModuleModel::$company->getUuid());
            $data_user_member->set('member_type_id', $type);
        } else {
            return false;
        }

        try {
            if ($data_user_member->save()) {
                return true;
            } else {
                return false;
            }
        } catch (\PDOException $e) {
            $message = $e->getMessage();
            return false;
        } catch (Exception $e) {
            $message = $e->getMessage();
            return false;
        }
    }

    /**
     * @param $object
     * @param $profile
     * @param int $type
     */
    public static function __deleteMember($object, $profile, $type = self::MEMBER_TYPE_OWNER)
    {
        if (self::validateRole($type) == false) {
            return false;
        }

        $dataUserMember = self::findFirst([
            'conditions' => 'object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid: AND member_type_id = :member_type_id:',
            'bind' => [
                'object_uuid' => $object->getUuid(),
                'user_profile_uuid' => $profile->getUuid(),
                'member_type_id' => $type
            ]
        ]);
        if ($dataUserMember) {
            try {
                if ($dataUserMember->delete()) {
                    return true;
                } else {
                    return false;
                }
            } catch (\PDOException $e) {
                return false;
            } catch (Exception $e) {
                return false;
            }
        }
    }

    /**
     * @param $object
     * @param $profile
     * @param int $type
     */
    public static function __deleteMemberFromUuid($object_uuid, $profile = null, $type = self::MEMBER_TYPE_OWNER)
    {
        if (self::validateRole($type) == false) {
            return false;
        }

        $dataUserMember = self::findFirst([
            'conditions' => 'object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid: AND member_type_id = :member_type_id: AND company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'object_uuid' => $object_uuid,
                'user_profile_uuid' => $profile->getUuid(),
                'member_type_id' => $type
            ]
        ]);

        if ($dataUserMember) {
            $return = $dataUserMember->__quickRemove();
            $return['method'] = __METHOD__;
            return $return;
        } else {
            return ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'method' => __METHOD__];
        }
    }

    /**
     * @param $object
     * @param $profile
     * @param int $type
     */
    public static function __deleteMembersFromUuid($object_uuid, $type = self::MEMBER_TYPE_OWNER, $company_id = null)
    {
        if (self::validateRole($type) == false) {
            return false;
        }

        if ($company_id == null) {
            $company_id = ModuleModel::$company->getId();
        }
        $dataUserMembers = self::find([
            'conditions' => 'object_uuid = :object_uuid: AND member_type_id = :member_type_id: AND company_id = :company_id:',
            'bind' => [
                'company_id' => $company_id,
                'object_uuid' => $object_uuid,
                'member_type_id' => $type
            ]
        ]);
        if ($dataUserMembers->count()) {
            $return = ModelHelper::__quickRemoveCollection($dataUserMembers);
            $return['oldOwner'] = $dataUserMembers;
            $return['method'] = __METHOD__;
            return $return;
        } else {
            return ['success' => true, 'message' => 'NO_MEMBERS_TO_REMOVE_TEXT', 'method' => __METHOD__];
        }
    }

    /**
     * Add Owner OBJECT
     * @param $object
     * @param $profile
     * @return boolean
     */
    public static function __addNewMemberFromUuid($object_uuid, $profile, $object_source, $type = self::MEMBER_TYPE_OWNER, $company_id = null)
    {

        if (self::validateRole($type) == false) {
            return ['success' => false];
        }
        if ($company_id == null) {
            $company_id = ModuleModel::$company->getId();
            $company_uuid = ModuleModel::$company->getUuid();
        } else {
            $company = Company::findFirstById($company_id);
            $company_uuid = $company ? $company->getUuid() : null;
        }
        $data_user_member = new self();
        $data_user_member->set('object_uuid', $object_uuid);
        $data_user_member->set('object_name', $object_source);

        if (is_object($profile) && method_exists($profile, 'getId')) {
            $data_user_member->set('user_profile_id', $profile->getId());
        }
        if (is_object($profile) && method_exists($profile, 'getUuid')) {
            $data_user_member->set('user_profile_uuid', $profile->getUuid());
        }

        if (is_array($profile) && isset($profile['id'])) {
            $data_user_member->set('user_profile_id', $profile['id']);
        }
        if (is_array($profile) && isset($profile['uuid'])) {
            $data_user_member->set('user_profile_uuid', $profile['uuid']);
        }

        $data_user_member->set('member_type_id', $type);
        $data_user_member->set('company_id', $company_id);
        $data_user_member->set('company_uuid', $company_uuid);

        $return = $data_user_member->__quickCreate();

        if ($return['success'] == true) {
            $return['message'] = 'ADD_MEMBER_SUCCESS_TEXT';
        }
        $return['method'] = __METHOD__;

        return $return;
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public static function getDataReporter($object_uuid, $companyId = null)
    {
        if ($companyId == null) $companyId = ModuleModel::$company->getId();
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('Reloday\Gms\Models\DataUserMember', 'DataUserMember')
            ->innerJoin('Reloday\Gms\Models\UserProfile', 'DataUserMember.user_profile_id = UserProfile.id', 'UserProfile')
            ->where('DataUserMember.member_type_id = :member_type_id:')
            ->andWhere('DataUserMember.object_uuid = :object_uuid:')
            ->andWhere('DataUserMember.company_id = :company_id:')
            ->orderBy('DataUserMember.created_at DESC')
            ->limit(1);

        $bindArray = array();
        $bindArray['member_type_id'] = self::MEMBER_TYPE_REPORTER;
        $bindArray['object_uuid'] = $object_uuid;
        $bindArray['company_id'] = $companyId;

        $dataUserMembers = $queryBuilder->getQuery()->execute($bindArray);

        if ($dataUserMembers->count() > 0) {
            $dataUserMember = $dataUserMembers->getFirst();
            if ($dataUserMember) {
                return $dataUserMember->getUserProfile();
            }
        }
    }

    /*
    **
    * @param $object_uuid
    * @return mixed
    */
    public static function getDataOwner($object_uuid, $companyId = null)
    {
        if ($companyId == null) $companyId = ModuleModel::$company->getId();
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('Reloday\Gms\Models\DataUserMember', 'DataUserMember')
            ->innerJoin('Reloday\Gms\Models\UserProfile', 'DataUserMember.user_profile_uuid = UserProfile.uuid', 'UserProfile')
            ->where('DataUserMember.member_type_id = :member_type_id:')
            ->andWhere('DataUserMember.object_uuid = :object_uuid:')
            ->andWhere('DataUserMember.company_id = :company_id:')
            ->orderBy('DataUserMember.created_at DESC')
            ->limit(1);

        $bindArray = array();
        $bindArray['member_type_id'] = self::MEMBER_TYPE_OWNER;
        $bindArray['object_uuid'] = $object_uuid;
        $bindArray['company_id'] = $companyId;

        $dataUserMembers = $queryBuilder->getQuery()->execute($bindArray);

        if ($dataUserMembers->count() > 0) {
            $dataUserMember = $dataUserMembers->getFirst();
            if ($dataUserMember) {
                return $dataUserMember->getUserProfile();
            }
        }
    }


    /*
    * get viewers of object of accounbt
    * @param $object_uuid
    * @return mixed
    */
    public static function __getDataMembers($object_uuid, $companyId = null)
    {
        if ($companyId == null) $companyId = ModuleModel::$company->getId();
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('Reloday\Gms\Models\UserProfile', 'UserProfile')
            ->distinct(true)
            ->innerJoin('Reloday\Gms\Models\DataUserMember', 'DataUserMember.user_profile_uuid = UserProfile.uuid', 'DataUserMember')
            ->andWhere('DataUserMember.object_uuid = :object_uuid:')
            ->andWhere('UserProfile.company_id = :company_id:')
            ->orderBy('DataUserMember.created_at DESC');

        $bindArray = array();
        $bindArray['object_uuid'] = $object_uuid;
        $bindArray['company_id'] = $companyId;

        try {
            $profiles = $queryBuilder->getQuery()->execute($bindArray);
            return $profiles;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @param string $source
     */
    public static function addReporter($object_uuid, $profile, $source = self::MEMBER_TYPE_OBJECT_TEXT)
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $source, self::MEMBER_TYPE_REPORTER);
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @param string $source
     */
    public static function addOwner($object_uuid, $profile, $source = self::MEMBER_TYPE_OBJECT_TEXT, $company_id = null)
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $source, self::MEMBER_TYPE_OWNER, $company_id);
    }


    /**
     * @param $object_uuid
     * @param $profile
     * @param string $source
     */
    public static function addViewer($object_uuid, $profile, $source = self::MEMBER_TYPE_OBJECT_TEXT)
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $source, self::MEMBER_TYPE_VIEWER);
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @param string $source
     */
    public static function addCreator($object_uuid, $profile, $source = 'object')
    {
        return self::__addNewMemberFromUuid($object_uuid, $profile, $source, self::MEMBER_TYPE_INITIATOR);
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @return bool
     */
    public static function deleteReporter($object_uuid, $profile)
    {
        return self::__deleteMemberFromUuid($object_uuid, $profile, self::MEMBER_TYPE_REPORTER);
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @return bool
     */
    public static function deleteReporters($object_uuid)
    {
        return self::__deleteMembersFromUuid($object_uuid, self::MEMBER_TYPE_REPORTER);
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @return bool
     */
    public static function deleteOwner($object_uuid, $profile = null)
    {
        return self::__deleteMemberFromUuid($object_uuid, $profile, self::MEMBER_TYPE_OWNER);
    }

    /**
     * @param $object_uuid
     * @param $profile
     * @return bool
     */
    public static function deleteOwners($object_uuid, $companyId = null)
    {
        return self::__deleteMembersFromUuid($object_uuid, self::MEMBER_TYPE_OWNER, $companyId);
    }

    /**
     * @return bool
     */
    public static function checkMyViewPermission($object_uuid)
    {
        return self::checkViewPermissionOfUserByProfile($object_uuid, ModuleModel::$user_profile);
    }

    /**
     * @return bool
     */
    public static function checkMyEditPermission($object_uuid)
    {
        return self::checkEditPermissionOfUserByProfile($object_uuid, ModuleModel::$user_profile);

    }

    /**
     * @return bool
     */
    public static function checkMyDeletePermission($object_uuid)
    {
        return self::checkDeletePermissionOfUserByProfile($object_uuid, ModuleModel::$user_profile);
    }

    /**
     * @param $object_uuid
     * @param $user_profile
     * @return bool
     */
    public static function checkDeletePermissionOfUserByProfile($object_uuid, $user_profile)
    {
        if ($user_profile->getUserGroupId() == UserGroup::GROUP_GMS_ADMIN ||
            $user_profile->getUserGroupId() == UserGroup::GROUP_GMS_MANAGER
        ) {
            return true;
        }
        return self::checkDeletePermissionOfUserByUuid($object_uuid, $user_profile->getUuid());
    }

    /**
     * @param $object_uuid
     * @param $user_profile_uuid
     */
    public static function checkDeletePermissionOfUserByUuid($object_uuid, $user_profile_uuid)
    {
        $count = self::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    self::MEMBER_TYPE_OWNER,
                    self::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $object_uuid
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }


    /**
     * @return bool
     */
    public static function checkViewPermissionOfUserByProfile($object_uuid, $user_profile)
    {
        if ($user_profile->getUserGroupId() == UserGroup::GROUP_GMS_ADMIN ||
            $user_profile->getUserGroupId() == UserGroup::GROUP_GMS_MANAGER
        ) {
            return true;
        }
        return self::checkViewPermissionOfUserByUuid($object_uuid, $user_profile->getUuid());
    }

    /**
     * @param $object_uuid
     * @param $user_profile_uuid
     * @return bool
     */
    public static function checkViewPermissionOfUserByUuid($object_uuid, $user_profile_uuid)
    {
        $count = self::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: 
             AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    self::MEMBER_TYPE_INITIATOR,
                    self::MEMBER_TYPE_OWNER,
                    self::MEMBER_TYPE_VIEWER,
                    self::MEMBER_TYPE_ASSIGNEE,
                    self::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $object_uuid
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param $object_uuid
     * @param $user_profile_uuid
     * @return bool
     */
    public static function checkEditPermissionOfUserByProfile($object_uuid, $user_profile)
    {
        if ($user_profile->getUserGroupId() == UserGroup::GROUP_GMS_ADMIN ||
            $user_profile->getUserGroupId() == UserGroup::GROUP_GMS_MANAGER
        ) {
            return true;
        }
        return self::checkEditPermissionOfUserByUuid($object_uuid, $user_profile->getUuid());
    }

    /**
     * @param $object_uuid
     * @param $user_profile_uuid
     * @return bool
     */
    public static function checkEditPermissionOfUserByUuid($object_uuid, $user_profile_uuid)
    {
        $count = self::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    self::MEMBER_TYPE_OWNER,
                    self::MEMBER_TYPE_ASSIGNEE,
                    self::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $object_uuid
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param $object_uuid
     * @return bool
     */
    public static function __removeMember($object_uuid)
    {
        $paramsSearch = [
            'conditions' => 'object_uuid = :object_uuid:',
            'bind' => [
                'object_uuid' => $object_uuid
            ]
        ];
        $count = self::count($paramsSearch);
        if ($count > 0) {
            try {
                $res = self::find($paramsSearch)->delete();
                if ($res) {
                    return ['success' => true];
                } else {
                    return ['success' => false];
                }
            } catch (\PDOException $e) {
                return ['success' => false, 'detail' => $e->getMessage()];
            } catch (Exception $e) {
                return ['success' => false, 'detail' => $e->getMessage()];
            }
        } else {
            return ['success' => true, 'detail' => 'MEMBER_NOT_FOUND_TEXT'];
        }
    }


    /*
    **
    * @param $object_uuid
    * @return mixed
    */
    public static function getDataCreator($object_uuid)
    {

        $queryBuilder = new Builder();
        $queryBuilder->addFrom('Reloday\Gms\Models\DataUserMember', 'DataUserMember')
            ->innerJoin('Reloday\Gms\Models\UserProfile', 'DataUserMember.user_profile_id = UserProfile.id', 'UserProfile')
            ->where('DataUserMember.member_type_id = :member_type_id:')
            ->andWhere('DataUserMember.object_uuid = :object_uuid:')
            ->orderBy('DataUserMember.created_at DESC')
            ->limit(1);

        $bindArray = array();
        $bindArray['member_type_id'] = self::MEMBER_TYPE_INITIATOR;
        $bindArray['object_uuid'] = $object_uuid;

        $dataUserMembers = $queryBuilder->getQuery()->execute($bindArray);

        if ($dataUserMembers->count() > 0) {
            $dataUserMember = $dataUserMembers->getFirst();
            if ($dataUserMember) {
                return $dataUserMember->getUserProfile();
            }
        }
    }

    /**
     * @param $object_uuid
     * @return array
     */
    public static function __getAllMembersOfObjectWithRole($object_uuid)
    {
        $data = self::find([
            "conditions" => "object_uuid = :object_uuid: AND  company_id = :company_id:",
            "distinct" => true,
            "bind" => [
                'company_id' => ModuleModel::$company->getId(),
                'object_uuid' => $object_uuid,
            ]
        ]);
        $return = [];

        foreach ($data as $item) {
            if (!isset($return[$item->getUserProfileUuid()])) {
                $return[$item->getUserProfileUuid()] = $item->getUserProfile()->toArray();
            }
            $return[$item->getUserProfileUuid()]['roles'][] = intval($item->getMemberTypeId());
            $return[$item->getUserProfileUuid()]['principal_email'] = $item->getUserProfile()->getPrincipalEmail();
            $return[$item->getUserProfileUuid()]['is_active'] = $item->getUserProfile()->isActive();
            $return[$item->getUserProfileUuid()]['is_current_user'] = $item->getUserProfile()->getUuid() == ModuleModel::$user_profile->getUuid();
            $return[$item->getUserProfileUuid()]['is_viewer'] = isset($return[$item->getUserProfileUuid()]['is_viewer']) && $return[$item->getUserProfileUuid()]['is_viewer'] == true || $item->getMemberTypeId() == self::MEMBER_TYPE_VIEWER;
            $return[$item->getUserProfileUuid()]['is_owner'] = isset($return[$item->getUserProfileUuid()]['is_owner']) && $return[$item->getUserProfileUuid()]['is_owner'] == true || $item->getMemberTypeId() == self::MEMBER_TYPE_OWNER;
            $return[$item->getUserProfileUuid()]['is_reporter'] = isset($return[$item->getUserProfileUuid()]['is_reporter']) && $return[$item->getUserProfileUuid()]['is_reporter'] == true || $item->getMemberTypeId() == self::MEMBER_TYPE_REPORTER;
            $return[$item->getUserProfileUuid()]['is_creator'] = isset($return[$item->getUserProfileUuid()]['is_creator']) && $return[$item->getUserProfileUuid()]['is_creator'] == true || $item->getMemberTypeId() == self::MEMBER_TYPE_INITIATOR;
        }
        return $return;
    }

    /**
     * get user member by user_profile_uuid and object name
     * member_type_id = 2
     * @param $user_profile_uuid
     * @param $object_name (self::OBJECT_ASSIGNMENT, self::OBJECT_RELOCATION, ...)
     * @return array
     */
    public static function getAllObjectsByOwner($user_profile_uuid, $object_name = '')
    {

        $data = self::find([
            "conditions" => "user_profile_uuid = :user_profile_uuid: AND  member_type_id = :member_type_id:",
            "distinct" => true,
            "bind" => [
                'member_type_id' => self::MEMBER_TYPE_OWNER,
                'user_profile_uuid' => $user_profile_uuid,
            ]
        ]);

        return ['success' => true, 'data' => $data];
    }


    public static function __getDataCreator($object_uuid)
    {
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('Reloday\Gms\Models\DataUserMember', 'DataUserMember')
            ->innerJoin('Reloday\Gms\Models\UserProfile', 'DataUserMember.user_profile_id = UserProfile.id', 'UserProfile')
            ->where('DataUserMember.member_type_id = :member_type_id:')
            ->andWhere('DataUserMember.object_uuid = :object_uuid:')
            ->orderBy('DataUserMember.created_at DESC')
            ->limit(1);

        $bindArray = array();
        $bindArray['member_type_id'] = self::MEMBER_TYPE_INITIATOR;
        $bindArray['object_uuid'] = $object_uuid;

        $dataUserMembers = $queryBuilder->getQuery()->execute($bindArray);

        if ($dataUserMembers->count() > 0) {
            $dataUserMember = $dataUserMembers->getFirst();
            if ($dataUserMember) {
                return $dataUserMember->getUserProfile();
            }
        }else{
            return false;
        }
    }
}
