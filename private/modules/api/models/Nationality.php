<?php

namespace SMXD\Api\Models;

use SMXD\Application\Models\SupportedLanguageExt;

class Nationality extends \SMXD\Application\Models\NationalityExt
{
    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        $this->hasMany('code', 'SMXD\Api\Models\NationalityTranslation', 'country_code', ['alias' => 'translation']);
    }

    /**
     * [getTranslationByLanguage description]
     * @param  string $language [description]
     * @return [type]           [description]
     */
    public function getTranslationByLanguage($language = SupportedLanguageExt::LANG_EN)
    {

        if ($language == 'en') {
            return $this;
        }

        $languageProfile = SupportedLanguageExt::__getLangData( $language );

        $translation = $this->getTranslation([
            'conditions' => 'supported_language_id = :languageId:',
            'bind' => [
                'languageId' => $languageProfile->getId(),
            ]
        ])->getFirst();

        if ($translation || (method_exists($translation, 'getValue') && $translation->getValue() != '')) {
            return $translation;
        } else {
            return $this;
        }
    }
}