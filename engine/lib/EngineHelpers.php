<?php

class EngineHelpers
{
    /**
     * @param $string
     */
    public static function getShortkeyFromWord($string)
    {
        $string = preg_replace('/[^\da-z]/i', '', $string);
        $shortname = "";

        if (strlen($string) <= 4) {
            $shortname = strtoupper($string);
        } else {
            $array = [];
            $arrayWord = [];
            $words = str_word_count(strtoupper($string), 1);
            if (is_array($words) && count($words) > 0) {
                for ($i = 0; $i < count($words); $i++) {
                    if (isset($words[$i])) {
                        $array = array_merge($array, str_split($words[$i]));
                        $arrayWord[$i] = $words[$i];
                    }
                }
            }
            $array = array_values(array_unique($array));

            if (count($arrayWord) >= 3) {
                foreach ($arrayWord as $key => $aryWord) {
                    if ($key < 1) {
                        $shortname .= $aryWord[0] . $aryWord[1];
                    }
                    if ($key == 1) {
                        $shortname .= $aryWord[0];
                    }
                    if ($key == 2) {
                        $shortname .= $aryWord[0];
                    }
                }
            } elseif (count($arrayWord) == 2) {
                foreach ($arrayWord as $key => $aryWord) {
                    if ($key <= 1) {
                        $shortname .= $aryWord[0] . $aryWord[1];
                    }
                }
            } elseif (count($arrayWord) == 1) {
                $shortname = $array[0] . $array[1] . $array[2] . (isset($array[3]) ? $array[3] : '');
            }
        }
        return $shortname;
    }

    /**
     * @param $rand_no
     * @return string
     */
    public function getNickName($string_name, $rand_no)
    {
        $string_name = $this->getFirstname() . " " . $this->getLastname() . " " . $this->getCompany()->getName();
        while (true) {
            $username_parts = array_filter(explode(" ", strtolower($string_name))); //explode and lowercase name
            $username_parts = array_slice($username_parts, 0, 2); //return only first two arry part

            $part1 = (!empty($username_parts[0])) ? substr($username_parts[0], 0, 8) : ""; //cut first name to 8 letters
            $part2 = (!empty($username_parts[1])) ? substr($username_parts[1], 0, 5) : ""; //cut second name to 5 letters
            $part3 = ($rand_no) ? rand(0, $rand_no) : "";

            $username = $part1 . str_shuffle($part2) . $part3; //str_shuffle to randomly shuffle all characters
            if ($this->checkNickName($username) == true) {
                return $username;
            }
        }
    }

    /***
     * @param $name
     * @param $lang
     * @return bool
     */
    public static function __getTemplateEmail($name, $lang = \Reloday\Application\Models\SupportedLanguageExt::LANG_EN)
    {
        return \Reloday\Application\Models\EmailTemplateDefaultExt::__getTemplate($name, $lang);
    }

    /**
     * @param $name
     * @param string $lang
     */
    public static function __getConstantValue($name, $lang = \Reloday\Application\Models\SupportedLanguageExt::LANG_EN)
    {
        return \Reloday\Application\Models\ConstantExt::__translateConstant($name, $lang);
    }

    /**
     * @param $input
     * @return string
     */
    static function format4DigitNumber($input)
    {

        $value = str_pad($input, 4, '0', STR_PAD_LEFT);

        return $value;
    }

    /**
     * Get config discount value
     * @return array
     */
    static function discountValue()
    {
        return [
            1 => [
                'id' => 1,
                'name' => 'Discount 1',
                'value' => 10
            ],
            2 => [
                'id' => 2,
                'name' => 'Discount 2',
                'value' => 15
            ],
            3 => ['id' => 3, 'name' => 'Discount 3', 'value' => 20]
        ];
    }

    static function _decodeUTF8($text)
    {
        return iconv('utf-8', 'cp1258//IGNORE', $text);
    }
}

?>