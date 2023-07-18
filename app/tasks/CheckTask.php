<?php
/**
 * Created by PhpStorm.
 * User: anmeo
 * Date: 10/11/16
 * Time: 4:54 PM
 */

use \Phalcon\Cli\Task;

class CheckTask extends Task
{
    /**
     *
     */
    function emailsAction(){
        $to = 'Thinh NGUYEN <thinh@expatfinder.com>, thinh@vietoffices.com';
        var_dump(mailparse_rfc822_parse_addresses($to));
        die();
    }
    function versionAction()
    {
        echo Phalcon\Version::get();
    }


    function find_constantAction()
    {
        $contants_1 = $this->constant($this->config->application->modelsDir . "");
        $contants_2 = $this->constant($this->config->application->modulesDir . "");
        $contants_3 = $this->constant($this->config->application->originDir . "/resources/scripts/core/");
        $constants = array_merge($contants_1, $contants_2, $contants_3);

        //echo implode("\r\n", $constants );


        $constantFromDdList = Constant::find();

        foreach ($constants as $key => $constant) {
            foreach ($constantFromDdList as $constantFromDbItem) {
                if ($constantFromDbItem->name == trim($constant)) {
                    unset($constant[$key]);
                }
            }
        }

        $file = fopen($this->config->application->internalLibDir . "constant.txt", "w+");
        fputs($file, implode("\r\n", $constants));
        fclose($file);

    }

    function constant($path)
    {
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        $lists = [];
        foreach ($objects as $name => $object) {
            if (is_file($name)) {
                //echo "$name\n";
                $text_content = file_get_contents($name);
                $searchfor = "_TEXT";
                $pattern = preg_quote($searchfor, '/');
                $pattern = "/([_+0-9A-Z]+)$pattern/m";

                if (preg_match_all($pattern, $text_content, $matches)) {
                    //echo "Found matches:\n";
                    //var_dump( $matches );
                    //echo implode("\r\n", $matches[0] );
                    if (isset($matches[0]) && count($matches[0]) > 0) {
                        foreach ($matches[0] as $item) {
                            $txt = trim(trim($item, "'"), '"');
                            $lists[$txt] = $txt;
                        }
                    }
                    //echo "\r\n";
                }

            }
        }
        return $lists;
    }

    public function find_controllersAction()
    {
        $controllers = $this->getAllControllers();
        var_dump($controllers);
    }

    public function getAllControllers()
    {
        $files = scandir(__DIR__ . '/../../private/modules/gms/controllers/api/');
        $controllers = array();
        foreach ($files as $file) {
            if ($controller = $this->extractController($file)) {
                $className = $controller;
                $controllers[] = [
                    'name' => $controller,
                    'actions' => $this->getAllActions($className)
                ];
            }
        }
        return $controllers;
    }

    public function getAllActions($controller)
    {
        $functions = get_class_methods($controller);
        $actions = array();
        foreach ($functions as $name) {
            $actions[] = $this->extractAction($name);
        }
        return array_filter($actions);
    }

    protected function extractAction($name)
    {
        $action = explode('Action', $name);
        if ((count($action) > 1)) {
            return $action[0];
        }
    }

    protected function extractController($name)
    {
        $filename = explode('.php', $name);
        if (count(explode('Controller.php', $name)) > 1) {
            # code...
            if (count($filename) > 1) {
                return $filename[0];
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function firstletterAction()
    {

        $array_string = [
            'Bupa',
            'Cigna Global',
            'Aviva',
            'Thompson Reuters',
            'Walmart',
            'State Grid',
            'China National Petroleum',
            'Sinopec Group',
            'Royal Dutch Shell',
            'Exxon Mobil',
            'Volkswagen',
            'Toyota',
            'Apple',
            'BP',
            'Europa Moters',
            'Boing',
            'Airbus',
            'Air bnb',
            'Adidas',
            'Adibas',
            'AdiTask',
            'Berkshire Hathaway',
            'McKesson',
            'Samsung Electronics',
            'Glencore',
            'General Motors',
            'Societe General',
            'BNP Paribas',
            'La Banque Postale',
            'Sofitel Legend Metropole Hanoi',
            'Wikipedia',
            'Coma',
            'Panasonic',
            'Panasunic',
        ];

        $limit = 4;
        $voys = ['A', 'E', 'I', 'O', 'U', 'Y'];

        foreach ($array_string as $string) {
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

                if( count($arrayWord) >= 3){
                    foreach( $arrayWord as $key => $aryWord){
                        if( $key < 1) {
                            $shortname .= $aryWord[0] . $aryWord[1];
                        }
                        if( $key == 1) {
                            $shortname .= $aryWord[0];
                        }
                        if( $key == 2) {
                            $shortname .= $aryWord[0];
                        }
                    }
                }elseif( count($arrayWord) == 2){
                    foreach( $arrayWord as $key => $aryWord){
                        if( $key <= 1) {
                            $shortname .= $aryWord[0] . $aryWord[1];
                        }
                    }
                }elseif( count($arrayWord) == 1) {

                    $shortname = $array[0] . $array[1] . $array[2] . (isset($array[3]) ? $array[3] : '');
                }
            }
            echo $string . " => " . $shortname . "\r\n";
        }

        /*
        preg_match_all("/[A-Z]/", ucwords(strtolower($string)), $matches);
        if( isset($matches[0])  && is_array( $matches[0] ) && count($matches[0]) > 0 ){
            echo implode("",$matches[0]) . "\r\n";
        }
        */
    }
}