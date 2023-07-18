<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\HtmlToPDFHelper;
use Reloday\Application\Lib\HttpStatusCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\ObjectMapHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Currency;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Property;
use Reloday\Gms\Models\PropertyData;
use Reloday\Gms\Models\PropertyType;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType;
use Reloday\Gms\Models\ServiceProviderType;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\AttributesValue;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\UserSetting;
use Reloday\Gms\Module;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class PropertyController extends BaseController
{
    const LIMIT_PAGE = 10;

    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * Load list real-estate (properties)
     * @Route("/property", paths={module="gms"}, methods={"GET"}, name="gms-property-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();
        $this->view->disable();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        /****** start ****/
        $params['start'] = Helpers::__getRequestValue('start');

        $params['limit'] = Helpers::__getRequestValue('limit');

        $params['page'] = Helpers::__getRequestValue('page');

        /*** countries ****/
        $countries = Helpers::__getRequestValueAsArray('countries');
        $country_ids = [];
        if (is_array($countries) && count($countries) > 0) {
            foreach ($countries as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $country_ids[] = $item['id'];
                }
            }
        }
        $params['country_ids'] = $country_ids;

        /*** currencies ****/

        $currencies = Helpers::__getRequestValueAsArray('currencies');
        $currency_codes = [];
        if (is_array($currencies) && count($currencies) > 0) {
            foreach ($currencies as $item) {
                $item = (array)$item;
                if (isset($item['code'])) {
                    $currency_codes[] = $item['code'];
                }
            }
        }
        $params['currency_codes'] = $currency_codes;

        /*** property types ****/

        $property_types = Helpers::__getRequestValueAsArray('property_types');
        $property_type_codes = [];
        if (is_array($property_types) && count($property_types) > 0) {
            foreach ($property_types as $item) {
                $item = (array)$item;
                if (isset($item['code'])) {
                    $property_type_codes[] = $item['code'];
                }
            }
        }
        $params['property_type_codes'] = $property_type_codes;

        /*** type ****/

        $types = Helpers::__getRequestValueAsArray('types');
        $type_codes = [];
        if (is_array($types) && count($types) > 0) {
            foreach ($types as $item) {
                $item = (array)$item;
                if (isset($item['value'])) {
                    $type_codes[] = $item['value'];
                }
            }
        }
        $params['type_codes'] = $type_codes;

        /*** availabilities ****/

        $availabilities = Helpers::__getRequestValueAsArray('availabilities');
        $availability_codes = [];
        if (is_array($availabilities) && count($availabilities) > 0) {
            foreach ($availabilities as $item) {
                $item = (array)$item;
                if (isset($item['value'])) {
                    $availability_codes[] = $item['value'];
                }
            }
        }
        $params['availability_codes'] = $availability_codes;

        /*** furnished ****/

        $furnished_list = Helpers::__getRequestValueAsArray('furnished_list');
        $furnished_codes = [];
        if (is_array($furnished_list) && count($furnished_list) > 0) {
            foreach ($furnished_list as $item) {
                $item = (array)$item;
                if (isset($item['value'])) {
                    $furnished_codes[] = $item['value'];
                }
            }
        }
        $params['furnished_codes'] = $furnished_codes;

        //Landlord
        $landlords = Helpers::__getRequestValueAsArray('landlords');
        $landlord_ids = [];
        if (is_array($landlords) && count($landlords) > 0) {
            foreach ($landlords as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $landlord_ids[] = $item['id'];
                }
            }
        }
        $params['landlord_ids'] = $landlord_ids;

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        /*** search ****/
        $dataResult = Property::__findWithFilter($params, $ordersConfig);
        if ($dataResult['success'] == true) {
            $dataResult['recordsFiltered'] = ($dataResult['total_items']);
            $dataResult['recordsTotal'] = ($dataResult['total_items']);
            $dataResult['length'] = ($dataResult['total_items']);
            $dataResult['draw'] = Helpers::__getRequestValue('draw');
        }

        $this->response->setJsonContent($dataResult);
        return $this->response->send();
    }

    /**
     * Load data init for form
     */
    public function initAction()
    {

        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        // 1. Load country list
        $country_list = Country::find([
            'columns' => 'id, name',
            'order' => 'name'
        ]);

        // 2. Load type list
        $type_list = PropertyType::find(['order' => 'name']);

        // 3. load attribute list: PERIOD
        $period_list = [];
        $period_attribute = Attributes::findFirstByName('PERIOD');
        if ($period_attribute instanceof Attributes) {
            // Find period list from value of company first, if not, we will find in template default
            $period_list = AttributesValue::find('attributes_id = ' . $period_attribute->getId() . ' AND company_id=' . ModuleModel::$user_profile->getCompanyId());
            if (count($period_list) == 0) {
                $period_list = AttributesValue::find('attributes_id = ' . $period_attribute->getId());
            }
            if (count($period_list))
                $period_list = $period_list->toArray();
        }
        /*if ($this->request->get('estate') == 'building') {
            foreach (Property::$periods as $k => $value) {
                $period_list[] = [
                    'id' => $k,
                    'value' => $value
                ];
            }
        }*/

        // 4. Load provider with LANDLORD & REAL_ESTATE_AGENT
        $provider_type_list = ServiceProviderType::find([
            'name IN ("LANDLORD", "REAL_ESTATE_AGENT")'
        ]);
        $landlord_list = [];
        $real_estate_agent = [];
        if (count($provider_type_list)) {
            $land_lord_ids = [];
            $real_estate_ids = [];
            foreach ($provider_type_list as $item) {
                if ($item->getName() == 'LANDLORD') {
                    $provider_has_landlord = ServiceProviderCompanyHasServiceProviderType::find('service_provider_type_id=' . $item->getId());
                    if (count($provider_has_landlord)) {
                        foreach ($provider_has_landlord as $landlord) {
                            $land_lord_ids[$landlord->getServiceProviderCompanyId()] = $landlord->getServiceProviderCompanyId();
                        }
                    }
                } else {
                    $provider_has_real_estate = ServiceProviderCompanyHasServiceProviderType::find('service_provider_type_id=' . $item->getId());
                    if (count($provider_has_real_estate)) {
                        foreach ($provider_has_real_estate as $real_estate) {
                            $real_estate_ids[$real_estate->getServiceProviderCompanyId()] = $real_estate->getServiceProviderCompanyId();
                        }
                    }
                }
            }

            if (count($land_lord_ids)) {
                $landlord_list = ServiceProviderCompany::find('id IN (' . implode(',', $land_lord_ids) . ')');
                if (count($landlord_list)) {
                    $landlord_list = $landlord_list->toArray();
                }
            }

            if (count($real_estate_ids)) {
                $real_estate_agent = ServiceProviderCompany::find('id IN (' . implode(',', $real_estate_ids) . ')');
                if (count($real_estate_agent)) {
                    $real_estate_agent = $real_estate_agent->toArray();
                }
            }
        }

        // 5. Load currency list
        $currency_list = Currency::find([
            'order' => 'name'
        ]);

        echo json_encode([
            'success' => true,
            'country_list' => count($country_list) ? $country_list->toArray() : [],
            'type_list' => count($type_list) ? $type_list->toArray() : [],
            'period_list' => $period_list,
            'landlord_list' => $landlord_list,
            'real_estate_list' => $real_estate_agent,
            'currency_list' => count($currency_list) ? $currency_list->toArray() : []
        ]);
    }

    /**
     * Load detail information of property
     * @param $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT',
            'redirect' => true
        ];
        if (!(is_numeric($id) && $id > 0)) {
            if (Helpers::__isValidUuid($id) == false) {
                goto end_of_function;
            }
            $property = Property::findFirstByUuid($id);
        } else {
            $property = Property::findFirstById($id);
        }
        if (!($property instanceof Property && $property->belongsToGms() && $property->isDeleted() == false)) {
            goto end_of_function;
        }
        $property->setPrefillCurrency();
        $data = $property->toArray();

        $data['country_name'] = $property->getCountry() ? $property->getCountry()->getName() : null;
        $data['landlord_name'] = $property->getLandlord() ? $property->getLandlord()->getName() : null;
        $data['landlord_phone_number'] = $property->getLandlord() ? $property->getLandlord()->getPhone() : null;
        $data['landlord_email'] = $property->getLandlord() ? $property->getLandlord()->getEmail() : null;
        $data['agent_name'] = $property->getAgent() ? $property->getAgent()->getName() : null;

        $propertyData = $property->getPropertyData();
        $data['description'] = $propertyData ? $propertyData->getDescription() : null;
        $data['comments'] = $propertyData ? $propertyData->getComments() : null;
        $data['scrape_content'] = $propertyData ? json_decode($propertyData->getScrapeContent()) : null;
        $data['attachments'] = $property->getAttachments();

        $data['url_thumb'] = $property->getMainThumbUrl();
        $data['image'] = $property->getMainThumbUrl();

        $result = [
            'success' => true,
            'data' => $data,
            'propertyData' => $propertyData,
        ];
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save property action
     */
    public function saveAction()
    {
        $this->view->disable();
        $result = ['success' => true, 'message' => 'FUNCTION_NOT_FOUND_TEXT'];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createPropertyAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $property = new Property();
        $dataInput = Helpers::__getRequestValuesArray();
        $dataInput['company_id'] = ModuleModel::$company->getId();
        $dataInput['last_user_login_id'] = ModuleModel::$user_login->getId();
        if (isset($dataInput['uuid']) && Helpers::__isValidUuid($dataInput['uuid'])) {
            $property->setUuid($dataInput['uuid']);
        }

        if(isset($dataInput['description']) && $dataInput['description']){
            $dataInput['description'] = rawurldecode(base64_decode($dataInput['description']));
        }else{
            $dataInput['description'] = null;
        }


        if (isset($dataInput['show_url']) && $dataInput['show_url']) {
            $dataInput['show_url'] = Property::SHOW_URL;
        } else {
            $dataInput['show_url'] = Property::NOT_SHOW_URL;
        }

        $property->setData($dataInput);
        $property->setCompanyId(ModuleModel::$company->getId());
        $this->db->begin();
        $resultCreate = $property->__quickCreate();
        if ($resultCreate['success'] == false) {
            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => 'PROPERTY_CREATE_FAIL_TEXT',
                'detail' => $resultCreate['detail']
            ];
            goto end;
        }

        $propertyData = new PropertyData();
        $propertyData->setData([
            'property_id' => $property->getId(),
            'scrape_content' => Helpers::__getRequestValue('scrape_content'),
            'description' => $dataInput['description'],
            'comments' => Helpers::__getRequestValue('comments'),
        ]);

        $resultCreate = $propertyData->__quickCreate();
        if ($resultCreate['success'] == false) {
            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => 'PROPERTY_CREATE_FAIL_TEXT',
                'detail' => $resultCreate['detail']
            ];
            goto end;
        }
        $this->db->commit();
        $result = [
            'success' => true,
            'data' => $property,
            'message' => $property->getIsBuilding() == Property::IS_BUILDING ? 'BUILDING_CREATE_SUCCESS_TEXT' : 'PROPERTY_CREATE_SUCCESS_TEXT'
        ];
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Delete property
     */
    public function deleteAction($id = null)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT'
        ];

        if (is_numeric($id) && $id > 0) {
            $propertyModel = Property::findFirstById($id);
            if ($propertyModel instanceof Property && $propertyModel->belongsToGms()) {

                $result = $propertyModel->remove();

                if ($result['success'] == false) {
                    $result = [
                        'success' => false,
                        'message' => 'DELETE_PROPERTY_FAIL_TEXT'
                    ];
                } else {
                    $result = [
                        'success' => true,
                        'message' => 'DELETE_PROPERTY_SUCCESS_TEXT'
                    ];
                }
            }
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Delete multiple properties
     */
    public function deleteMultipleAction()
    {
        $this->checkAjaxPost();
        $this->checkAclDelete();

        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT'
        ];

        $uuids = Helpers::__getRequestValue('uuids');


        if (is_array($uuids) && count($uuids) > 0) {
            $this->db->begin();
            foreach ($uuids as $uuid) {
                $propertyModel = Property::findFirstByUuid($uuid);
                if ($propertyModel instanceof Property && $propertyModel->belongsToGms()) {
                    $result = $propertyModel->remove();

                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'DELETE_PROPERTY_FAIL_TEXT'
                        ];
                        goto end_of_function;
                    } else {
                        $result = [
                            'success' => true,
                            'message' => 'DELETE_PROPERTY_SUCCESS_TEXT'
                        ];
                    }
                }
            }
            $this->db->commit();
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Search Propertye
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public
    function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['number'] = Helpers::__getRequestValue('number');
        $params['nb_bedrooms'] = Helpers::__getRequestValue('nb_bedrooms');
        $params['nb_bathrooms'] = Helpers::__getRequestValue('nb_bathrooms');
        $params['environment'] = Helpers::__getRequestValue('environment');
        $params['type'] = Helpers::__getRequestValue('property_type');
        $params['city'] = Helpers::__getRequestValue('city');
        $params['country_id'] = Helpers::__getRequestValue('country_id');
        $params['property_type'] = Helpers::__getRequestValue('property_type');
        $params['rent_amount_min'] = Helpers::__getRequestValue('rent_amount_min');
        $params['rent_amount_max'] = Helpers::__getRequestValue('rent_amount_max');
        $params['rent_currency'] = Helpers::__getRequestValue('rent_currency');
        $params['is_building'] = Helpers::__getRequestValue('is_building');
        $params['is_furnished'] = Helpers::__getRequestValue('is_furnished');

        $params['have_car_port'] = Helpers::__getRequestValue('have_car_port');
        if (isset($params['have_car_port']) && $params['have_car_port']) {
            $params['have_car_port'] = 1;
        }
        $params['is_pet_accepted'] = Helpers::__getRequestValue('is_pet_accepted');
        if (isset($params['is_pet_accepted']) && $params['is_pet_accepted']) {
            $params['is_pet_accepted'] = 1;
        }
        $params['have_swimming_pool'] = Helpers::__getRequestValue('have_swimming_pool');
        if (isset($params['have_swimming_pool']) && $params['have_swimming_pool']) {
            $params['have_swimming_pool'] = 1;
        }

        $params['is_selected'] = Helpers::__getRequestValue('is_selected');
        $params['address'] = Helpers::__getRequestValue('address');
        $params['zipcode'] = Helpers::__getRequestValue('zipcode');
        $params['property_name'] = Helpers::__getRequestValue('property_name');
        $params['plot'] = Helpers::__getRequestValue('plot');
        $params['furnished_status'] = Helpers::__getRequestValue('furnished_status');
        $params['size'] = Helpers::__getRequestValue('size');
        $params['size_unit'] = Helpers::__getRequestValue('size_unit');
        $params['is_available'] = Helpers::__getRequestValue('is_available');
        $params['page'] = Helpers::__getRequestValue('page');
        /****** start ****/
        $params['start'] = Helpers::__getRequestValue('start');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['limit'] = Helpers::__getRequestValue('limit');
        /*** countries ****/
        $countries = Helpers::__getRequestValueAsArray('countries');
        $country_ids = [];
        if (is_array($countries) && count($countries) > 0) {
            foreach ($countries as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $country_ids[] = $item['id'];
                }
            }
        }
        $params['country_ids'] = $country_ids;

        /*** currencies ****/

        $currencies = Helpers::__getRequestValueAsArray('currencies');
        $currency_codes = [];
        if (is_array($currencies) && count($currencies) > 0) {
            foreach ($currencies as $item) {
                $item = (array)$item;
                if (isset($item['code'])) {
                    $currency_codes[] = $item['code'];
                }
            }
        }
        $params['currency_codes'] = $currency_codes;
        $params['is_selected'] = true;

        /*** search ****/
        $ordersConfig = Helpers::__getApiOrderConfig(Helpers::__getRequestValue('orders'));
        $dataResult = Property::__findWithFilter($params, $ordersConfig);
        if ($dataResult['success'] == true) {
            $dataResult['orders'] = $ordersConfig;
            $dataResult['totalItems'] = ($dataResult['total_items']);
            $dataResult['recordsFiltered'] = ($dataResult['total_items']);
            $dataResult['recordsTotal'] = ($dataResult['total_items']);
            $dataResult['length'] = ($dataResult['total_items']);
            $dataResult['totalPages'] = ($dataResult['total_items']);
            $dataResult['draw'] = Helpers::__getRequestValue('draw');
        }

        $this->response->setJsonContent($dataResult);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function loadByUrlAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex();

        $result = [
            'success' => true,
            'images' => [],
            'meta_description' => ''
        ];
        //TODO sur dung class HTML
        require_once __DIR__ . '/../../lib/simple_html_dom.php';

        $url = Helpers::__getRequestValue('url');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($url != '' && Helpers::__isUrl($url)) {

            try {
                $url_info = parse_url($url);
                $url_host = isset($url_info['host']) ? $url_info['scheme'] . '://' . $url_info['host'] : '';
                $url_sheme = $url_info['scheme'];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
                $output = curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                $output = "";
                goto end_of_function;
            }
            try {
                $data = str_get_html($output);

                $result['images'] = [];
                if (is_object($data)) {
                    $images = $data->find('img');
                    foreach ($images as $item) {
                        if ($item->src) {
                            if (Helpers::__isUrl($item->src)) {
                                $src = $item->src;
                            } elseif (preg_match('#^\/\/#', $item->src)) {
                                $src = $url_sheme . ":" . $item->src;
                            } else {
                                $src = $url_host . $item->src;
                            }
                            if (array_search($src, $result['images']) === false)
                                $result['images'][] = $src;
                        }
                    }
                    $dataTitle = $data->find('title', 0);
                    $result['meta_title'] = $dataTitle && is_object($dataTitle) && isset($dataTitle->plaintext) ? $dataTitle->plaintext : '';
                    $result['meta_description'] = '';

                    $description = $data->find('meta');
                    if (count($description)) {
                        foreach ($description as $des) {
                            $attr = $des->attr;
                            if (isset($attr['name'])) {
                                if ($attr['name'] == 'description') {
                                    $result['meta_description'] = isset($attr['content']) ? $attr['content'] : '';
                                    break;
                                }
                            }

                            if (isset($attr['property'])) {
                                if (preg_match('/description/', $attr['property']) !== false) {
                                    $result['meta_description'] .= isset($attr['content']) ? $attr['content'] : '';
                                }
                            }
                        }
                    }
                    $result['html'] = $output;
                    $result['success'] = true;
                    $result['message'] = "DATA_LOAD_SUCCESS_TEXT";

                } else {
                    $result['meta_title'] = '';
                    $result['images'] = [];
                    $result['meta_description'] = '';
                    $result['message'] = 'CAN_NOT_GET_INFORMATION_FROM_THIS_URL_TEXT';
                }
            } catch (\Exception $e) {
                $result['meta_title'] = '';
                $result['images'] = [];
                $result['meta_description'] = '';
                $result['message'] = 'CAN_NOT_GET_INFORMATION_FROM_THIS_URL_TEXT';
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getContentOfUrlAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        //$this->checkAclIndex();

        $url = Helpers::__getRequestValue('url');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($url != '' && Helpers::__isUrl($url)) {

            $url_info = parse_url($url);
            $url_host = isset($url_info['host']) ? $url_info['scheme'] . '://' . $url_info['host'] : '';
            $url_sheme = $url_info['scheme'];
            $output = Helpers::__urlGetContent($url);
            $result = [
                'success' => true,
                'data' => [
                    'host' => $url_host,
                    'scheme' => $url_sheme,
                    'html' => ($output),
                ]
            ];

            //var_dump($result); die();
        }
        $this->response->setJsonContent($result, JSON_UNESCAPED_UNICODE);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function simpleAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $propertiesArray = [];
        $properties = Property::find([
            'conditions' => 'company_id = :company_id: AND status <> :status_deleted:',
            'order' => 'updated_at DESC',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_deleted' => Property::STATUS_DELETED
            ]
        ]);

        foreach ($properties as $property) {
            $propertiesArray[] = [
                'id' => $property->getId(),
                'name' => $property->getName(),
                'uuid' => $property->getUuid(),
                'country_name' => $property->getCountry() ? $property->getCountry()->getName() : '',
                'number' => $property->getNumber()
            ];
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $propertiesArray,
        ]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function typesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $type_list = PropertyType::find(['order' => 'name']);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $type_list,
        ]);
        return $this->response->send();
    }

    /**
     * Save property action
     */
    public function updatePropertyAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $property = Property::findFirstByUuid($uuid);
            if ($property && $property->belongsToGms()) {

                $this->db->begin();
                $dataInput = Helpers::__getRequestValuesArray();
                $dataInput['show_url'] = isset($dataInput['show_url']) && $dataInput['show_url'] ? Property::SHOW_URL : Property::NOT_SHOW_URL;
                $dataInput['last_user_login_id'] = ModuleModel::$user_login->getId();
                $dataInput['car_port'] = isset($dataInput['car_port']) && $dataInput['car_port'] ? Helpers::YES :  Helpers::NO;
                $dataInput['pets'] = isset($dataInput['pets']) && $dataInput['pets'] ? Helpers::YES :  Helpers::NO;
                $dataInput['swimming_pool'] = isset($dataInput['swimming_pool']) && $dataInput['swimming_pool'] ? Helpers::YES :  Helpers::NO;
                $property->setData($dataInput);
                $resultUpdate = $property->__quickUpdate();

                if ($resultUpdate['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'PROPERTY_SAVE_FAIL_TEXT', 'detail' => $resultUpdate['detail']];
                    goto end_of_function;
                }

                $propertyData = $property->getPropertyData();
                if (!$propertyData) {
                    $propertyData = new PropertyData();
                    $propertyData->setPropertyId($property->getId());
                }
                $propertyData->setData([
                    'scrape_content' => Helpers::__getRequestValue('scrape_content'),
                    'description' => rawurldecode(base64_decode(Helpers::__getRequestValue('description'))),
                    'comments' => Helpers::__getRequestValue('comments'),
                ]);
                $resultUpdate = $propertyData->__quickSave();
                if ($resultUpdate['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'PROPERTY_SAVE_FAIL_TEXT', 'detail' => $resultUpdate['detail']];
                    goto end_of_function;
                }
                $this->db->commit();
                $return = ['success' => true, 'data' => $property, 'message' => 'PROPERTY_SAVE_SUCCESS_TEXT'];

            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * pre Create Option
     */
    public function preCreateAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclCreate();

        $settings = UserSetting::find([
            "conditions" => "user_profile_id = :id: AND user_setting_default_id IN ({user_setting_default_id:array})",
            "bind" => [
                "id" => ModuleModel::$user_profile->getId(),
                "user_setting_default_id" => UserSetting::PROPERTY_SETTINGS
            ]
        ]);


        $settingArray = [
            "uuid" => Helpers::__uuid(),
            "country_id" => "",
            "rent_period" => "",
            "size_unit" => ""
        ];


        if (count($settings) > 0) {
            foreach ($settings as $setting) {
                switch ($setting->getUserSettingDefaultId()) {
                    case UserSetting::TYPE_COUNTRY:
                        $settingArray["country_id"] = intval($setting->getValue());
                        break;
                    case UserSetting::TYPE_SURFACE_UNIT:
                        $settingArray["size_unit"] = intval($setting->getValue()) == 0 ? "" : intval($setting->getValue());
                        break;
                    case UserSetting::TYPE_RENT_PERIOD:
                        $settingArray["rent_period"] = $setting->getValue();
                        $settingArray["adv_rent_period"] = $setting->getValue();
                        break;
                    case UserSetting::TYPE_CURRENCY:
                        $settingArray["rent_currency"] = $setting->getValue();
                        $settingArray["deposit_currency"] = $setting->getValue();
                        $settingArray["furniture_deposit_currency"] = $setting->getValue();
                        $settingArray["utility_deposit_currency"] = $setting->getValue();
                        $settingArray["maintenance_fees_currency"] = $setting->getValue();
                        $settingArray["parking_fees_currency"] = $setting->getValue();
                        break;
                }
            }
        }

        $createNewObjectMap = RelodayObjectMapHelper::__createObject(
            $settingArray['uuid'],
            RelodayObjectMapHelper::TABLE_PROPERTY,
            false, $settingArray
        );

        $return = [
            'success' => true,
            'data' => $settingArray
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Save property action
     */
    public function getPropertySettingAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclCreate();

        $settings = UserSetting::find([
            "conditions" => "user_profile_id = :id: AND user_setting_default_id IN ({user_setting_default_id:array})",
            "bind" => [
                "id" => ModuleModel::$user_profile->getId(),
                "user_setting_default_id" => UserSetting::PROPERTY_SETTINGS
            ]
        ]);


        $settingArray = [
            "country_id" => "",
            "rent_period" => "",
            "size_unit" => ""
        ];


        if (count($settings) > 0) {
            foreach ($settings as $setting) {
                switch ($setting->getUserSettingDefaultId()) {
                    case UserSetting::TYPE_COUNTRY:
                        $settingArray["country_id"] = intval($setting->getValue());
                        break;
                    case UserSetting::TYPE_SURFACE_UNIT:
                        $settingArray["size_unit"] = intval($setting->getValue()) == 0 ? "" : intval($setting->getValue());
                        break;
                    case UserSetting::TYPE_RENT_PERIOD:
                        $settingArray["rent_period"] = $setting->getValue();
                        break;
                    case UserSetting::TYPE_CURRENCY:
                        $settingArray["rent_currency"] = $setting->getValue();
                        break;
                }
            }
        }
        $return = [
            'success' => true,
            'data' => $settingArray
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save property action
     */
    public function savePropertySettingAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $country_id = Helpers::__getRequestValue("country_id");
        $size_unit = Helpers::__getRequestValue("size_unit");
        $rent_period = Helpers::__getRequestValue("rent_period");
        $rent_currency = Helpers::__getRequestValue("rent_currency");

        $return = [
            "success" => true,
            "message" => "DATA_SAVE_SUCCESS_TEXT"
        ];

        if ($country_id) {
            $countrySetting = UserSetting::findFirst([
                'conditions' => 'user_profile_id = :user_profile_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                    'user_setting_default_id' => UserSetting::TYPE_COUNTRY,
                ]
            ]);
            if (!$countrySetting instanceof UserSetting) {
                $countrySetting = new UserSetting();
                $countrySetting->setUserSettingDefaultId(UserSetting::TYPE_COUNTRY);
                $countrySetting->setName(UserSetting::TYPE_COUNTRY_NAME);
                $countrySetting->setUserProfileId(ModuleModel::$user_profile->getId());
            }
            $countrySetting->setValue($country_id);
        }

        if ($size_unit) {
            $sizeSetting = UserSetting::findFirst([
                'conditions' => 'user_profile_id = :user_profile_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                    'user_setting_default_id' => UserSetting::TYPE_SURFACE_UNIT,
                ]
            ]);
            if (!$sizeSetting instanceof UserSetting) {
                $sizeSetting = new UserSetting();
                $sizeSetting->setUserSettingDefaultId(UserSetting::TYPE_SURFACE_UNIT);
                $sizeSetting->setName(UserSetting::TYPE_SURFACE_UNIT_NAME);
                $sizeSetting->setUserProfileId(ModuleModel::$user_profile->getId());
            }
            $sizeSetting->setValue($size_unit);
        }

        if ($rent_period) {
            $periodSetting = UserSetting::findFirst([
                'conditions' => 'user_profile_id = :user_profile_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                    'user_setting_default_id' => UserSetting::TYPE_RENT_PERIOD,
                ]
            ]);
            if (!$periodSetting instanceof UserSetting) {
                $periodSetting = new UserSetting();
                $periodSetting->setUserSettingDefaultId(UserSetting::TYPE_RENT_PERIOD);
                $periodSetting->setName(UserSetting::TYPE_RENT_PERIOD_NAME);
                $periodSetting->setUserProfileId(ModuleModel::$user_profile->getId());
            }
            $periodSetting->setValue($rent_period);
        }

        if ($rent_currency && Helpers::__isCurrency($rent_currency)) {
            $currencySetting = UserSetting::findFirst([
                'conditions' => 'user_profile_id = :user_profile_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                    'user_setting_default_id' => UserSetting::TYPE_CURRENCY,
                ]
            ]);
            if (!$currencySetting instanceof UserSetting) {
                $currencySetting = new UserSetting();
                $currencySetting->setUserSettingDefaultId(UserSetting::TYPE_CURRENCY);
                $currencySetting->setName(UserSetting::TYPE_CURRENCY_NAME);
                $currencySetting->setUserProfileId(ModuleModel::$user_profile->getId());
            }
            $currencySetting->setValue($rent_currency);
        }

        if (isset($currencySetting) || isset($countrySetting) || isset($periodSetting) || isset($sizeSetting)) {
            $this->db->begin();

            if (isset($countrySetting)) {
                $result = $countrySetting->__quickSave();
                if ($result['success'] != true) {
                    $this->db->rollback();
                    $return = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT",
                        "detail" => $result
                    ];
                    goto end_of_function;
                }
            }
            if (isset($sizeSetting)) {
                $result = $sizeSetting->__quickSave();
                if ($result['success'] != true) {
                    $this->db->rollback();
                    $return = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT",
                        "detail" => $result
                    ];
                    goto end_of_function;
                }
            }
            if (isset($periodSetting)) {
                $result = $periodSetting->__quickSave();
                if ($result['success'] != true) {
                    $this->db->rollback();
                    $return = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT",
                        "detail" => $result
                    ];
                    goto end_of_function;
                }
            }
            if (isset($currencySetting)) {
                $result = $currencySetting->__quickSave();
                if ($result['success'] != true) {
                    $this->db->rollback();
                    $return = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT",
                        "detail" => $result
                    ];
                    goto end_of_function;
                }
            }
            $this->db->commit();
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param int $invoice_id
     */
    public function downloadAction($id = 0)
    {
        // Load data
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT',
            'redirect' => true
        ];
        if (!(is_numeric($id) && $id > 0)) {
            goto end_of_function;
        }
        $property = Property::findFirstById($id);
        if (!($property instanceof Property && $property->belongsToGms() && $property->isDeleted() == false)) {
            goto end_of_function;
        }

        $contentHtml = $property->generatePrintHtml();
        $di = \Phalcon\DI::getDefault();
        HtmlToPDFHelper::$tempDir = $di->getShared('appConfig')->application->cacheDir;
        HtmlToPDFHelper::__generatePdfFromHTML($contentHtml);
        exit;

        end_of_function:
        if ($result['success']) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST, 'Bad Request');
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Get Last Image of Property
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getImageAction(string $uuid)
    {
        // Load data
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT',
            'redirect' => true
        ];

        if (Helpers::__isValidUuid($uuid)) {
            $property = Property::findFirstByUuid($uuid);
            if (!($property instanceof Property && $property->belongsToGms() && $property->isDeleted() == false)) {
                goto end_of_function;
            }

            $file = MediaAttachment::__getLastImage($property->getUuid());
            if (!empty($file)) {
                $result = ['success' => true, 'data' => $file, 'property' => $property->toArray()];
            } else {
                $result = ['success' => false, 'data' => null, 'property' => $property->toArray()];
            }
        }

        end_of_function:
        if ($result['success'] == false) {
            //$this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST, 'Bad Request');
        }
        $this->response->setJsonContent($result);
        return $this->response->send();


    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\ResponseInterface
     */

    public function getPropertyThumbAction($uuid){
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'PROPERTY_NOT_FOUND_TEXT',
            'redirect' => true
        ];

        if (Helpers::__isValidUuid($uuid)) {
            $property = Property::findFirstByUuid($uuid);
            if (!($property instanceof Property && $property->belongsToGms() && $property->isDeleted() == false)) {
                goto end_of_function;
            }

            $thumb = $property->getMainThumbUrl();
            $result = ['success' => true, 'data' => $thumb, 'attachments' => $property->getAttachments()];

        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
