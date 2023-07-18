<?php

namespace Reloday\Gms\Models;

class Nationality extends \Reloday\Application\Models\NationalityExt
{
    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('code', 'Reloday\Gms\Models\NationalityTranslation', 'code', ['alias' => 'translation']);
    }

    /**
     * [getTranslationByLanguage description]
     * @param  string $language [description]
     * @return [type]           [description]
     */
    public function getTranslationByLanguage($language = 'en')
    {

        if ($language == 'en') {
            return $this;
        }

        $translation = $this->getTranslation([
            'conditions' => 'language = :language:',
            'bind' => [
                'language' => $language,
            ]
        ])->getFirst();

        if ($translation || (method_exists($translation, 'getValue') && $translation->getValue() != '')) {
            return $translation;
        } else {
            return $this;
        }

    }
}
