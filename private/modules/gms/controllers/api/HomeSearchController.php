<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Mvc\Model;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\EmployeeActivityHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayObjectMapHelper;


use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Gms\Models\HousingPropositionVisite;
use Reloday\Gms\Models\ListOrderSetting;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\PropertyType;
use Reloday\Gms\Help\Utils;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\AssignmentDestination;
use Reloday\Gms\Models\AssignmentType;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\HomeSearchRequest;
use Reloday\Gms\Models\HomeSearchSuggestedProperty;
use Reloday\Gms\Models\MemberType;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Property;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompanyHasServiceProvider;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\HousingProposition;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\SupportedLanguage;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class HomeSearchController extends BaseController
{

    /**
     * calendar events
     */
    public function getCalendarEventsAction($uuid)
    {

        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);

            if ($relocationServiceCompany && $relocationServiceCompany->belongsToGms() && $relocationServiceCompany->isActive() == true
            ) {

                $propositions = $relocationServiceCompany->getHousingProposition();
                $result = [];
                if ($propositions->count() > 0) {
                    foreach ($propositions as $proposition) {
                        if ($proposition->getLastVisite() && $proposition->isAccepted() && $proposition->getProperty()) {
                            $result[] = [
                                'uuid' => $proposition->getUuid(),
                                'title' => $proposition->getProperty()->getName() . " <br> " . $proposition->getProperty()->getNumber() . " <br/> " . $proposition->getLastVisite()->getTitle(),
                                'start' => (int)$proposition->getLastVisite()->getStart(),
                                'end' => (int)$proposition->getLastVisite()->getEnd(),
                                'employee_id' => $proposition->getEmployeeId(),
                                'property_detail' => "<p><strong>" . $proposition->getProperty()->getName() . "</strong><br/>" .
                                    $proposition->getProperty()->getAddress1() . "<br/>" . $proposition->getProperty()->getAddress2() . "<br/>" . $proposition->getProperty()->getTown() . "<br/>" . $proposition->getProperty()->getCountry()->getName() . "</p>",
                                'full_name' => $proposition->getEmployee()->getFirstname() . " " . $proposition->getEmployee()->getLastname()
                            ];
                        }
                    }
                }
                $return = ['success' => true, 'data' => $result];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Add list of property for suggest to employee
     */
    public function addSuggestAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND'];

        $employee_id = Helpers::__getRequestValue('employee_id');
        $property_id = Helpers::__getRequestValue('property_id');
        $property_uuid = Helpers::__getRequestValue('property_uuid');
        $relocation_id = Helpers::__getRequestValue('relocation_id');
        $relocation_service_company_id = Helpers::__getRequestValue('relocation_service_company_id');

        if ($relocation_service_company_id > 0) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstById($relocation_service_company_id);
            if ($relocationServiceCompany && $relocationServiceCompany->belongsToGms() &&
                $relocationServiceCompany->isActive() == true &&
                $relocationServiceCompany->getRelocationId() == $relocation_id &&
                $relocationServiceCompany->getRelocation() && $relocationServiceCompany->getRelocation()->getEmployeeId() == $employee_id
            ) {

                $property = Property::findFirstByUuid($property_uuid);
                if ($property && $property->belongsToGms()) {
                    $proposition = HousingProposition::findFirst([
                        'conditions' => 'relocation_service_company_id = :relocation_service_company_id: AND property_id = :property_id:',
                        'bind' => [
                            'property_id' => $property->getId(),
                            'relocation_service_company_id' => $relocationServiceCompany->getId(),
                        ]
                    ]);
                    if (!$proposition) {
                        $proposition = new HousingProposition();
                        $employee = $relocationServiceCompany->getRelocation()->getEmployee();
                        $dataInput = [
                            'employee_id' => $relocationServiceCompany->getRelocation()->getEmployeeId(),
                            'relocation_service_company_id' => $relocationServiceCompany->getId(),
                            'service_provider_company_id' => $property->getLandLordSvpId(),
                            'gms_company_id' => ModuleModel::$company->getId(),
                            'hr_company_id' => $employee->getCompanyId(),
                            'relocation_id' => $relocationServiceCompany->getRelocationId(),
                            'property_uuid' => $property->getUuid(),
                            'property_id' => $property->getId(),
                            'status' => HousingProposition::STATUS_TO_SUGGEST,
                            'is_visited' => HousingProposition::VISITED_NO,
                            'is_selected' => HousingProposition::SELECTED_NO,
                        ];
                        $proposition->setDataSnapshoot($dataInput);
                        $propositionResult = $proposition->__quickCreate();

                        if ($propositionResult['success'] == true) {

                            $check = ListOrderSetting::checkOrderSetting([
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'list_type' => ListOrderSetting::TYPE_PROPOSITION,
                                'object_type' => ListOrderSetting::OBJECT_TYPE_RELOCATION_SERVICE_COMPANY,
                                'id' => $proposition->getId()
                            ]);

                            $return = [
                                'success' => true,
                                'message' => 'PROPERTY_ADDED_TO_LIST_TEXT',
                                'input' => $dataInput,
                                'data' => $proposition,
                            ];
                        } else {
                            $return = $propositionResult;
                        }
                    } else {
                        if ($proposition->isDeleted() == true) {
                            $proposition->setIsDeleted(HousingProposition::IS_DELETED_NO);
                        } else {
                            $return = [
                                'success' => true,
                                'message' => 'PROPERTY_ALREADY_SUGGESTED_HERE_TEXT',
                                'data' => $proposition,
                            ];
                            goto end_of_function;
                        }
                        $proposition->setStatus(HousingProposition::STATUS_TO_SUGGEST);
                        $proposition->setDateVisite(null);
                        $proposition->setDateVisiteStart(null);
                        $proposition->setDateVisiteEnd(null);
                        $proposition->setNoteVisite(null);
                        $propositionResult = $proposition->__quickUpdate();

                        $check = ListOrderSetting::checkOrderSetting([
                            'uuid' => $relocationServiceCompany->getUuid(),
                            'list_type' => ListOrderSetting::TYPE_PROPOSITION,
                            'object_type' => ListOrderSetting::OBJECT_TYPE_RELOCATION_SERVICE_COMPANY,
                            'id' => $proposition->getId()
                        ]);

                        if ($propositionResult['success'] == true) {
                            $return = [
                                'success' => true,
                                'message' => 'PROPERTY_SUGGESTED_SUCCESS_TEXT',
                                'data' => $proposition,
                            ];
                        } else {
                            $return = $propositionResult;
                        }
                    }
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     */
    public function getSuggestInformationAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);


        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $data = $relocation_service_company->getSearchInfosData();
                $relocation = $relocation_service_company->getRelocation();
                if ($relocation) {
                    $assignmentDestination = $relocation->getAssignmentDestination();
                    if ($assignmentDestination) {
                        $data['country_id'] = (int)$assignmentDestination->getDestinationCountryId();
                        $data['city'] = $assignmentDestination->getDestinationCity();
                        $relocation_service_company->setSearchInfos(json_encode($data));
                        try {
                            $relocation_service_company->save();
                        } catch (\PDOException $e) {
                            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
                        } catch (Exception $e) {
                            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
                        }
                    }
                }
                $return = [
                    'success' => true,
                    'data' => $data,
                ];
                /*************** end get suggest information from relocation *************/
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_service_company_uuid
     */
    public function getHousingPropositionListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->isActive()
            ) {
                $relocation = $relocation_service_company->getRelocation();
                if ($relocation) {
                    $housingPropositionList = $relocation_service_company->getHousingProposition();
                    $proposition_arr = [];
                    $orderSetting = [];
                    if (count($housingPropositionList) > 0) {
                        foreach ($housingPropositionList as $k => $propositionItem) {
                            $property = $propositionItem->getProperty();
                            $orderSetting[] = intval($propositionItem->getId());
                            if ($property) {
                                $proposition_arr_item = $propositionItem->toArray();
                                if ($propositionItem->getLastVisite() && $propositionItem->isAccepted()) {
                                    $proposition_arr_item['has_visite'] = true;
                                } else {
                                    $proposition_arr_item['has_visite'] = false;
                                }
                                $proposition_arr_item['is_accepted'] = $propositionItem->isAccepted();
                                $proposition_arr_item['visite'] = $propositionItem->getLastVisite();
                                $proposition_arr_item['date_visite'] = $propositionItem->getLastVisite() ? $propositionItem->getLastVisite()->getStart() : null;
                                $proposition_arr_item['property_number'] = $property->getNumber();
                                $proposition_arr_item['property_name'] = $property->getName();
                                $proposition_arr_item['property_type'] = $property->getType();
                                $proposition_arr_item['property_size'] = $property->getSize();
                                $proposition_arr_item['property_size_unit'] = $property->getSizeUnit();
                                $proposition_arr_item['property_address1'] = $property->getAddress1();
                                $proposition_arr_item['property_address2'] = $property->getAddress2();
                                $proposition_arr_item['property_town'] = $property->getTown();
                                $proposition_arr_item['property_zipcode'] = $property->getZipcode();
                                $proposition_arr_item['property_country_name'] = $property->getCountry() ? $property->getCountry()->getName() : "";
                                $proposition_arr_item['property_rent_amount'] = $property->getRentAmount();
                                $proposition_arr_item['property_rent_period'] = $property->getRentPeriod();
                                $proposition_arr_item['property_rent_currency'] = $property->getRentCurrency();
                                $proposition_arr_item['property_number'] = $property->getNumber();
                                $proposition_arr_item['property'] = $property->getSummaryData();
                                $proposition_arr_item['comment_count'] = 0;
                                $objectMap = RelodayObjectMapHelper::__getObject($propositionItem->getUuid());
                                if ($objectMap) {
                                    $proposition_arr_item['comment_count'] = $objectMap->getCommentCount();
                                }

                                if ($property->getUuid()) {
                                    $file = MediaAttachment::__getMainThumb($property->getUuid());
                                    if (empty($file)) {
                                        $file = MediaAttachment::__getFirstImage($property->getUuid());
                                    }

                                    if (!empty($file)) {
                                        $proposition_arr_item['property']['image'] = $file['image_data']['url_thumb'];
                                    } else {
                                        $proposition_arr_item['property']['image'] = false;
                                    }

                                    $proposition_arr_item['property']['attachments'] = $property->getAttachments();
                                }
                                $proposition_arr[] = $proposition_arr_item;
                            } else {
                                continue;
                            }
                        }
                    }
                    $check = ListOrderSetting::checkOrderSetting([
                        'uuid' => $uuid,
                        'list_type' => ListOrderSetting::TYPE_PROPOSITION,
                        'object_type' => ListOrderSetting::OBJECT_TYPE_RELOCATION_SERVICE_COMPANY,
                        'order_setting' => json_encode($orderSetting)
                    ]);

                    if ($proposition_arr) {
                        $listOrderSetting = ListOrderSetting::findFirst(
                            [
                                'conditions' => 'object_type = :object_type: AND object_uuid = :object_uuid: AND list_type = :list_type:',
                                'bind' => [
                                    'object_type' => ListOrderSetting::OBJECT_TYPE_RELOCATION_SERVICE_COMPANY,
                                    'object_uuid' => $uuid,
                                    'list_type' => ListOrderSetting::TYPE_PROPOSITION,
                                ]
                            ]
                        );
                        $_order = json_decode($listOrderSetting->getOrderSetting());
                        if ($_order) {
                            usort($proposition_arr, function ($a, $b) use ($_order) {
                                $pos_a = array_search($a['id'], $_order);
                                $pos_b = array_search($b['id'], $_order);
                                return $pos_a - $pos_b;
                            });
                        }
                    }

                    $return = ['success' => true, 'data' => $proposition_arr];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_service_company_uuid
     */
    public function getHousingPropositionItemAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $propositionItem = HousingProposition::findFirstByUuid($uuid);

            if ($propositionItem->belongsToGms() == false) {
                goto end_of_function;
            }

            $property = $propositionItem->getProperty();
            if (!$property) {
                goto end_of_function;
            }

            $propositionItemData = $propositionItem->toArray();
            if ($propositionItem->getLastVisite() && $propositionItem->isAccepted()) {
                $propositionItemData['has_visite'] = true;
            } else {
                $propositionItemData['has_visite'] = false;
            }
            $propositionItemData['is_accepted'] = $propositionItem->isAccepted();
            $propositionItemData['visite'] = $propositionItem->getLastVisite();
            $propositionItemData['property_number'] = $property->getNumber();
            $propositionItemData['property_name'] = $property->getName();
            $propositionItemData['property_type'] = $property->getType();
            $propositionItemData['property_size'] = $property->getSize();
            $propositionItemData['property_size_unit'] = $property->getSizeUnit();
            $propositionItemData['property_address1'] = $property->getAddress1();
            $propositionItemData['property_address2'] = $property->getAddress2();
            $propositionItemData['property_town'] = $property->getTown();
            $propositionItemData['property_zipcode'] = $property->getZipcode();
            $propositionItemData['property_country_name'] = $property->getCountry()->getName();
            $propositionItemData['property_rent_amount'] = $property->getRentAmount();
            $propositionItemData['property_rent_period'] = $property->getRentPeriod();
            $propositionItemData['property_rent_currency'] = $property->getRentCurrency();
            $propositionItemData['property_number'] = $property->getNumber();
            $return = ['success' => true, 'data' => $propositionItemData];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function changeSelectedPropositionAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $relocation_service_company_uuid = Helpers::__getRequestValue('relocation_service_company_uuid ');
        $housing_proposition_uuid = Helpers::__getRequestValue('housing_proposition_uuid');

        if ($relocation_service_company_uuid == '' || !Helpers::__isValidUuid($relocation_service_company_uuid) &&
            $housing_proposition_uuid == '' && !Helpers::__isValidUuid($housing_proposition_uuid)
        ) {
            goto end_of_function;
        }

        $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
        if (!$relocation_service_company ||
            $relocation_service_company->belongsToGms() == false ||
            $relocation_service_company->isActive() == false
        ) {
            $return['message'] = "RELOCATION_NOT_FOUND_TEXT";
            goto end_of_function;
        }

        $housing_proposition = HousingProposition::findFirstByUuid($housing_proposition_uuid);

        if (!$housing_proposition ||
            $housing_proposition->belongsToGms() == false ||
            $housing_proposition->isDeleted() == true) {
            $return['message'] = "HOUSING_PROPOSITION_NOT_EXIST_TEXT";
            goto end_of_function;
        }

        if ($housing_proposition->isDeclined() == true) {
            $return['message'] = "PROPERTY_DECLINED_CANNOT_BE_FINAL_TEXT";
            goto end_of_function;
        }

        if ($housing_proposition->isSelected() == false) {

            $current_selected_housing_proposition = $relocation_service_company->getSelectedHousingProposition();
            if ($current_selected_housing_proposition instanceof HousingProposition && $current_selected_housing_proposition->getId() == $housing_proposition->getId()) {
                //nothing
                $return = ['success' => true, 'message' => 'CONFIRM_PROPERTY_SELECTED_SUCCESS_TEXT'];
            } else {
                if ($current_selected_housing_proposition instanceof HousingProposition) {
                    $this->db->begin();
                    $return = $current_selected_housing_proposition->setSelectedNo();
                    if ($return['success'] == false) {
                        $return['message'] = "CONFIRM_PROPERTY_SELECTED_FAIL_TEXT";
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $return = $housing_proposition->setSelectedYes();
                    if ($return['success'] == true) {
                        $this->db->commit();
                        $return['data'] = $housing_proposition;
                        $return['message'] = "CONFIRM_PROPERTY_SELECTED_SUCCESS_TEXT";
                    }
                } else {
                    $return = $housing_proposition->setSelectedYes();
                    if ($return['success'] == true) {
                        $return['data'] = $housing_proposition;
                        $return['message'] = "CONFIRM_PROPERTY_SELECTED_SUCCESS_TEXT";
                    } else {
                        $return['message'] = "CONFIRM_PROPERTY_SELECTED_FAIL_TEXT";
                    }
                }
            }
        } else {
            $return = ['success' => true, 'message' => 'CONFIRM_PROPERTY_SELECTED_SUCCESS_TEXT'];
        }


        end_of_function:
        if ($return['success'] == true) {
            ModuleModel::$housingProposition = $housing_proposition;
            $this->dispatcher->setParam('return', $return);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getSelectedPropertyAction($uuid)
    {
        $this->view->disable();

        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);


        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->isActive()
            ) {

                $property = $relocation_service_company->getSelectedProperty();

                if ($property instanceof Property) {
                    $data = $property->toArray();
                    $data['country_name'] = $property->getCountry() ? $property->getCountry()->getName() : null;
                    $data['landlord_name'] = $property->getLandlord() ? $property->getLandlord()->getName() : null;
                    $data['landlord_phone_number'] = $property->getLandlord() ? $property->getLandlord()->getPhone() : null;
                    $data['landlord_email'] = $property->getLandlord() ? $property->getLandlord()->getEmail() : null;
                    $data['agent_name'] = $property->getAgent() ? $property->getAgent()->getName() : null;
                    $data['description'] = $property->getPropertyData() ? $property->getPropertyData()->getDescription() : '';
                    $data['comments'] = $property->getPropertyData() ? $property->getPropertyData()->getComments() : '';
                    $data['attachments'] = $property->getAttachments();
                    $data['attachments'] = $property->getAttachments();

                    $proposition = $relocation_service_company->getSelectedHousingProposition() ? $relocation_service_company->getSelectedHousingProposition()->toArray() : [];
                    $proposition['property_name'] = $property->getName();
                    $proposition['property_number'] = $property->getNumber();

                    $return = [
                        'success' => true,
                        'data' => $data,
                        'proposition' => $proposition
                    ];
                } else {
                    $return = [
                        'success' => true,
                        'data' => []
                    ];
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function getSuggestedPropertiesAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);


        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->isActive()
            ) {

                $relocation = $relocation_service_company->getRelocation();

                if ($relocation) {
                    $properties = $relocation_service_company->getProperties();
                    $properties_arr = [];
                    if (count($properties) > 0) {
                        foreach ($properties as $k => $property) {
                            $properties_arr[$property->getId()] = $property->toArray();
                            $properties_arr[$property->getId()]['description'] = Helpers::limit_text($properties_arr[$property->getId()]['summary'], 100);
                            $file = MediaAttachment::__getLastAttachment($properties_arr[$property->getId()]['uuid']);
                            if (!empty($file))
                                $properties_arr[$property->getId()]['image'] = $file['image_data']['url_thumb'];
                            else
                                $properties_arr[$property->getId()]['image'] = '/resources/img/image_not_available.jpeg';
                        }
                    }
                    $return = ['success' => true, 'data' => $properties_arr];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function changeStatusHousingPropositionAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid ');
        $status = Helpers::__getRequestValue('status');
        if ($uuid != '' && Helpers::__isValidUuid($uuid) && is_numeric($status) && is_integer(intval($status))) {
            $housingProposition = HousingProposition::findFirstByUuid($uuid);
            if ($housingProposition &&
                $housingProposition->belongsToGms() &&
                $housingProposition->isDeleted() == false) {
                if ($housingProposition->getStatus() == $status && $status == HousingProposition::STATUS_SUGGESTED) {
                    $return = ['success' => true, 'message' => 'PROPERTY_ALREADY_SUGGESTED_HERE_TEXT'];
                    goto end_of_function_2;
                }

                $return = $housingProposition->changeStatus($status);

                if ($return['success'] == false) {
                    goto end_of_function;
                }

                $return['data'] = $housingProposition;
                $return['message'] = "CHANGE_STATUS_HOME_SEARCH_SUCCESS_TEXT";

                if ($housingProposition->isSuggested() == true) {
                    $return['message'] = "PROPERTY_SUGGESTED_SUCCESS_TEXT";
                }

                $return['data'] = $housingProposition->toArray();
                $return['data']['is_suggested'] = $housingProposition->isSuggested();
                $return['data']['is_accepted'] = $housingProposition->isAccepted();
                if ($housingProposition->isSuggested() == true) {
                    /********************************* begin:send mail ***************************************/
                    if ($return['success'] == true) {
                        /*
                        $relodayQueue = RelodayQueue::__getQueueSendMail();
                        $dataArray = [
                            'action' => "sendMail",
                            'email' => ($relocation) ? $relocation->getEmployee()->getWorkemail() : '',
                            'assignee_name' => ($relocation) ? $relocation->getEmployee()->getFullname() : '',
                            'relocation_number' => ($relocation) ? $relocation->getNumber() : '',
                            'url' => ($relocation) ? $relocation->getEmployee()->getMyHousingProposalsUrl() : '',
                            'templateName' => EmailTemplateDefault::SEND_PROPERTY,
                            'language' => ModuleModel::$system_language
                        ];
                        $relodayQueueResult = $relodayQueue->addQueue($dataArray);
                        $return['queueResult'] = $relodayQueueResult;
                        */
                    }
                    /********************************* end:send mail ***************************************/

                }
            }
        }
        end_of_function:
        /********************************* init:modulemodel ***************************************/

        if ($return['success'] == true) {

        }

        /********************************* begin:pusher ***************************************/
        if ($return['success'] == true) {
            $pushHelper = new PushHelper();
            $relocation = $housingProposition->getRelocation();
            $pushResult = $pushHelper->sendMessage($relocation->getEmployee()->getUuid(), PushHelper::EVENT_RELOAD_HOUSING_PROPOSITION, [
                'message' => 'EE_NEW_HOUSING_PROPOSAL_TEXT',
                'data' => $housingProposition->toArray()
            ]);
            $return['pushResult'] = $pushResult;

            ModuleModel::$housingProposition = $housingProposition;
            ModuleModel::$relocationServiceCompany = $housingProposition->getRelocationServiceCompany();
            $this->dispatcher->setParam('return', $return);
        }
        /********************************* end:pusher ***************************************/

        end_of_function_2:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getHomeSearchHousingPropositionItemAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid ');
        $status = Helpers::__getRequestValue('status');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $housing_proposition = HousingProposition::findFirstByUuid($uuid);
            if ($housing_proposition && $housing_proposition->belongsToGms() && $housing_proposition->isDeleted() == false
            ) {
                $housing_propositionArray = $housing_proposition->toArray();
                $property = $housing_proposition->getProperty();
                if ($property) {
                    $housing_propositionArray['property_name'] = $property->getName();
                    $housing_propositionArray['property_type'] = $property->getType();
                    $housing_propositionArray['property_size'] = $property->getSize();
                    $housing_propositionArray['property_rent_amount'] = $property->getRentAmount();
                    $housing_propositionArray['property_rent_period'] = $property->getRentPeriod();
                    $housing_propositionArray['property_rent_currency'] = $property->getRentCurrency();
                    $housing_propositionArray['property_number'] = $property->getNumber();
                    $return = ['success' => true, 'data' => $housing_proposition];
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function cancelSelectedPropertyAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid ');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company && $relocation_service_company->belongsToGms() && $relocation_service_company->isActive() == true
            ) {
                $selected_proposition = $relocation_service_company->getSelectedHousingProposition();
                if ($selected_proposition) {
                    $return = $selected_proposition->setSelectedNo();
                    if ($return['success'] == true) {
                        $return['message'] = 'CANCEL_SELECTED_PROPERTY_SUCCESS_TEXT';
                    }
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function deleteHousingPropositionAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($id != '' && Helpers::__checkId($id) && is_numeric($id) && $id > 0) {
            $housingProposition = HousingProposition::findFirstById($id);
            if ($housingProposition &&
                $housingProposition->belongsToGms() &&
                $housingProposition->isDeleted() == false) {

                if ($housingProposition->isSelected() == false) {
                    $return = $housingProposition->__quickRemove();

                    if ($return['success'] == false) {
                        goto end_of_function;
                    }

                    $return['data'] = $housingProposition;
                    $return['message'] = "DELETE_PROPOSITION_HOME_SEARCH_SUCCESS_TEXT";
                } else {
                    $return = ['success' => false, 'message' => 'CAN_NOT_DELETE_SELECTED_PROPOSITION_TEXT'];
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Add new visite event to Housing Proposition and delete all last visites
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addVisiteEventAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $housingProposition = HousingProposition::findFirstByUuid($uuid);
            if ($housingProposition &&
                $housingProposition->belongsToGms() &&
                $housingProposition->isDeleted() == false) {

                $eventTitle = Helpers::__getRequestValue('title');
                $eventStartAt = Helpers::__getRequestValue('startsAt');
                $eventEndAt = Helpers::__getRequestValue('endsAt');
                $eventTimeStart = Helpers::__getRequestValue('timeStart');
                $duration = Helpers::__getRequestValue('duration');
                $offset = Helpers::__getRequestValue('timezone_offset');

                if (!$eventStartAt) {
                    $return['message'] = 'ADD_VISITE_EVENT_FAIL_TEXT';
                    goto end_of_function;
                }

                if (!$offset){
                    $offset = ApplicationModel::__getTimezoneOffset();
                }
                if (!is_numeric($offset)) {
                    $offset = 0;
                }

                $eventStartAt = strtotime(date('Y-m-d', $eventStartAt + $offset * 60)) - ($offset * 60);

                $timeStartData = explode(":", $eventTimeStart);
                if (count($timeStartData) <= 1) {
                    $return['message'] = 'ADD_VISITE_EVENT_FAIL_TEXT';
                    goto end_of_function;
                }

                $eventStartAt += $timeStartData[0] * 3600 + $timeStartData[1] * 60;

                if ($duration <= 0) {
                    $return['message'] = 'ADD_VISITE_EVENT_FAIL_TEXT';
                    goto end_of_function;
                }
                $eventEndAt = $eventStartAt + $duration * 3600;

                $eventStartAtSecond = Helpers::__convertToSecond($eventStartAt);
                $eventEndAtSecond = Helpers::__convertToSecond($eventEndAt);

                $this->db->begin();
                $visites = $housingProposition->getHousingPropositionVisites();
                $return = ModelHelper::__quickRemoveCollection($visites);

                if ($return['success'] == false) {
                    $return['message'] = 'ADD_VISITE_EVENT_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }

                $visite = new HousingPropositionVisite();
                $visite->setHousingPropositionId($housingProposition->getId());
                $visite->setStart($eventStartAtSecond);
                $visite->setEnd($eventEndAtSecond);
                $visite->setVisitTime($eventTimeStart);
                $visite->setDuration($duration);
                $visite->setTitle($eventTitle);
                $visite->setTimezoneOffset($offset);

                $return = $visite->__quickSave();

                if ($return['success'] == true) {
                    $this->db->commit();
                    $return['data'] = $visite;
                    $return['property'] = $housingProposition->getProperty();
                    $return['property_visit_date_time'] = date('d/m/Y H:i:s', ($eventStartAtSecond + $offset * 60));
                    $return['message'] = 'ADD_VISITE_EVENT_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'ADD_VISITE_EVENT_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }
            }
        }
        end_of_function:
        if ($return['success'] == true && isset($housingProposition) && $housingProposition && isset($visite) && $visite) {
            ModuleModel::$property = $housingProposition->getProperty();
            ModuleModel::$assignment = $housingProposition->getAssignment();
            ModuleModel::$employee = ModuleModel::$assignment->getEmployee();
            ModuleModel::$housingVisiteEvent = $visite;
            ModuleModel::$housingProposition = $housingProposition;
            $this->dispatcher->setParam('return', $return);

        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Delete all visites events of Housing Proposition
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function cancelVisiteEventAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $housingProposition = HousingProposition::findFirstByUuid($uuid);
            if ($housingProposition &&
                $housingProposition->belongsToGms() &&
                $housingProposition->isDeleted() == false) {
                $visites = $housingProposition->getHousingPropositionVisites();
                ModuleModel::$housingVisiteEvent = $housingProposition->getLastVisite();
                $return = ModelHelper::__quickRemoveCollection($visites);
                if ($return['success'] == true) {
                    $return['housing_proposition'] = $housingProposition;
                    $return['message'] = 'CANCEL_VISITE_EVENT_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'CANCEL_VISITE_EVENT_FAIL_TEXT';
                }
            }
        }
        end_of_function:

        if ($return['success'] == true && isset($housingProposition) && $housingProposition) {
            ModuleModel::$property = $housingProposition->getProperty();
            ModuleModel::$assignment = $housingProposition->getAssignment();
            ModuleModel::$employee = ModuleModel::$assignment->getEmployee();
            ModuleModel::$housingProposition = $housingProposition;
            $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $propertyId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function checkPropertyAvailabilityAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $propertyId = Helpers::__getRequestValue('propertyId');
        $serviceId = Helpers::__getRequestValue('serviceId');
        $assigneeId = Helpers::__getRequestValue('assigneeId');

        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT',
            'redirect' => true
        ];
        if (!(is_numeric($propertyId) && $propertyId > 0)) {
            goto end_of_function;
        }
        $property = Property::findFirstById($propertyId);
        if (!($property instanceof Property && $property->belongsToGms() && $property->isDeleted() == false)) {
            goto end_of_function;
        }

        $lastOtherProposition = HousingProposition::__findFirstBySelectedProperty($property->getId(), $serviceId, $assigneeId);
        $lastActiveProposition = HousingProposition::__findFirstBySelectedService($property->getId(), $serviceId);

        $result = [
            'success' => true,
            'isSuggested' => $lastActiveProposition ? true : false,
            'isSelected' => $lastOtherProposition ? true : false,
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Add list of property for suggest to employee
     */
    public function sendSuggestNotificationAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND'];

        $serviceUuid = Helpers::__getRequestValue('uuid');

        if (Helpers::__isValidUuid($serviceUuid)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($serviceUuid);

            if ($relocationServiceCompany && $relocationServiceCompany->belongsToGms() && $relocationServiceCompany->isActive() == true
            ) {
                $relocation = $relocationServiceCompany->getRelocation();
                $relodayQueue = RelodayQueue::__getQueueSendMail();
                $return = ['success' => true];
                $dataArray = [
                    'action' => "sendMail",
                    'email' => ($relocation) ? $relocation->getEmployee()->getWorkemail() : '',
                    'assignee_name' => ($relocation) ? $relocation->getEmployee()->getFullname() : '',
                    'relocation_number' => ($relocation) ? $relocation->getNumber() : '',
                    'dsp_company_name' => ModuleModel::$company->getName(),
                    'url' => ($relocation) ? $relocation->getEmployee()->getMyHousingProposalsUrl() : '',
                    'templateName' => EmailTemplateDefault::SEND_PROPERTY,
                    'language' => ModuleModel::$system_language
                ];
                $relodayQueueResult = $relodayQueue->addQueue($dataArray);
                $return['queueResult'] = $relodayQueueResult;
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
