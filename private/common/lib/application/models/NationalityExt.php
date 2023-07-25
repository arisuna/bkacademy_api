<?php

namespace SMXD\Application\Models;

use SMXD\Application\Lib\CacheHelper;

class NationalityExt extends Nationality
{

    public function initialize()
    {
        $this->hasMany('code', 'SMXD\Application\Models\NationalityTranslationExt', 'country_code', ['alias' => 'translation']);
    }

    /**
	 * [getAll description]
	 * @return [type] [description]
	 */
	public static function getAll(){
		return self::find([
			"conditions" => "value <> ''",
			"order" => "value ASC",
            "cache" => [
                'key' => '__CACHE_NATIONALITITES_LIST',
                "lifetime" => CacheHelper::__TIME_24H,
            ]
		]);
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
                'languageId' => $languageProfile ? $languageProfile->getId() : null,
            ]
        ])->getFirst();

        if ($translation || (method_exists($translation, 'getValue') && $translation->getValue() != '')) {
            return $translation;
        } else {
            return $this;
        }
    }
}
