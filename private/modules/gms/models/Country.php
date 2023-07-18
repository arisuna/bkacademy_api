<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Models\SupportedLanguageExt;

class Country extends \Reloday\Application\Models\CountryExt {
    /**
     * [getValueTranslationByLanguage description]
     * @param string $language [description]
     * @return [type]           [description]
     */
    public function getName()
    {
        $language = ModuleModel::$user_profile->getDisplayLanguage();
        if ($language == 'en') {
            return $this->get('name');
        }

        $languageProfile = SupportedLanguageExt::__getLangData($language);

        $translation = $this->getTranslation([
            'conditions' => 'supported_language_id = :languageId:',
            'bind' => [
                'languageId' => $languageProfile->getId(),
            ]
        ])->getFirst();

        if ($translation || (method_exists($translation, 'getValue') && $translation->getValue() != '')) {
            return $translation->getValue();
        } else {

            return $this->get('name');
        }
    }
}