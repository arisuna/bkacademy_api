<?php

namespace SMXD\App\Controllers\API;

use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\CountryExt;
use SMXD\Application\Models\CountryTranslationExt;
use SMXD\Application\Models\SupportedLanguageExt;
use SMXD\App\Models\Country;

class CountryController extends BaseController
{
    /**
     * @Route("/country", paths={module="app"}, methods={"GET"}, name="app-country-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $query = Helpers::__getRequestValue('query');
        $param = null;

        if (isset($query) && is_string($query) && $query != '') {
            $param = ['conditions' => 'name LIKE :query: OR label LIKE :query: OR cio LIKE :query: OR id LIKE :query: OR capital LIKE :query: OR phone LIKE :query: ',
                'bind' => [
                    'query' => '%' . $query . '%'
                ]];
        }

        $list = Country::find($param);
        $this->response->setJsonContent([
            'success' => true,
            'data' => count($list) ? $list->toArray() : []
        ]);

        end:
        $this->response->send();
    }

    public function detailAction($id = 0)
    {
        $this->view->disable();

        $data = Country::findFirst((int)$id);
        $data = $data instanceof Country ? $data->toArray() : ['id' => 0];

        // Load list supported language
        $supportedLanguages = SupportedLanguageExt::find();

        $countryTranslations = CountryTranslationExt::find('country_id=' . $data['id']);
        $_tmp = [];
        if (count($countryTranslations)) {
            foreach ($countryTranslations as $countryTranslation) {
                $_tmp[$countryTranslation->getSupportedLanguageId()] = $countryTranslation->getValue();
            }
        }
        $countryTranslations = $_tmp;

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data,
            'supportedLanguages' => $supportedLanguages,
            'countryTranslations' => $countryTranslations
        ]);

        end:
        $this->response->send();

    }

    public function createAction()
    {
        $this->view->disable();

        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    public function updateAction()
    {
        $this->view->disable();

        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    public function __save()
    {
        $country = new Country();
        if ((int)Helpers::__getRequestValue('id') > 0) {
            $country = Country::findFirst((int)Helpers::__getRequestValue('id'));
            if (!$country instanceof Country) {
                exit(json_encode([
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ]));
            }
        }

        $country->setCio(Helpers::__getRequestValue('cio'));
        $country->setName(Helpers::__getRequestValue('name'));
        $country->setPhone(Helpers::__getRequestValue('phone'));
        $country->setCapital(Helpers::__getRequestValue('capital'));
        $country->setLabel(Helpers::__getRequestValue('label'));
        $country->setContinent(Helpers::__getRequestValue('continent'));
        $country->setIsoNumeric(Helpers::__getRequestValue('iso_numeric'));
        $country->setTop(Helpers::__getRequestValue('top'));
        $country->setActive(Helpers::__getRequestValue('active'));
        $country->setAlternativeNames(Helpers::__getRequestValue('alternative_names'));
        $country->setCioFlag(Helpers::__getRequestValue('cio_flag'));
        $country->setGeonameid((int)Helpers::__getRequestValue('geonameid'));
        $country->setSecondary(Helpers::__getRequestValue('secondary'));

        $this->db->begin();
        if ((int)Helpers::__getRequestValue('id') > 0) {

            $result = $country->__quickUpdate();
        } else {
            $result = $country->__quickCreate();
        }

        if ($result['success']) {
            // Save translation
            $saveTranslation = true;
            $translations = Helpers::__getRequestValue('translations');
            if ($translations != null) {
                foreach ($translations as $language_id => $value_translation) {
                    $countryTranslation = CountryTranslationExt::findFirst(['country_id=' . $country->getId() . ' AND supported_language_id=' . (int)$language_id]);
                    if (!$countryTranslation instanceof CountryTranslationExt) {
                        $countryTranslation = new CountryTranslationExt();
                    }
                    $countryTranslation->setCountryId($country->getId());
                    $countryTranslation->setSupportedLanguageId((int)$language_id);
                    $countryTranslation->setValue($value_translation);

                    if (!$countryTranslation->save()) {
                        $saveTranslation = false;
                        break;
                    }
                }
            }

            if (!$saveTranslation) {
                $this->db->rollback();
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT'
                ];
            } else {
                $this->db->commit();
                $result = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT'
                ];
            }
        }

        return $result;
    }

    /**
     * Save country
     */
    public function saveAction()
    {
        $this->view->disable();

        $country = new Country();
        if ((int)Helpers::__getRequestValue('id') > 0) {
            $country = Country::findFirst((int)Helpers::__getRequestValue('id'));
            if (!$country instanceof Country) {
                exit(json_encode([
                    'success' => false,
                    'msg' => 'Country was not found'
                ]));
            }
        }

        $country->setCio(Helpers::__getRequestValue('cio'));
        $country->setName(Helpers::__getRequestValue('name'));
        $country->setPhone(Helpers::__getRequestValue('phone'));
        $country->setCapital(Helpers::__getRequestValue('capital'));
        $country->setLabel(Helpers::__getRequestValue('label'));
        $country->setContinent(Helpers::__getRequestValue('continent'));
        $country->setIsoNumeric(Helpers::__getRequestValue('iso_numeric'));
        $country->setTop(Helpers::__getRequestValue('top'));
        $country->setActive(Helpers::__getRequestValue('active'));

        $country->setCioFlag(Helpers::__getRequestValue('cio_flag'));
        $country->setGeonameid(Helpers::__getRequestValue('geonameid'));

        $this->db->begin();
        if ($country->save()) {

            // Save translation
            $saveTranslation = true;
            $translations = Helpers::__getRequestValue('translations');
            if (is_array($translations)) {
                foreach ($translations as $language_id => $value_translation) {
                    $countryTranslation = CountryTranslationExt::findFirst(['country_id=' . $country->getId() . ' AND supported_language_id=' . (int)$language_id]);
                    if (!$countryTranslation instanceof CountryTranslationExt) {
                        $countryTranslation = new CountryTranslationExt();
                    }
                    $countryTranslation->setCountryId($country->getId());
                    $countryTranslation->setSupportedLanguageId((int)$language_id);
                    $countryTranslation->setValue($value_translation);

                    if (!$countryTranslation->save()) {
                        $saveTranslation = false;
                        break;
                    }
                }
            }

            if (!$saveTranslation) {
                $this->db->rollback();
                $this->response->setJsonContent([
                    'success' => false,
                    'msg' => 'Save data translation was error'
                ]);
            } else {
                $this->db->commit();
                $this->response->setJsonContent([
                    'success' => true
                ]);
            }
        } else {
            $this->db->rollback();
            $msg = [];
            foreach ($country->getMessages() as $message) {
                $msg[] = $message->getMessage();
            }
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'Save country was error',
                'detail' => $msg
            ]);
        }

        end:
        $this->response->send();
    }

    public function deleteAction($id)
    {
        $this->view->disable();

        $return = [
            'success' => false,
            'message' => 'ACCESS_DENIED_TEXT'
        ];

        if (!$this->request->isDelete()) {
            goto end;
        }

        $return['message'] = 'DATA_NOT_FOUND_TEXT';

        $country = Country::findFirst($id);
        if (!$country instanceof Country) {
            goto end;
        }

        $return = $country->__quickRemove();
        if (!$return['success']) {
            $return['message'] = "DATA_DELETE_FAIL_TEXT";
        }

        end:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function listJsonAction()
    {
        $this->view->disable();
        $list = Country::find(['order' => 'name ASC']);

        $result = [];
        foreach ($list as $item) {
            $result[$item->getId()] = [
                'name' => $item->getName(),
                'cio' => $item->getCio()
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Import country with translation name of country
     */
    public function importAction()
    {
        $this->view->disable();

        $data = Helpers::__getRequestValue('data');
        $countryId = 0;

        if (is_array($data) && count($data)) {
            foreach ($data as $key => $object) {

                if ($key == 0) { // Find country data
                    // Search country in database
                    $country = CountryExt::findFirst("name='" . (isset($object['text']) ? $object['text'] : '') . "'");
                    if (!$country instanceof CountryExt) {
                        $country = new CountryExt();
                        $country->setName(isset($object['text']) ? $object['text'] : '');
                        $country->setTop(0);
                        $country->setSecondary(1);
                        $country->setActive(CountryExt::IS_ACTIVE_YES);

                        if (!$country->save()) {
                            exit(json_encode(['success' => false, 'msg' => 'Can not create new country with name "' . (isset($object['text']) ? $object['text'] : '') . '"']));
                        }
                    }

                    $countryId = $country->getId();
                    unset($country);
                    continue;
                }

                // Find ID of language
                $supportedLanguage = SupportedLanguageExt::findFirst(['name="' . (isset($object['code']) ? $object['code'] : '') . '"']);
                if (!$supportedLanguage instanceof SupportedLanguageExt) {
                    unset($supportedLanguage);
                    continue;
                }

                $country_translation = CountryTranslationExt::findFirst(['country_id=' . $countryId . ' AND supported_language_id=' . $supportedLanguage->getId()]);
                if (!$country_translation instanceof CountryTranslationExt) {
                    $country_translation = new CountryTranslationExt();
                }
                $country_translation->setCountryId($countryId);
                $country_translation->setValue(isset($object['text']) ? $object['text'] : '');
                $country_translation->setSupportedLanguageId($supportedLanguage->getId());

                $country_translation->save();
                unset($country_translation);
            }
        }

        $this->response->setJsonContent(['success' => true]);

        end:
        $this->response->send();
    }

    /**
     * Import country with translation name of country
     */
    public function importTranslationAction()
    {
        $this->view->disable();

        $data = Helpers::__getRequestValue('data');

        if (is_array($data) && count($data)) {
            //Set key to array
            $header = array_shift($data);
            $data = array_map(function ($v) use ($header) {
                return array_combine($header, $v);
            }, $data);

            foreach ($data as $key => $object) {
                // Search country in database
                foreach ($object as $lang => $value) {
                    $name = isset($object['Standard']) ? $object['Standard'] : '';
                    $country = CountryExt::findFirst("name='" . $name . "'");
                    $supportedLanguage = SupportedLanguageExt::findFirstByName($lang);
                    if (!$supportedLanguage instanceof SupportedLanguageExt) {
                        continue;
                    }

                    if ($country instanceof CountryExt) {
                        $translationContent = CountryTranslationExt::findFirst([
                            'conditions' => 'country_id = :country_id: AND supported_language_id = :language:',
                            'bind' => [
                                'country_id' => $country->getId(),
                                'language' => $supportedLanguage->getId()
                            ]
                        ]);
                        if (!$translationContent) {
                            $translationContent = new CountryTranslationExt();
                            $translationContent->setCountryId($country->getId());
                            $translationContent->setSupportedLanguageId($supportedLanguage->getId());
                        }

                        if (isset($value) && $value) {
                            $translationContent->setValue($value);
                        }
                        $content = $translationContent->__quickSave();

                        if ($content['success'] == false) {
                            $this->response->setJsonContent([
                                'success' => false,
                                'message' => 'Can not update new country transaction with name "' . (isset($value) ? $value : '') . '"'

                            ]);
                            goto end;
                        }
                    }
                }
            }
        }

        $this->response->setJsonContent(['success' => true]);

        end:
        $this->response->send();
    }
}
