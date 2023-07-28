<?php
/**
 * List of 217 language codes: ISO 639-1.
 *
 * @author    Josantonius <hello@josantonius.com>
 * @copyright 2017 - 2018 (c) Josantonius - PHP-LanguageCode
 * @license   https://opensource.org/licenses/MIT - The MIT License (MIT)
 * @link      https://github.com/Josantonius/PHP-LanguageCode
 * @since     1.0.0
 */
namespace SMXD\Application\Lib;

/**
 * Language code handler.
 */
class LanguageCode
{
    /**
     * Get all language codes as array.
     *
     * @return array → language codes and language names
     */
    public static function get()
    {
        return LanguageCodeCollection::all();
    }

    /**
     * Get all language codes as array.
     *
     * @return array → language codes and language names
     */
    public static function getAllTranslation($lang = 'en')
    {
        return LanguageCodeCollection::getAllTranslate($lang);
    }

    /**
     * Get language name from language code.
     *
     * @param string $languageCode → language code, e.g. 'es'
     *
     * @return tring|false → country name
     */
    public static function getLanguageFromCode($languageCode)
    {
        return LanguageCodeCollection::get($languageCode) ?: false;
    }

    /**
     * Get language name from language code.
     *
     * @param string $languageCode → language code, e.g. 'es'
     * @param string $lang → language code, e.g. 'es'
     *
     * @return string|false → country name
     */
    public static function getLanguageTranslationFromCode($languageCode, $lang = 'en')
    {
        return LanguageCodeCollection::getTranslateByKey($languageCode, $lang) ?: false;
    }

    /**
     * Get language code from language name.
     *
     * @param string $languageName → language name, e.g. 'Spanish'
     *
     * @return string|false → language code
     */
    public static function getCodeFromLanguage($languageName)
    {
        return array_search($languageName, LanguageCodeCollection::all(), true);
    }

    /**
     * Get language code from language name.
     *
     * @param $languageName → language name, e.g. 'Spanish'
     * @param $lang → language name, e.g. 'Spanish'
     *
     * @return string|false → language code
     */
    public static function getCodeFromLanguageWithTranslation($languageName, $lang = 'en')
    {
        return array_search($languageName, LanguageCodeCollection::getAllTranslate($lang), true);
    }
}