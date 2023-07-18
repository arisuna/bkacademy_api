<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Gms\Models\App;
use Reloday\Gms\Models\AppSetting;
use Reloday\Gms\Models\AppSettingGroupDefault;
use Reloday\Gms\Models\CompanySetting;
use Reloday\Gms\Models\CompanySettingDefault;
use Reloday\Gms\Models\CompanySettingGroup;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Currency;
use Reloday\Gms\Models\Timezone;
use Reloday\Gms\Models\TimezoneConfig;
use Reloday\Gms\Models\UserSettingDefault;
use Reloday\Gms\Models\ZoneLang;
use Reloday\Gms\Models\Constant;
use Reloday\Gms\Models\AttributesTemplate;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Acl;
use Reloday\Gms\Models\Nationality;
use Reloday\Gms\Models\NationalityTranslation;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\AttributesValue;
use Reloday\Gms\Models\AttributesValueTranslation;
use Reloday\Gms\Models\UserGroup;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\AppSettingDefault;
use Reloday\Gms\Models\DocumentType;

use Phalcon\Mvc\Url;
use Reloday\Application\Models\SubscriptionExt;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SettingController extends BaseController
{

    /**
     * @Route("/setting", paths={module="gms"}, methods={"GET"}, name="gms-setting-index")
     */
    public function indexAction()
    {

    }

    /**
     * init settings for APP
     * @return [type] [description]
     */
    public function initAction()
    {
        $this->checkAjaxGet();
        $zone_langs = ZoneLang::find();
        $currencies = Currency::getAll();
        $countries = Country::find();
        $languages = SupportedLanguage::find();
        //$constants          = Constant::find();
        $user_profile = ModuleModel::$user_profile;
        $company = ModuleModel::$company;
        $current_language = ModuleModel::$language;
        $user_groups = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_GMS . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);

        $svp_user_groups = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_SVP . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);

        $subscription = SubscriptionExt::findFirstByCompanyId($user_profile->getCompanyId());
        $list_controller_action = Acl::getTreeGms($subscription, $user_profile);

        $languagesArray = [];
        foreach ($languages as $language) {
            $languagesArray[$language->getName()] = $language->toArray();
            $languagesArray[$language->getName()]['options'] = $language->getOptions();
        }

        $currenciesArray = [];
        foreach ($currencies as $currency) {
            $currenciesArray[$currency->getCode()] = $currency->toArray();
            if ($currency->getPrincipal() == 1) {
                $currenciesArray[$currency->getCode()]['group'] = 'CURRENCY_PRINCIPAL_TEXT';
            } else {
                $currenciesArray[$currency->getCode()]['group'] = 'CURRENCY_OTHERS_TEXT';
            }
        }

        $zone_langsArray = [];
        foreach ($zone_langs as $zone_lang) {
            $zone_langsArray[$zone_lang->getCode()] = $zone_lang;
        }

        $countriesArray = [];
        foreach ($countries as $country) {
            $countriesArray[$country->getId()] = $country;
        }

        $attributes = Attributes::find();
        $attributes_array = [];
        foreach ($attributes as $attribute) {
            $values_arr = [];
            $attributeValuesTranslation = $attribute->getListValuesOfCompany($company->getId(), $current_language);
            foreach ($attributeValuesTranslation as $attributeValueTranslation) {
                $value_id = method_exists($attributeValueTranslation, 'getAttributesValueId') ? $attributeValueTranslation->getAttributesValueId() :
                    is_object($attributeValueTranslation) && method_exists($attributeValueTranslation, 'getId') ? $attributeValueTranslation->getId() : $attributeValueTranslation['id'];
                $values_arr[] = $attribute->getId() . "_" . $value_id;
            }
            $attributes_array[$attribute->getCode()] = $values_arr;
        }
        $nationalitiesArray = [];
        $nationalities = Nationality::getAll();

        foreach ($nationalities as $nationality) {
            $values_arr = [];
            $nationalityTranslation = $nationality->getTranslationByLanguage($current_language);
            $nationalitiesArray[] = [
                'code' => $nationality->getCode(),
                'value' => $nationalityTranslation->getValue()
            ];
        }


        $this->view->disable();
        $this->response->setJsonContent([
            'success' => true,
            'data' => [
                'nationalities' => $nationalitiesArray,
                'countries' => array_values($countriesArray),
                'zone_langs' => $zone_langsArray,
                'currencies' => array_values($currenciesArray),
                'languages' => $languagesArray,
                'attributes' => $attributes_array,
                'user' => $user_profile->toArray(),
                'controller_action_items' => $list_controller_action,
                'user_groups' => $user_groups->toArray(),
                'svp_user_groups' => $svp_user_groups->toArray(),
            ]
        ]);
        $this->response->send();
    }

    /**
     * set default language
     */
    public function setLanguageAction()
    {
        $this->checkAjax('PUT');
        $this->view->disable();

        $value = Helpers::__getRequestValue('value');
        if ($value == '' || !in_array($value, SupportedLanguage::$languages)) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        } else {
            $result = ModuleModel::$app->saveAppSetting(AppSetting::LANGUAGE, $value);
            if ($result['success'] == true) {
                $return = [
                    'success' => true,
                    'message' => 'SAVE_SETTING_SUCCESS_TEXT',
                    'data' => $result['data'],
                ];
            } else {
                $return = $result;
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * Config language
     */
    public function languageAction()
    {
        $this->checkAjax('GET');
        $this->view->disable();

        $languages = SupportedLanguage::__findWithCache(null, CacheHelper::__TIME_6_MONTHS);
        $languagesArray = [];
        foreach ($languages as $language) {
            $languagesArray[$language->getName()] = $language->toArray();
            $languagesArray[$language->getName()]['options'] = $language->getOptions();
        }
        $return = [
            'success' => true,
            'current' => ModuleModel::$user_profile->getUserSettingValue(UserSettingDefault::DISPLAY_LANGUAGE) != '' ?
                ModuleModel::$user_profile->getUserSettingValue(UserSettingDefault::DISPLAY_LANGUAGE) : SupportedLanguage::LANG_EN,
            'data' => ($languagesArray)
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * generate lang
     * @return [type] [description]
     */

    public function i18nAction($lang = '')
    {

        $this->view->disable();

        $this->checkAjaxGet();

        $company = ModuleModel::$company;
        if ($lang == '') {
            $lang = ModuleModel::$language;
        }
        $language = SupportedLanguage::findFirstByName($lang);

        $cacheKey = "I18N_" . $company->getId() . "_" . $lang;

        $results = $this->cache->get($cacheKey);

        if ($results === null) {
            if ($language) {
                $constants = Constant::find();
                $results = array();
                if (count($constants)) {
                    foreach ($constants as $constant) {
                        if (trim($constant->getName()) != "") {
                            $results[trim($constant->getName())] = trim($constant->getName());
                            $translations = $constant->getConstantTranslation("language = '" . $language->getName() . "'");
                            if (count($translations)) {
                                foreach ($translations as $translation) {
                                    if ($translation->getLanguage() == $language->getName()) {
                                        if (method_exists($translation, "getValue"))
                                            $results[trim($constant->getName())] = trim($translation->getValue());
                                        else
                                            $results[trim($constant->getName())] = trim($constant->getName());
                                    }
                                }
                            }
                        }
                    }
                }

                ///get attributesù
                $results['ATTRIBUTES'] = [];

                $attributes = Attributes::find();
                foreach ($attributes as $attribute) {
                    $values_arr = [];
                    $attributeValuesTranslation = $attribute->getListValuesOfCompany($company->getId(), $language->getName());
                    foreach ($attributeValuesTranslation as $attributeValueTranslation) {

                        $value_id = method_exists($attributeValueTranslation, 'getAttributesValueId') ? $attributeValueTranslation->getAttributesValueId() :
                            is_object($attributeValueTranslation) && method_exists($attributeValueTranslation, 'getId') ? $attributeValueTranslation->getId() : $attributeValueTranslation['id'];

                        if (method_exists($attributeValueTranslation, "getValue")) {

                            $values_arr[$attribute->getId() . "_" . $value_id] = $attributeValueTranslation->getValue();
                        } elseif (isset($attributeValueTranslation['value'])) {
                            $values_arr[$attribute->getId() . "_" . $value_id] = $attributeValueTranslation['value'];
                        }
                    }
                    $results['ATTRIBUTES'][$attribute->getCode()] = $values_arr;
                }

                $results['NATIONALITIES'] = [];

                $nationalities = Nationality::getAll();

                foreach ($nationalities as $nationality) {
                    $values_arr = [];
                    $nationalityTranslation = $nationality->getTranslationByLanguage($language->getName());
                    $results['NATIONALITIES'][$nationality->getCode()] = $nationalityTranslation->getValue();
                }

                $this->cache->save($cacheKey, $results, getenv('CACHE_TIME'));

                $this->response->setJsonContent($results, JSON_ERROR_UTF8);
                return $this->response->send();

            } else {
                $this->response->setJsonContent(['success' => false]);
                return $this->response->send();
            }
        } else {
            $this->response->setJsonContent($results, JSON_ERROR_UTF8);
            return $this->response->send();
        }
    }

    /**
     * @param string $lang
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attributesAction($lang = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();


        $language = SupportedLanguage::findFirstByName($lang);
        $return = ['success' => false, 'message' => 'LANGUAGE_NOT_FOUND_TEXT'];
        $results = [];
        if ($language) {
            $results = Attributes::__getAllAttributesByCompany(ModuleModel::$company->getId(), $language->getName());
            $return = ['success' => true, 'data' => $results];
        }

        $this->response->setJsonContent($return, JSON_ERROR_UTF8);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function reloadAttributesAction($lang)
    {
        /**
         * @todo use cache phalcon
         */
        $this->checkAjaxPutGet();
        $this->view->disable();
        if ($lang == '') $lang = ModuleModel::$language;
        $language = SupportedLanguage::findFirstByName($lang);
        $return = ['success' => false, 'message' => 'LANGUAGE_NOT_FOUND_TEXT'];
        $results = [];
        if ($language) {
            $results = Attributes::__getAllAttributesByCompany(ModuleModel::$company->getId(), $language->getName());
            $return = ['success' => true, 'data' => $results];
        }

        $this->response->setJsonContent($return, JSON_ERROR_UTF8);
        return $this->response->send();
    }

    /**
     * @param $lang
     */
    public function user_groupsAction($lang)
    {
        $this->checkAjaxGet();
        $this->view->disable();
        $user_groups = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_GMS . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        $return = ['success' => true, 'data' => $user_groups];
        $this->response->setJsonContent($return, JSON_ERROR_UTF8);
        return $this->response->send();
    }


    /**
     * @param $lang
     */
    public function svp_user_groupsAction($lang)
    {
        $this->checkAjaxGet();
        $this->view->disable();
        $user_groups = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_SVP . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        $return = ['success' => true, 'data' => $user_groups];
        $this->response->setJsonContent($return, JSON_ERROR_UTF8);
        return $this->response->send();
    }

    /**
     * get pusher variables and AMAZON
     */
    public function getSettingVariablesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $variables = [
            'pusher_app_id' => getenv('PUSHER_APP_ID'),
            'pusher_app_key' => getenv('PUSHER_APP_KEY'),
            'pusher_app_secret' => getenv('PUSHER_APP_SECRET'),
            'pusher_app_cluster' => getenv('PUSHER_APP_CLUSTER')
        ];
        $settingAppList = ModuleModel::$company->getCompanySettingValues();
        $settingAppList = array_merge($settingAppList, $variables);
        $this->response->setJsonContent(['success' => true, 'data' => $settingAppList], JSON_ERROR_UTF8);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getSettingGroupAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $settingGroups = CompanySettingGroup::find([
            'conditions' => 'visible = :visible:',
            'bind' => [
                'visible' => CompanySettingGroup::STATUS_ACTIVE,
            ],
            'order' => 'position ASC'
        ]);
        $settingGroupsArray = [];
        foreach ($settingGroups as $groupItem) {
            $group = $groupItem->toArray();
            $group['settings'] = $groupItem->getSettingList();
            if (count($group['settings']) == 0) {
                $group['visible'] = false;
            } else {
                $group['visible'] = ($groupItem->getVisible() == 1);
            }
            $settingGroupsArray[$groupItem->getName()] = $group;
        }
        $this->response->setJsonContent(['success' => true, 'data' => array_values($settingGroupsArray)], JSON_ERROR_UTF8);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function getSettingListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $settings = ModuleModel::$company->getCompanySetting();
        $this->response->setJsonContent(['success' => true, 'data' => array_values($settings)], JSON_ERROR_UTF8);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getPermissionsListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $user_login = ModuleModel::$user_login;
        $permissions = $user_login->loadListPermission();
        $result = ['success' => true, 'data' => $permissions];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function getMenuItemsAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $user_login = ModuleModel::$user_login;
        $menus = $user_login->loadListMenu();
        $result = ['success' => true, 'data' => $menus];
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * save action
     * @return mixed
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl("index", "my_company");

        $name = Helpers::__getRequestValue('name');
        $value = Helpers::__getRequestValue('value');
        $company_setting_default_id = Helpers::__getRequestValue('company_setting_default_id');

        if ($company_setting_default_id > 0) {
            $companyConfigDefault = CompanySettingDefault::findFirstById($company_setting_default_id);
        } else {
            $companyConfigDefault = CompanySettingDefault::findFirstByName($name);
        }


        $return = ['success' => false, 'DATA_NOT_FOUND_TEXT'];

        if ($companyConfigDefault) {
            $companySetting = CompanySetting::findFirst([
                'conditions' => 'company_id = :company_id: AND company_setting_default_id = :company_setting_default_id:',
                'bind' => [
                    'company_id' => ModuleModel::$company->getId(),
                    'company_setting_default_id' => $companyConfigDefault->getId(),
                ]
            ]);

            if (!$companySetting) {
                $companySetting = new CompanySetting();
            }

            if ($value != $companySetting->getValue()) {
                $data = [
                    'company_id' => ModuleModel::$company->getId(),
                    'company_setting_default_id' => $companyConfigDefault->getId(),
                    'value' => $value,
                    'name' => $companyConfigDefault->getName(),
                ];
                $companySetting->setData($data);
                $resultAppSetting = $companySetting->__quickSave();


                if ($resultAppSetting['success'] == true) {
                    $return = ['success' => true, 'message' => $companyConfigDefault->getMessageSuccess(), "data" => $companySetting];
                } else {
                    $return = ['success' => false, 'message' => $companyConfigDefault->getMessageError(), 'detail' => $resultAppSetting];
                }
            } else {
                $return = ['success' => true, 'message' => 'NO_CHANGES_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * default server time zone
     * @return mixed
     */
    public function getServerTimeZoneAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->response->setJsonContent(['success' => true, 'data' => TimezoneConfig::DEFAULT_TIME_ZONE]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function timezonesAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $timezones = Timezone::find([
            'cache' => [
                'key' => "_TIME_ZONE"
            ]
        ]);
        $this->response->setJsonContent(['success' => true, 'data' => $timezones]);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getTimeZoneAction($id)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $timezone = Timezone::__findFirstByIdWithCache($id);
        if ($timezone) {
            $this->response->setJsonContent(['success' => true, 'data' => $timezone]);
        } else {
            $this->response->setJsonContent(['success' => false, 'data' => null]);
        }
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function checkDomainAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $calledUrl = RelodayUrlHelper::__getCalledUrl();
        $parse_called_url = parse_url($calledUrl);
        $host_called_url = isset($parse_called_url["host"]) ? $parse_called_url["host"] : '';
        $appUrl = ModuleModel::$app->getAppSubdomain();


        $data = [
            'calledUrl' => RelodayUrlHelper::__getCalledUrl(),
            'appSubDomain' => ModuleModel::$app->getAppSubdomain(),
            'systemMainDomain' => RelodayUrlHelper::__getMainDomain(),
            'appFrontendUrl' => ModuleModel::$app->getAppFrontendUrl(),
            'mainAppFrontendUrl' => ModuleModel::$app->getFrontendUrl(),
        ];

        $redirect = $this->getDI()->get('appConfig')->application->needRedirectAfterLogin == true ? $host_called_url !== $appUrl : false;
        if ($redirect == true) {
            $data['redirect'] = true;
        }

        $this->response->setJsonContent(['success' => true, 'data' => $data]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDocumentTypeListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $documentTypes = DocumentType::getAll();
        $documentTypesArray = [];
        foreach ($documentTypes as $documentType) {
            $documentTypesArray[] = $documentType;
        }
        $this->response->setJsonContent(['success' => true, 'data' => $documentTypesArray]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCountriesByIdsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        $countries = Country::find([
            'conditions' => 'id IN ({country_ids:array})',
            'bind' => [
                'country_ids' => $ids
            ],
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $countries
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getZoneLangItemAction()
    {
        $this->checkAjaxGet();
        $code = Helpers::__getRequestValue('code');
        $zoneLang = ZoneLang::__findFirstByCodeWithCache($code);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $zoneLang
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCountryAction($isoId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $country = [];
        if (Helpers::__isValidId($isoId)) {
            $country = Country::__findFirstByIdWithCache($isoId, CacheHelper::__TIME_24H);
        } else {
            $country = Country::__findFirstByIsoCodeWithCache($isoId, CacheHelper::__TIME_24H);
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $country
        ]);
        return $this->response->send();
    }
}
