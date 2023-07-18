<?php

namespace Reloday\Gms\Controllers\API;

use Aws\S3\Crypto\HeadersMetadataStrategy;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Application\Lib\ErrorHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HttpStatusCode;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contact;
use Reloday\Gms\Models\DataContactMember;
use Reloday\Gms\Models\Employee;
use \Reloday\Gms\Models\Relocation;
use \Reloday\Gms\Models\UserProfile;
use \Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ContactsController extends BaseController
{
    /**
     * [indexAction description]
     * @return [type] [description]
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => []]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function listAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $data = [];
        if (Helpers::__isValidUuid($uuid) == false) {
            goto end;
        }
        $data = Contact::__findByUuid($uuid);
        end:
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $data]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getHrContactsAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['isPagination'] = Helpers::__getRequestValue('isPagination');
        $params['company_ids'] = Helpers::__getRequestValue('company_ids');
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $return = Contact::__findHrContacts($params, $ordersConfig);
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getListByBookerAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $data = [];
        if (Helpers::__isValidUuid($uuid) == false) {
            goto end;
        }
        $booker = Company::__findBooker($uuid);
        if (!$booker || $booker->belongsToGms() == false) {
            goto end;
        }
        $return = Contact::__findByUuid($booker->getUuid());
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function searchContactAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        //$this->checkAclIndex();
        $uuid = Helpers::__getRequestValue('uuid');
        $query = Helpers::__getRequestValue('query');
        $limit = Helpers::__getRequestValue('limit');
        $start = Helpers::__getRequestValue('start');
        $bookerId = Helpers::__getRequestValue('bookerId');
        $hrCompanyId = Helpers::__getRequestValue('hrCompanyId');
        if ($bookerId && Helpers::__isValidId($bookerId)) {
            $booker = Company::__findBookerById($bookerId);
            if ($booker) {
                $uuid = $booker->getUuid();
            }
        }
        if ($hrCompanyId && Helpers::__isValidId($hrCompanyId)) {
            $hrCompany = Company::__findHrById($hrCompanyId);
            if ($hrCompany && $hrCompany->belongsToGms()) {
                $uuid = $hrCompany->getUuid();
            }
        }
        $resultSearch = Contact::__findWithFilter([
            'query' => $query,
            'uuid' => $uuid,
            'limit' => $limit,
            'start' => $start,
        ]);
        $resultSearch['$hrCompany'] = [
            'id' => $hrCompanyId,
            '$hrCompany' => isset($hrCompany) ? $hrCompany : false,
        ];
        $this->response->setJsonContent($resultSearch);
        return $this->response->send();
    }

    /**
     * create new contact
     * @return mixed
     */
    public function createContactAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $email = Helpers::__getRequestValue('email');
        $firstname = Helpers::__getRequestValue('firstname');
        $lastname = Helpers::__getRequestValue('lastname');
        $hrCompanyId = Helpers::__getRequestValue('hr_company_id');
        $bookerCompanyId = Helpers::__getRequestValue('booker_company_id');
        $companyId = $hrCompanyId ?? $bookerCompanyId;
        $objectUuid = Helpers::__getRequestValue('object_uuid');
        $contact = Contact::__findFirstByEmailCache($email);

        if ($contact) {
            $return = [
                'success' => false,
                'errorType' => ErrorHelper::CONTACT_IS_EXISTED,
                'data' => $contact,
                'message' => 'CONTACT_ALREADY_EXIST_TEXT'
            ];
            goto end_of_function;
        }


        $contact = new Contact();
        if (Helpers::__isValidId($companyId)) {
            $companyObject = Company::findFirstById($companyId);
            if (!$companyObject || !$companyObject->belongsToGms()) {
                $companyObject = false;
            } else {
                $objectUuid = $companyObject->getUuid();
            }
        }
        if (!isset($companyObject) || $companyObject == false) {
            if ($objectUuid && Helpers::__isValidUuid($objectUuid)) {
                $companyObject = Company::findFirstByUuidCache($objectUuid);
                if (!$companyObject || !$companyObject->belongsToGms() || (!$companyObject->isBooker() && !$companyObject->isHr())) {
                    $companyObject = false;
                }
            }
        }


        $this->db->begin();

        $paramsData = [
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'organisation' => Helpers::__getRequestValue('organisation'),
            'object_uuid' => $objectUuid,
            'company_id' => ModuleModel::$company->getId(),
        ];

        $contact->setData($paramsData);
        if (isset($companyObject) && $companyObject) {
            $contact->setObjectUuid($companyObject->getUuid());
            $contact->setOrganisation($companyObject->getName());
        }

        $result = $contact->__quickCreate();

        if ($result['success'] == false) {
            $this->db->rollback();
            $return = ['success' => false, 'message' => 'CONTACT_SAVE_FAIL_TEXT', 'detail' => $result, 'raw' => $paramsData];
            if (isset($result['detail']) && is_array($result['detail']) && count($result['detail'])) {
                $return['message'] = reset($result['detail']);
            }
            goto end_of_function;
        }

        if (isset($companyObject) && $companyObject) {
            $result = DataContactMember::__add([
                'object_uuid' => $companyObject->getUuid(),
                'object_id' => isset($companyObject) && $companyObject ? $companyObject->getId() : null,
                'object_source' => isset($companyObject) && $companyObject ? $companyObject->getSource() : null,
            ], $contact);
        }

        if ($result['success'] == false) {
            $this->db->rollback();
            $return = ['success' => false, 'message' => 'CONTACT_SAVE_FAIL_TEXT', 'detail' => $result, 'raw' => $paramsData];
            if (isset($result['detail']) && is_array($result['detail']) && count($result['detail'])) {
                $return['message'] = reset($result['detail']);
            }
            goto end_of_function;
        }

        $this->db->commit();
        $return = [
            'success' => true,
            'message' => 'CONTACT_SAVE_SUCCESS_TEXT',
            'data' => $contact,
            'companies' => isset($companyObject) && $companyObject ? [$companyObject] : null
        ];


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * create new contact
     * @return mixed
     */
    public function createContactFromAssigneeAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        //$this->checkAclIndex();

        $uuid = Helpers::__getRequestValue('uuid');

        $employee = Employee::findFirstByUuid($uuid);
        if (!$employee instanceof Employee) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $email = $employee->getWorkemail();

        $firstname = $employee->getFirstname();
        $lastname = $employee->getLastname();

        $contact = Contact::findFirstByEmail($email);

        if (!$contact) {
            $contact = new Contact();
            $paramsData = [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'company_id' => ModuleModel::$company->getId(),
                'creator_user_profile_id' => ModuleModel::$user_profile->getId()
            ];
            $contact->setData($paramsData);
            $result = $contact->__quickCreate();

            if ($result['success'] == true) {
                $return = ['success' => true, 'message' => 'CONTACT_SAVE_SUCCESS_TEXT', 'data' => $contact];
            } else {
                $return = ['success' => false, 'message' => 'CONTACT_SAVE_FAIL_TEXT', 'detail' => $result, 'raw' => $paramsData];
                if (isset($result['detail']) && is_array($result['detail']) && count($result['detail'])) {
                    $return['message'] = reset($result['detail']);
                }
            }
        } else {
            $return = ['success' => true, 'message' => 'CONTACT_ALREADY_EXIST_TEXT'];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * create new contact
     * @return mixed
     */
    public function createContactFromUserProfileAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        //$this->checkAclIndex();

        $uuid = Helpers::__getRequestValue('uuid');

        $user_profile = UserProfile::findFirstByUuid($uuid);
        if (!$user_profile instanceof UserProfile) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $email = $user_profile->getWorkemail();

        $firstname = $user_profile->getFirstname();
        $lastname = $user_profile->getLastname();

        $contact = Contact::findFirstByEmail($email);

        if (!$contact) {
            $contact = new Contact();
            $paramsData = [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'company_id' => ModuleModel::$company->getId(),
                'creator_user_profile_id' => ModuleModel::$user_profile->getId()
            ];
            $contact->setData($paramsData);
            $result = $contact->__quickCreate();

            if ($result['success'] == true) {
                $return = ['success' => true, 'message' => 'CONTACT_SAVE_SUCCESS_TEXT', 'data' => $contact];
            } else {
                $return = ['success' => false, 'message' => 'CONTACT_SAVE_FAIL_TEXT', 'detail' => $result, 'raw' => $paramsData];
                if (isset($result['detail']) && count($result['detail'])) {
                    $return['message'] = reset($result['detail']);
                }
            }

        } else {
            $return = ['success' => true, 'message' => 'CONTACT_ALREADY_EXIST_TEXT', 'data' => $contact];
        }


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * create new contact
     * @return mixed
     */
    public function updateContactAction(int $id)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        //$this->checkAclIndex();

        $email = Helpers::__getRequestValue('email');
        $firstname = Helpers::__getRequestValue('firstname');
        $lastname = Helpers::__getRequestValue('lastname');

        $contact = Contact::findFirstById($id);
        if ($contact && $contact->belongsToGms()) {
            $paramsData = [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'telephone' => Helpers::__getRequestValue('telephone'),
                'mobile' => Helpers::__getRequestValue('mobile'),
                'jobtitle' => Helpers::__getRequestValue('jobtitle'),
                'organisation' => Helpers::__getRequestValue('organisation'),
                'company_id' => ModuleModel::$company->getId(),
            ];
            $contact->setData($paramsData);
        }

        $result = $contact->__quickUpdate();
        if ($result['success'] == true) {
            $return = ['success' => true, 'message' => 'CONTACT_SAVE_SUCCESS_TEXT', 'data' => $contact];
        } else {
            $return = ['success' => false, 'message' => 'CONTACT_SAVE_FAIL_TEXT', 'detail' => $result];
            if (isset($return['detail']) && is_array($return['detail']) && count($return['detail'])) {
                $return['message'] = $return['detail']['message'] ? $return['detail']['message'] : 'CONTACT_SAVE_FAIL_TEXT';
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * create new contact
     * @return mixed
     */
    public function getContactByIdAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        if (Helpers::__isValidId($id)) {
            $contact = Contact::findFirstById($id);
        } else {
            $contact = false;
        }

        if ($contact && $contact->belongsToGms()) {
            $contactArray = $contact->toArray();
            $contactArray['companies'] = $contact->getLinkedCompanies();
            if ($contact->getHrCompany() && $contact->getHrCompany()->belongsToGms() && $contact->getHrCompany()->isHr()) {
                $contactArray['hr_company_id'] = (int)$contact->getHrCompany()->getId();
            }
            if ($contact->getBookerCompany() && $contact->getBookerCompany()->belongsToGms() && $contact->getBookerCompany()->isBooker()) {
                $contactArray['booker_company_id'] = (int)$contact->getBookerCompany()->getId();
            }
            $return = [
                'success' => true,
                'data' => $contactArray
            ];
        } else {
            $return = ['success' => false];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * create new contact
     * @return mixed
     */
    public function getContactAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        //$this->checkAclIndex();

        $email = Helpers::__getRequestValue('email');
        $id = Helpers::__getRequestValue('id');

        if (Helpers::__isValidId($id)) {
            $contact = Contact::findFirstById($id);
        } elseif (Helpers::__isEmail($email)) {
            $contact = Contact::findFirst([
                'conditions' => 'email = :email: AND company_id = :company_id:',
                'bind' => [
                    'email' => $email,
                    'company_id' => ModuleModel::$company->getId()
                ]
            ]);
        } else {
            $contact = false;
        }

        if ($contact && $contact->belongsToGms()) {
            $contactArray = $contact->toArray();
            $contactArray['companies'] = $contact->getLinkedCompanies();
            if ($contact->getHrCompany() && $contact->getHrCompany()->belongsToGms() && $contact->getHrCompany()->isHr()) {
                $contactArray['hr_company_id'] = (int)$contact->getHrCompany()->getId();
            }
            if ($contact->getBookerCompany() && $contact->getBookerCompany()->belongsToGms() && $contact->getBookerCompany()->isBooker()) {
                $contactArray['booker_company_id'] = (int)$contact->getBookerCompany()->getId();
            }
            $return = [
                'success' => true,
                'data' => $contactArray
            ];
        } else {
            $return = ['success' => false];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (Helpers::__isValidId($id) && $id > 0) {
            $contact = Contact::findFirstById($id);
            if ($contact && $contact instanceof Contact && $contact->belongsToGms() == true) {
                $return = $contact->__quickRemove();
                if ($return['success'] == true) {
                    $return['message'] = 'DATA_DELETED_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'DATA_DELETE_FAIL_TEXT';
                }
            }
            $return['data'] = $contact;
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addCompanyAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $contactId = Helpers::__getRequestValue('contact_id');
        $companyId = Helpers::__getRequestValue('company_id');

        if (Helpers::__isValidId($contactId) && $contactId > 0) {
            $contact = Contact::findFirstById($contactId);
            if ($contact && $contact instanceof Contact && $contact->belongsToGms() == true) {

                if (Helpers::__isValidId($companyId) && $companyId > 0) {
                    $company = Company::findFirstByIdCache($companyId);
                    if ($company && $company->belongsToGms()) {

                        $result = DataContactMember::__add([
                            'object_uuid' => $company->getUuid(),
                            'object_id' => isset($company) && $company ? $company->getId() : null,
                            'object_source' => isset($company) && $company ? $company->getSource() : null,
                        ], $contact);

                        if ($result['success'] == true) {
                            $return = ['success' => true, 'message' => 'COMPANY_ADDED_SUCCESS_TEXT', 'data' => $company, 'result' => $result];
                        } else {
                            $return = $result;
                        }
                    }
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function removeCompanyAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $contactId = Helpers::__getRequestValue('contact_id');
        $companyId = Helpers::__getRequestValue('company_id');

        if (Helpers::__isValidId($contactId) && $contactId > 0) {
            $contact = Contact::findFirstById($contactId);
            if ($contact && $contact instanceof Contact && $contact->belongsToGms() == true) {

                if (Helpers::__isValidId($companyId) && $companyId > 0) {
                    $company = Company::findFirstByIdCache($companyId);
                    if ($company && $company->belongsToGms()) {

                        $result = DataContactMember::__remove([
                            'object_uuid' => $company->getUuid(),
                            'object_id' => isset($company) && $company ? $company->getId() : null,
                            'object_source' => isset($company) && $company ? $company->getSource() : null,
                        ], $contact);

                        if ($result['success'] == true) {
                            $return = ['success' => true, 'message' => 'COMPANY_ADDED_SUCCESS_TEXT', 'data' => $company];
                        } else {
                            $return = $result;
                        }

                    }
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
