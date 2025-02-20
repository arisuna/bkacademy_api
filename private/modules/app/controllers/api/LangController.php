<?php

namespace SMXD\App\Controllers\API;

use \SMXD\App\Controllers\ModuleApiController;
use SMXD\App\Models\SupportedLanguage;
use SMXD\App\Models\Constant;
use SMXD\App\Models\ConstantTranslation;
/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class LangController extends ModuleApiController
{
	/**
     * @Route("/lang", paths={module="app"}, methods={"GET"}, name="app-lang-index")
     */
    public function indexAction(  )
    {
    	$this->view->disable();
        $this->checkAjaxGet();

    }
    /**
     * load language configuration
     * @param  string $lang [description]
     * @return [type]       [description]
     */
    public function i18nAction( $lang = ''){
    	$this->view->disable();
        $this->checkAjaxGet();
        
        $language 		= SupportedLanguage::findFirstByName($lang);
        if( $language  ){
	        $constants 	= Constant::find();
	        $results = array();
            if( count(  $constants ) ){
                foreach($constants as $constant ){
                    if( trim($constant->getName()) != ""){
                        $results[trim($constant->getName())] = trim($constant->getName());
                        $translations = $constant->getConstantTranslation("language = '".$language->getName()."'");
                        if( count(  $translations ) ){
                            foreach( $translations as $translation ){
                                if( $translation->getLanguage() == $language->getName() ){
                                    $results[trim($constant->getName())] = trim($translation->getValue());
                                }
                            }
                        }
                    }
                }
            }

	        $this->response->setJsonContent($results, JSON_ERROR_UTF8 );
	    	return $this->response->send();

	    }else{
	    	$this->response->setJsonContent(['success' => false ]);
	    	return $this->response->send();
	    }
    }


    /**
     *
     */
    function loginpageAction( $lang ){
        $this->view->disable();
        $constant_keys = [
            'LOGIN_TEXT',
            'ENTER_EMAIL_TEXT',
            'REMEMBER_ME_TEXT',
            'LOGIN_BTN_TEXT',
            'REGISTER_NOW_TEXT',
            'SUBMIT_BTN_TEXT',
            'RESET_PASSWORD_BTN_TEXT',
            'ENTER_EMAIL_TEXT',
            'ENTER_PASSWORD_TEXT',
            'REENTER_NEW_PASSWORD_TEXT',
            'FORGOT_PASSWORD_TEXT',
            'REGISTER_NOW_TEXT',
            'NEED_TO_SIGN_UP_TEXT',
        ];

        $constants = Constant::find([
            "conditions" => 'name IN ("' . implode('","', $constant_keys) . '")',
        ]);
        $keys = [];
        $result = [];
        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getId();
            }

            $lang  = (empty($lang) ? $lang : 'en');
            $constants_translate = ConstantTranslation::find([
                "conditions" => 'constant_id IN (' . implode(',', $keys) . ') AND language="' . (empty($lang) ? $lang : 'en') . '"',
            ]);
            if (count($constants_translate)) {
                foreach ($constants_translate as $tran) {
                    if (($key = array_search($tran->getConstantId(), $keys)) !== false) {
                        $result[$key] = $tran->getValue();
                    }
                }
            }
        }


        $this->response->setJsonContent([
            'success' => true,
            'keys' => $result
        ]);
        return $this->response->send();
    }
}
