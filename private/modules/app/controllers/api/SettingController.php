<?php

namespace Reloday\App\Controllers\API;

use Phalcon\Mvc\Model\Resultset;
use \Reloday\App\Controllers\ModuleApiController;
use Reloday\App\Models\SupportedLanguage;
use Reloday\App\Models\Constant;
use Reloday\App\Models\Currency;
use Reloday\App\Models\ConstantTranslation;
use Reloday\App\Models\Nationality;
use Reloday\App\Models\Country;
use Reloday\App\Models\Timezone;
use Reloday\App\Models\TimezoneConfig;
use Reloday\App\Models\ZoneLang;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\LanguageCode;
use Reloday\Application\Models\CountryExt;
use Reloday\Application\Models\CountryTranslationExt;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Models\DocumentTypeExt;
use Reloday\Application\Models\SupportedLanguageExt;
use Reloday\Application\Models\UserSettingDefaultExt;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class SettingController extends ModuleApiController
{
    /**
     * @Route("/lang", paths={module="app"}, methods={"GET"}, name="app-lang-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

    }

    /**
     * load language configuration
     * @param string $lang [description]
     * @return [type]       [description]
     */
    public function i18nAction($lang = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $language = SupportedLanguage::findFirstByName($lang);
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
                                    $results[trim($constant->getName())] = trim($translation->getValue());
                                }
                            }
                        }
                    }
                }
            }

            $this->response->setJsonContent($results, JSON_ERROR_UTF8);
            return $this->response->send();

        } else {
            $this->response->setJsonContent(['success' => false]);
            return $this->response->send();
        }
    }

    /**
     * @return mixe
     */
    public function currenciesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $currenciesArray = [];
        $currencies = Currency::findByActive(Currency::CURRENCY_ACTIVE);
        foreach ($currencies as $currency) {
            $currenciesArray[$currency->getCode()] = [
                'code' => $currency->getCode(),
                'name' => $currency->getName(),
                'principal' => $currency->getPrincipal() == 1 ? true : false,
                'group' => $currency->getPrincipal() == 1 ? 'CURRENCY_PRINCIPAL_TEXT' : 'CURRENCY_OTHERS_TEXT'
            ];
        }
        $this->response->setJsonContent(['success' => true, 'data' => array_values($currenciesArray)]);
        return $this->response->send();
    }

    /**
     * @return mixe
     */
    public function nationalitiesAction($current_language = 'en')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $nationalitiesArray = [];
        $nationalities = Nationality::getAll();

        $langProfile = SupportedLanguageExt::__getLangData($current_language);
        if (!$langProfile) {
            $langProfile = SupportedLanguageExt::__getLangData();
            $current_language = $langProfile->getName();
        }
        foreach ($nationalities as $nationality) {
            $values_arr = [];
            $nationalityTranslation = $nationality->getTranslationByLanguage($current_language);
            $nationalitiesArray[] = [
                'code' => $nationality->getCode(),
                'value' => $nationalityTranslation->getValue()
            ];
        }
        $this->response->setJsonContent(['success' => true, 'data' => array_values($nationalitiesArray)]);
        return $this->response->send();
    }

    /**
     * @return mixe
     */
    public function getCountryIsoListAction($current_language = 'en')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $nationalitiesArray = [];
        $nationalities = Nationality::getAll();
        foreach ($nationalities as $nationality) {
            $values_arr = [];
            $nationalityTranslation = $nationality->getTranslationByLanguage($current_language);
            $nationalitiesArray[$nationality->getCode()] = [
                'code' => $nationality->getCode(),
                'name' => $nationalityTranslation->getValue()
            ];
        }
        $this->response->setJsonContent(['success' => true, 'data' => ($nationalitiesArray)]);
        return $this->response->send();
    }

    /**
     * @param string $current_language
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function countriesAction($current_language = 'en')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $cacheKey = "APP_SETTINGS_COUNTRIES_ARR_" . strtoupper($current_language);
       
        $countriesArray = $this->cache->get($cacheKey);
        if ($countriesArray === null) {
            $countriesArray = [];
            $langProfile = SupportedLanguageExt::__getLangData($current_language);
            if (!$langProfile) {
                $langProfile = SupportedLanguageExt::__getLangData();
                $current_language = $langProfile->getName();
            }
            $countries = Country::getAll();
            $countries->setHydrateMode(Resultset::HYDRATE_RECORDS);
            foreach ($countries as $country) {
                $countryTranslation = $country->getTranslationByLanguage($current_language);
                $countryItem = $country->toArray();
                if ($countryTranslation instanceof CountryTranslationExt) {
                    $countryItem["name"] = $countryTranslation->getValue();
                }
                $countriesArray[] = $countryItem;
            }
            $this->cache->save($cacheKey, $countriesArray);
        }

        $cacheKey = "APP_SETTINGS_ISO_COUNTRIES_ARR_" . strtoupper($current_language);
        $countriesIsoArray = $this->cache->get($cacheKey);
        if ($countriesIsoArray === null) {
            $countriesIsoArray = [];
            $countries = Country::getAll();
            $countries->setHydrateMode(Resultset::HYDRATE_RECORDS);
            foreach ($countries as $country) {
                $countriesIsoArray[$country->getCio()] = $country->toArray();
            }
            $this->cache->save($cacheKey, $countriesIsoArray);
        }

        $this->response->setJsonContent(['success' => true, 'data' => $countriesArray, 'iso' => $countriesIsoArray]);
        return $this->response->send();
    }

    /**
     *
     */
    public function getZoneLangListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $zone_langs = ZoneLang::find();
        $this->response->setJsonContent(['success' => true, 'data' => $zone_langs]);
        return $this->response->send();
    }

    /**
     * default server time zone
     * @return mixed
     */
    public function getServerTimeZoneAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->response->setJsonContent(['success' => true, 'data' => TimezoneConfig::DEFAULT_TIME_ZONE]);
        return $this->response->send();
    }

    /**
     *
     */
    public function getClientTimeZoneAction()
    {
        $this->checkAjaxGet();
        $date = new \DateTime();
        $tz = $date->getTimezone();
        $this->view->disable();
        $this->checkAjax('GET');
        $this->response->setJsonContent(['success' => true, 'data' => $tz]);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function timezonesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $timezones = Timezone::find([
            'cache' => [
                'key' => "_TIME_ZONE",
            ],
            'order' => 'utc'
        ]);
        $this->response->setJsonContent(['success' => true, 'data' => $timezones]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getSpokenLanguagesAction($language = 'en')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $languages = LanguageCode::getAllTranslation($language);
        $languagesArray = [];
        foreach ($languages as $key => $lang) {
            $languagesArray[] = ['code' => $key, 'name' => $lang];
        }
        $this->response->setJsonContent(['success' => true, 'data' => $languagesArray]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getSpokenLanguagesIsoListAction($language = 'en')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        if(!$language || $language == null || $language == 'null'){
            $language = 'en';
        }

        $language = Helpers::__removeStringQuote($language);

        $languages = LanguageCode::getAllTranslation($language);
        $languagesArray = [];
        foreach ($languages as $key => $lang) {
            $languagesArray[$key] = ['code' => $key, 'name' => $lang];
        }
        $this->response->setJsonContent(['success' => true, 'data' => $languagesArray]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getDependantRelationsAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $relations = DependantExt::$dependant_relations;
        $this->response->setJsonContent(['success' => true, 'data' => $relations]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAppCusesConfigAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        if (getenv('APP_CUES_JAVASCRIPT_SOURCE') == '') {
            $this->response->setJsonContent(['success' => false, 'data' => false]);
        } else {
            $this->response->setJsonContent(['success' => true, 'data' => getenv('APP_CUES_JAVASCRIPT_SOURCE')]);
        }
        return $this->response->send();
    }

    /**
     * Config language
     */
    public function getSystemLanguagesAction()
    {
        $this->checkAjax('GET');
        $this->view->disable();

        $languages = SupportedLanguageExt::find([
            'order' => 'name',
            'cache' => [
                'key' => '_SupportedLanguageExt_'
            ]
        ]);
        $languagesArray = [];
        foreach ($languages as $language) {
            $languagesArray[$language->getName()] = $language->toArray();
            $languagesArray[$language->getName()]['options'] = $language->getOptions();
        }
        $return = [
            'success' => true,
            'data' => ($languagesArray)
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function checkDomainAction()
    {

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDocumentTypeListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $documentTypes = DocumentTypeExt::getAll();
        $documentTypesArray = [];
        foreach ($documentTypes as $documentType) {
            $documentTypesArray[] = $documentType;
        }
        $this->response->setJsonContent(['success' => true, 'data' => $documentTypesArray]);
        return $this->response->send();
    }
}
