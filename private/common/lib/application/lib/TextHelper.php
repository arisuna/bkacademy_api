<?php

namespace SMXD\Application\Lib;

use League\HTMLToMarkdown\HtmlConverter;
use SMXD\Application\Lib\Parsedown;

class TextHelper
{
    const ASCII_MAPPED_VALUES = [
        // Latin
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
        'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
        'ÿ' => 'y',

        // Latin symbols
        '©' => '(c)',

        // Greek
        'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
        'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
        'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
        'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
        'Ϋ' => 'Y',
        'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
        'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
        'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
        'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
        'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',

        // Turkish
        'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
        'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',

        // Russian
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
        'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
        'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
        'Я' => 'Ya',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya',

        // Ukrainian
        'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
        'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',

        // Czech
        'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
        'Ž' => 'Z',
        'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
        'ž' => 'z',

        // Polish
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
        'Ż' => 'Z',
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
        'ż' => 'z',

        // Latvian
        'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
        'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
        'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
        'š' => 's', 'ū' => 'u', 'ž' => 'z'
    ];

    static $tags = [
        "span",
        "p",
        "a",
        "img",
        "h1", "h2", "h3", "h4", "h5", "h6",
        "ul", "li",
        "ol", "li",
        "strong",
        "em",
        "table",
        "tr",
        "th",
        "td"
    ];

    static $colors = [
        "000000" => "Black",
        "993300" => "Burnt orange",
        "333300" => "Dark olive",
        "003300" => "Dark green",
        "003366" => "Dark azure",
        "000080" => "Navy Blue",
        "333399" => "Indigo",
        "333333" => "Very dark gray",
        "800000" => "Maroon",
        "FF6600" => "Orange",
        "808000" => "Olive",
        "008000" => "Green",
        "008080" => "Teal",
        "0000FF" => "Blue",
        "666699" => "Grayish blue",
        "808080" => "Gray",
        "FF0000" => "Red",
        "FF9900" => "Amber",
        "99CC00" => "Yellow green",
        "339966" => "Sea green",
        "33CCCC" => "Turquoise",
        "3366FF" => "Royal blue",
        "800080" => "Purple",
        "999999" => "Medium gray",
        "FF00FF" => "Magenta",
        "FFCC00" => "Gold",
        "FFFF00" => "Yellow",
        "00FF00" => "Lime",
        "00FFFF" => "Aqua",
        "00CCFF" => "Sky blue",
        "993366" => "Red violet",
        "FFFFFF" => "White",
        "FF99CC" => "Pink",
        "FFCC99" => "Peach",
        "FFFF99" => "Light yellow",
        "CCFFCC" => "Pale green",
        "CCFFFF" => "Pale cyan",
        "99CCFF" => "Light sky blue",
        "CC99FF" => "Plum"
    ];

    /**
     * @param $html
     * @return string
     */
    public static function convert2markdown($html)
    {
        $html = strip_tags($html, self::getAllowedTagsString());
        $converter = new HtmlConverter();
        $converter->getConfig()->setOption('italic_style', '_');
        $converter->getConfig()->setOption('bold_style', '__');
        $markdown = $converter->convert($html);
        return $markdown;
    }

    /**
     * @param $markdown
     * @return mixed
     */
    public static function convert2html($markdown)
    {
        $Parsedown = new Parsedown();
        return $Parsedown->text($markdown);
    }

    /**
     *
     */
    public static function getAllowedTagsString()
    {
        $return = "";
        foreach (self::$tags as $tag) {
            $return .= "<" . $tag . ">";
        }
        return $return;
    }

