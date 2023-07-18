<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Constant;
use Reloday\Gms\Models\ConstantTranslation;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TranslateController extends ModuleApiController
{
    /**
     * @Route("/translate", paths={module="gms"}, methods={"GET"}, name="gms-translate-index")
     */
    public function indexAction()
    {

    }

    public function datatableAction($lang = 'en')
    {

        $keywords = [
            'SEARCH_BTN_TEXT' => 'SEARCH_BTN_TEXT',
            'RECORD_PER_PAGE_TEXT' => 'RECORD_PER_PAGE_TEXT',
            'SHOW_PAGE_TEXT' => 'SHOW_PAGE_TEXT',
            'OF_TEXT' => 'OF_TEXT',
            'DATA_NOT_FOUND_TEXT' => 'DATA_NOT_FOUND_TEXT',
            'NO_RECORD_AVAILABLE_TEXT' => 'NO_RECORD_AVAILABLE_TEXT',
            'NO_DATA_AVAILABLE_TEXT' => 'NO_DATA_AVAILABLE_TEXT',
            'FILTER_FROM_TEXT' => 'FILTER_FROM_TEXT',
            'TOTAL_RECORD_TEXT' => 'TOTAL_RECORD_TEXT',
            'PREVIOUS_BTN_TEXT' => 'PREVIOUS_BTN_TEXT',
            'NEXT_BTN_TEXT' => 'NEXT_BTN_TEXT'
        ];


        // Search keywords in constant
        $constants = Constant::find([
            'name IN ("' . implode('","', $keywords) . '")'
        ]);

        // search text

        if (count($constants)) {
            $id_list = [];
            $key_maps = [];
            foreach ($constants as $constant) {
                $id_list[] = $constant->getId();
                $key_maps[$constant->getId()] = $constant->getName();
            }
            $translates = ConstantTranslation::find([
                'conditions' => 'constant_id IN(' . implode(',', $id_list) . ') AND language="' . $lang . '"'
            ]);

            if (count($translates)) {
                foreach ($translates as $translate) {
                    if (!empty($key_maps[$translate->getConstantId()])) {
                        $keywords[$key_maps[$translate->getConstantId()]] = $translate->getValue();
                    }
                }
            }
        }

        $result = [
            "sSearch" => '<em class=\"fa fa-search\"></em> ' . $keywords['SEARCH_BTN_TEXT'] . ':',
            "sLengthMenu" => '_MENU_ ' . $keywords['RECORD_PER_PAGE_TEXT'],
            "sInfo" => $keywords['RECORD_PER_PAGE_TEXT'] . ' _PAGE_ ' . $keywords['OF_TEXT'] . ' _PAGES_',
            "sZeroRecords" => $keywords['DATA_NOT_FOUND_TEXT'],
            "sInfoEmpty" => $keywords['NO_RECORD_AVAILABLE_TEXT'],
            "sEmptyTable" => $keywords['NO_DATA_AVAILABLE_TEXT'],
            "sInfoFiltered" => '(' . $keywords['FILTER_FROM_TEXT'] . ' _MAX_ ' . $keywords['TOTAL_RECORD_TEXT'] . ')',
            "oPaginate" => [
                "sPrevious" => '<em class="fa fa-angle-left mr-sm"></em> ' . $keywords['PREVIOUS_BTN_TEXT'],
                "sNext" => $keywords['NEXT_BTN_TEXT'] . ' <em class="fa fa-angle-right ml-sm"></em>'
            ]
        ];

        $this->view->disable();
        echo json_encode($result);
    }
}