    /**
     * @param $html
     * @param $users
     */
    public static function replaceUserTag($html, $users)
    {
        if (preg_match_all("#\[@([a-zA-Z0-9]+)\]#", $html, $matches)) {
            if (isset($matches[1]) && is_array($matches[1])) {
                foreach ($matches[1] as $nickname) {
                    //$nickname = substr($item, 2, strlen($item) - 1);
                    $fullname = $nickname;
                    foreach ($users as $user) {
                        $user = $user->toArray();
                        if (trim($user['nickname']) == trim($nickname)) {
                            $fullname = $user['firstname'] . " " . $user['lastname'];
                            continue;
                        }
                    }
                    $html = str_replace("[@" . $nickname . "]", '<a href="javascript:void(0);" class="mr-sm" uib-tooltip-template="\'CommentUserBox.tpl\'" ng-model="userTarget" tooltip-placement="bottom-left" tooltip-class="user-tooltip">' . $fullname . '</a>', $html);
                }
            }
        }
        return $html;
    }

    /**
     * @param string $stringToCheck
     * @return bool
     */
    public static function __isAsciiValid(string $stringToCheck): bool
    {
        foreach (self::ASCII_MAPPED_VALUES as $key => $value) {
            if (strpos($stringToCheck, $key) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $str
     * @param string $locale
     * @return string
     */
    public static function __stripAccents(string $str, $locale = ""): string
    {
        $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(194) . chr(170) => 'a', chr(194) . chr(186) => 'o',
            chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A',
            chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A',
            chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A',
            chr(195) . chr(134) => 'AE', chr(195) . chr(135) => 'C',
            chr(195) . chr(136) => 'E', chr(195) . chr(137) => 'E',
            chr(195) . chr(138) => 'E', chr(195) . chr(139) => 'E',
            chr(195) . chr(140) => 'I', chr(195) . chr(141) => 'I',
            chr(195) . chr(142) => 'I', chr(195) . chr(143) => 'I',
            chr(195) . chr(144) => 'D', chr(195) . chr(145) => 'N',
            chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O',
            chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O',
            chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U',
            chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U',
            chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y',
            chr(195) . chr(158) => 'TH', chr(195) . chr(159) => 's',
            chr(195) . chr(160) => 'a', chr(195) . chr(161) => 'a',
            chr(195) . chr(162) => 'a', chr(195) . chr(163) => 'a',
            chr(195) . chr(164) => 'a', chr(195) . chr(165) => 'a',
            chr(195) . chr(166) => 'ae', chr(195) . chr(167) => 'c',
            chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e',
            chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e',
            chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i',
            chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i',
            chr(195) . chr(176) => 'd', chr(195) . chr(177) => 'n',
            chr(195) . chr(178) => 'o', chr(195) . chr(179) => 'o',
            chr(195) . chr(180) => 'o', chr(195) . chr(181) => 'o',
            chr(195) . chr(182) => 'o', chr(195) . chr(184) => 'o',
            chr(195) . chr(185) => 'u', chr(195) . chr(186) => 'u',
            chr(195) . chr(187) => 'u', chr(195) . chr(188) => 'u',
            chr(195) . chr(189) => 'y', chr(195) . chr(190) => 'th',
            chr(195) . chr(191) => 'y', chr(195) . chr(152) => 'O',
            // Decompositions for Latin Extended-A
            chr(196) . chr(128) => 'A', chr(196) . chr(129) => 'a',
            chr(196) . chr(130) => 'A', chr(196) . chr(131) => 'a',
            chr(196) . chr(132) => 'A', chr(196) . chr(133) => 'a',
            chr(196) . chr(134) => 'C', chr(196) . chr(135) => 'c',
            chr(196) . chr(136) => 'C', chr(196) . chr(137) => 'c',
            chr(196) . chr(138) => 'C', chr(196) . chr(139) => 'c',
            chr(196) . chr(140) => 'C', chr(196) . chr(141) => 'c',
            chr(196) . chr(142) => 'D', chr(196) . chr(143) => 'd',
            chr(196) . chr(144) => 'D', chr(196) . chr(145) => 'd',
            chr(196) . chr(146) => 'E', chr(196) . chr(147) => 'e',
            chr(196) . chr(148) => 'E', chr(196) . chr(149) => 'e',
            chr(196) . chr(150) => 'E', chr(196) . chr(151) => 'e',
            chr(196) . chr(152) => 'E', chr(196) . chr(153) => 'e',
            chr(196) . chr(154) => 'E', chr(196) . chr(155) => 'e',
            chr(196) . chr(156) => 'G', chr(196) . chr(157) => 'g',
            chr(196) . chr(158) => 'G', chr(196) . chr(159) => 'g',
            chr(196) . chr(160) => 'G', chr(196) . chr(161) => 'g',
            chr(196) . chr(162) => 'G', chr(196) . chr(163) => 'g',
            chr(196) . chr(164) => 'H', chr(196) . chr(165) => 'h',
            chr(196) . chr(166) => 'H', chr(196) . chr(167) => 'h',
            chr(196) . chr(168) => 'I', chr(196) . chr(169) => 'i',
            chr(196) . chr(170) => 'I', chr(196) . chr(171) => 'i',
            chr(196) . chr(172) => 'I', chr(196) . chr(173) => 'i',
            chr(196) . chr(174) => 'I', chr(196) . chr(175) => 'i',
            chr(196) . chr(176) => 'I', chr(196) . chr(177) => 'i',
            chr(196) . chr(178) => 'IJ', chr(196) . chr(179) => 'ij',
            chr(196) . chr(180) => 'J', chr(196) . chr(181) => 'j',
            chr(196) . chr(182) => 'K', chr(196) . chr(183) => 'k',
            chr(196) . chr(184) => 'k', chr(196) . chr(185) => 'L',
            chr(196) . chr(186) => 'l', chr(196) . chr(187) => 'L',
            chr(196) . chr(188) => 'l', chr(196) . chr(189) => 'L',
            chr(196) . chr(190) => 'l', chr(196) . chr(191) => 'L',
            chr(197) . chr(128) => 'l', chr(197) . chr(129) => 'L',
            chr(197) . chr(130) => 'l', chr(197) . chr(131) => 'N',
            chr(197) . chr(132) => 'n', chr(197) . chr(133) => 'N',
            chr(197) . chr(134) => 'n', chr(197) . chr(135) => 'N',
            chr(197) . chr(136) => 'n', chr(197) . chr(137) => 'N',
            chr(197) . chr(138) => 'n', chr(197) . chr(139) => 'N',
            chr(197) . chr(140) => 'O', chr(197) . chr(141) => 'o',
            chr(197) . chr(142) => 'O', chr(197) . chr(143) => 'o',
            chr(197) . chr(144) => 'O', chr(197) . chr(145) => 'o',
            chr(197) . chr(146) => 'OE', chr(197) . chr(147) => 'oe',
            chr(197) . chr(148) => 'R', chr(197) . chr(149) => 'r',
            chr(197) . chr(150) => 'R', chr(197) . chr(151) => 'r',
            chr(197) . chr(152) => 'R', chr(197) . chr(153) => 'r',
            chr(197) . chr(154) => 'S', chr(197) . chr(155) => 's',
            chr(197) . chr(156) => 'S', chr(197) . chr(157) => 's',
            chr(197) . chr(158) => 'S', chr(197) . chr(159) => 's',
            chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's',
            chr(197) . chr(162) => 'T', chr(197) . chr(163) => 't',
            chr(197) . chr(164) => 'T', chr(197) . chr(165) => 't',
            chr(197) . chr(166) => 'T', chr(197) . chr(167) => 't',
            chr(197) . chr(168) => 'U', chr(197) . chr(169) => 'u',
            chr(197) . chr(170) => 'U', chr(197) . chr(171) => 'u',
            chr(197) . chr(172) => 'U', chr(197) . chr(173) => 'u',
            chr(197) . chr(174) => 'U', chr(197) . chr(175) => 'u',
            chr(197) . chr(176) => 'U', chr(197) . chr(177) => 'u',
            chr(197) . chr(178) => 'U', chr(197) . chr(179) => 'u',
            chr(197) . chr(180) => 'W', chr(197) . chr(181) => 'w',
            chr(197) . chr(182) => 'Y', chr(197) . chr(183) => 'y',
            chr(197) . chr(184) => 'Y', chr(197) . chr(185) => 'Z',
            chr(197) . chr(186) => 'z', chr(197) . chr(187) => 'Z',
            chr(197) . chr(188) => 'z', chr(197) . chr(189) => 'Z',
            chr(197) . chr(190) => 'z', chr(197) . chr(191) => 's',
            // Decompositions for Latin Extended-B
            chr(200) . chr(152) => 'S', chr(200) . chr(153) => 's',
            chr(200) . chr(154) => 'T', chr(200) . chr(155) => 't',
            // Euro Sign
            chr(226) . chr(130) . chr(172) => 'E',
            // GBP (Pound) Sign
            chr(194) . chr(163) => '',
            // Vowels with diacritic (Vietnamese)
            // unmarked
            chr(198) . chr(160) => 'O', chr(198) . chr(161) => 'o',
            chr(198) . chr(175) => 'U', chr(198) . chr(176) => 'u',
            // grave accent
            chr(225) . chr(186) . chr(166) => 'A', chr(225) . chr(186) . chr(167) => 'a',
            chr(225) . chr(186) . chr(176) => 'A', chr(225) . chr(186) . chr(177) => 'a',
            chr(225) . chr(187) . chr(128) => 'E', chr(225) . chr(187) . chr(129) => 'e',
            chr(225) . chr(187) . chr(146) => 'O', chr(225) . chr(187) . chr(147) => 'o',
            chr(225) . chr(187) . chr(156) => 'O', chr(225) . chr(187) . chr(157) => 'o',
            chr(225) . chr(187) . chr(170) => 'U', chr(225) . chr(187) . chr(171) => 'u',
            chr(225) . chr(187) . chr(178) => 'Y', chr(225) . chr(187) . chr(179) => 'y',
            // hook
            chr(225) . chr(186) . chr(162) => 'A', chr(225) . chr(186) . chr(163) => 'a',
            chr(225) . chr(186) . chr(168) => 'A', chr(225) . chr(186) . chr(169) => 'a',
            chr(225) . chr(186) . chr(178) => 'A', chr(225) . chr(186) . chr(179) => 'a',
            chr(225) . chr(186) . chr(186) => 'E', chr(225) . chr(186) . chr(187) => 'e',
            chr(225) . chr(187) . chr(130) => 'E', chr(225) . chr(187) . chr(131) => 'e',
            chr(225) . chr(187) . chr(136) => 'I', chr(225) . chr(187) . chr(137) => 'i',
            chr(225) . chr(187) . chr(142) => 'O', chr(225) . chr(187) . chr(143) => 'o',
            chr(225) . chr(187) . chr(148) => 'O', chr(225) . chr(187) . chr(149) => 'o',
            chr(225) . chr(187) . chr(158) => 'O', chr(225) . chr(187) . chr(159) => 'o',
            chr(225) . chr(187) . chr(166) => 'U', chr(225) . chr(187) . chr(167) => 'u',
            chr(225) . chr(187) . chr(172) => 'U', chr(225) . chr(187) . chr(173) => 'u',
            chr(225) . chr(187) . chr(182) => 'Y', chr(225) . chr(187) . chr(183) => 'y',
            // tilde
            chr(225) . chr(186) . chr(170) => 'A', chr(225) . chr(186) . chr(171) => 'a',
            chr(225) . chr(186) . chr(180) => 'A', chr(225) . chr(186) . chr(181) => 'a',
            chr(225) . chr(186) . chr(188) => 'E', chr(225) . chr(186) . chr(189) => 'e',
            chr(225) . chr(187) . chr(132) => 'E', chr(225) . chr(187) . chr(133) => 'e',
            chr(225) . chr(187) . chr(150) => 'O', chr(225) . chr(187) . chr(151) => 'o',
            chr(225) . chr(187) . chr(160) => 'O', chr(225) . chr(187) . chr(161) => 'o',
            chr(225) . chr(187) . chr(174) => 'U', chr(225) . chr(187) . chr(175) => 'u',
            chr(225) . chr(187) . chr(184) => 'Y', chr(225) . chr(187) . chr(185) => 'y',
            // acute accent
            chr(225) . chr(186) . chr(164) => 'A', chr(225) . chr(186) . chr(165) => 'a',
            chr(225) . chr(186) . chr(174) => 'A', chr(225) . chr(186) . chr(175) => 'a',
            chr(225) . chr(186) . chr(190) => 'E', chr(225) . chr(186) . chr(191) => 'e',
            chr(225) . chr(187) . chr(144) => 'O', chr(225) . chr(187) . chr(145) => 'o',
            chr(225) . chr(187) . chr(154) => 'O', chr(225) . chr(187) . chr(155) => 'o',
            chr(225) . chr(187) . chr(168) => 'U', chr(225) . chr(187) . chr(169) => 'u',
            // dot below
            chr(225) . chr(186) . chr(160) => 'A', chr(225) . chr(186) . chr(161) => 'a',
            chr(225) . chr(186) . chr(172) => 'A', chr(225) . chr(186) . chr(173) => 'a',
            chr(225) . chr(186) . chr(182) => 'A', chr(225) . chr(186) . chr(183) => 'a',
            chr(225) . chr(186) . chr(184) => 'E', chr(225) . chr(186) . chr(185) => 'e',
            chr(225) . chr(187) . chr(134) => 'E', chr(225) . chr(187) . chr(135) => 'e',
            chr(225) . chr(187) . chr(138) => 'I', chr(225) . chr(187) . chr(139) => 'i',
            chr(225) . chr(187) . chr(140) => 'O', chr(225) . chr(187) . chr(141) => 'o',
            chr(225) . chr(187) . chr(152) => 'O', chr(225) . chr(187) . chr(153) => 'o',
            chr(225) . chr(187) . chr(162) => 'O', chr(225) . chr(187) . chr(163) => 'o',
            chr(225) . chr(187) . chr(164) => 'U', chr(225) . chr(187) . chr(165) => 'u',
            chr(225) . chr(187) . chr(176) => 'U', chr(225) . chr(187) . chr(177) => 'u',
            chr(225) . chr(187) . chr(180) => 'Y', chr(225) . chr(187) . chr(181) => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin)
            chr(201) . chr(145) => 'a',
            // macron
            chr(199) . chr(149) => 'U', chr(199) . chr(150) => 'u',
            // acute accent
            chr(199) . chr(151) => 'U', chr(199) . chr(152) => 'u',
            // caron
            chr(199) . chr(141) => 'A', chr(199) . chr(142) => 'a',
            chr(199) . chr(143) => 'I', chr(199) . chr(144) => 'i',
            chr(199) . chr(145) => 'O', chr(199) . chr(146) => 'o',
            chr(199) . chr(147) => 'U', chr(199) . chr(148) => 'u',
            chr(199) . chr(153) => 'U', chr(199) . chr(154) => 'u',
            // grave accent
            chr(199) . chr(155) => 'U', chr(199) . chr(156) => 'u',
        );

        if ('de' === $locale) {
            $chars[chr(195) . chr(132)] = 'Ae';
            $chars[chr(195) . chr(164)] = 'ae';
            $chars[chr(195) . chr(150)] = 'Oe';
            $chars[chr(195) . chr(182)] = 'oe';
            $chars[chr(195) . chr(156)] = 'Ue';
            $chars[chr(195) . chr(188)] = 'ue';
            $chars[chr(195) . chr(159)] = 'ss';
        } elseif ('da' === $locale) {
            $chars[chr(195) . chr(134)] = 'Ae';
            $chars[chr(195) . chr(166)] = 'ae';
            $chars[chr(195) . chr(152)] = 'Oe';
            $chars[chr(195) . chr(184)] = 'oe';
            $chars[chr(195) . chr(133)] = 'Aa';
            $chars[chr(195) . chr(165)] = 'aa';
        }

        $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
        $str = strtr($str, $chars);
        return strtr($str, self::ASCII_MAPPED_VALUES);
    }
}

