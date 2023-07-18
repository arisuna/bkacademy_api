<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class NeedFormGabaritSection extends \Reloday\Application\Models\NeedFormGabaritSectionExt
{
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\NeedFormGabaritItem', 'need_form_gabarit_section_id', [
            'alias' => 'NeedFormGabaritItems',
            'params'   => [
                'conditions' => "need_form_gabarit_item_id is null and answer_format != :matrix_type:",
                'bind' => [
                    'matrix_type' => NeedFormGabaritItem::ANSWER_FORMAT_MATRIX
                ],
                'order' => 'position ASC, created_at ASC'
            ],
        ]);

        $this->belongsTo('need_form_gabarit_id', 'Reloday\Gms\Models\NeedFormGabarit', 'id', ['alias' => 'NeedFormGabarit']);
    }

    /**
     *
     */
    public function getDetailSectionContent(){
        $items = $this->getNeedFormGabaritItems();

        $return = [];
        foreach ($items as $item){
            $data = $item->toArray();

            $data['config'] =  [
                'required' => $item->getIsMandatory() == NeedFormGabaritItem::REQUIRED ? true : false,
                'maxSelections' => is_numeric($item->getLimit()) ? (int)$item->getLimit() : null,
            ];

            $data['is_mandatory'] = (int)$item->getIsMandatory();
            $data['limit'] = is_numeric($item->getLimit()) ? (int)$item->getLimit() : null;
            $data['id'] = (int)$item->getId();

            $data['type'] = $item->getType();


            if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT){
                $data["placeholder"] = "ENTER_YOUR_REPLY_TEXT";
            } else if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER){
                $data["placeholder"] = "ENTER_NUMERICAL_VALUE_TEXT";
            }

            if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_LINEAR || $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_YESNO){
                if (Helpers::__isJsonValid($item->getAnswerContent())) {
                    $options = json_decode($item->getAnswerContent(), true);
                    $data['options'] = $options;
                }

            }

            if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION ||
                $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST ||
                $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION){
                $subItems = $item->getSubItems();
                $options = [];
                $isChecked = false;
                foreach ($subItems as $subItem){
                    $option = [];
                    $option['id'] = $subItem->getId();
                    $option['value'] = $subItem->getQuestion();
                    if($subItem->getNextSectionId() != null){
                        $option['next_section_id'] = (int)$subItem->getNextSectionId();
                        $isChecked = true;
                    }
                    $options[] = $option;
                }
                $data['options'] = $options;
                if($isChecked){
                    $data['config']['checked'] = $isChecked;
                }
            }

            $return[] = $data;
        }

        return $return;
    }

    /**
     * @return mixed
     */
    public function parsedDataToArray(){
        $item = $this->toArray();
        if($this->getNextSectionId() == -1){
            $item['section_name'] = 'SUBMIT_FORM_TEXT';
        }else{
            $needFormGabarit = $this->getNeedFormGabarit();
            $item['section_name'] = $needFormGabarit ? $needFormGabarit->getSectionNameBasedOnIndex($this->getNextSectionId()) : '';
        }

        $items = $this->getNeedFormGabaritItems([
            'conditions' => 'answer_format != :matrix_type:',
            'bind' => [
                'matrix_type' => NeedFormGabaritItem::ANSWER_FORMAT_MATRIX
            ]
        ]);
        $arrItems = [];
        //Reorder Items
        if(count($items) > 0){
            foreach ($items as $i => $gabaritItem){
                $gabaritItem->setPosition($i);
                $resultData = $gabaritItem->__quickSave();

                $gabaritItemArr = $gabaritItem->toArray();
                if(count($gabaritItem->getNeedFormGabaritItemSystemFields()) > 0){
                    $gabaritItemArr['hasMatchField'] = true;
                }else{
                    $gabaritItemArr['hasMatchField'] = false;
                }

                $arrItems[] = $gabaritItemArr;
            }
        }

        $item['items'] = $arrItems;

        return $item;
    }


    /**
     * @param $request
     */
    public function getDetailSectionContentMappingWithRequest($request){
        if(!$request){
            return [];
        }
        $items = $this->getNeedFormGabaritItems();

        $return = [];
        foreach ($items as $item){
            $data = $item->toArray();

            $data['config'] =  [
                'required' => $item->getIsMandatory() == NeedFormGabaritItem::REQUIRED ? true : false,
                'maxSelections' => is_numeric($item->getLimit()) ? (int)$item->getLimit() : null,
            ];

            $data['is_mandatory'] = (int)$item->getIsMandatory();
            $data['limit'] = is_numeric($item->getLimit()) ? (int)$item->getLimit() : null;
            $data['id'] = (int)$item->getId();

            $data['type'] = $item->getType();

            if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT){
                $data["placeholder"] = "ENTER_YOUR_REPLY_TEXT";
            } else if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER){
                $data["placeholder"] = "ENTER_NUMERICAL_VALUE_TEXT";
            }


            if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_LINEAR || $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_YESNO){
                if (Helpers::__isJsonValid($item->getAnswerContent())) {
                    $options = json_decode($item->getAnswerContent(), true);
                    $data['options'] = $options;
                }

            }

            switch ($item->getAnswerFormat()){
                case NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION:
                    $subItems = $item->getSubItems();
                    $options = [];
                    $isChecked = false;
                    $value = [];
                    foreach ($subItems as $subItem){
                        $needFormRequestAnswer = NeedFormRequestAnswer::findFirst([
                            'conditions' => 'need_form_request_id = :need_form_request_id: and need_form_gabarit_item_id = :need_form_gabarit_item_id:',
                            'bind' => [
                                'need_form_request_id' => $request->getId(),
                                'need_form_gabarit_item_id' => $subItem->getId(),
                            ]
                        ]);
                        $option = [];
                        $option['id'] = $subItem->getId();
                        $option['value'] = $subItem->getQuestion();
                        if($needFormRequestAnswer && $needFormRequestAnswer->getAnswer() == $subItem->getQuestion()){
                            $option['selected'] = true;
                            $option['value'] = $needFormRequestAnswer->getAnswer();
                            $option['need_form_request_answer_id'] = $needFormRequestAnswer->getId();
                            if($needFormRequestAnswer->getAnswer() == NeedFormGabaritItem::OTHER_TYPE){
                                $option['content'] = $needFormRequestAnswer->getContent();
                                $value[] = $needFormRequestAnswer->getContent();
                            }else{
                                $value[] = $needFormRequestAnswer->getAnswer();
                            }
                        }else{
                            $option['selected'] = false;
                        }
                        if($subItem->getNextSectionId() != null){
                            $option['next_section_id'] = (int)$subItem->getNextSectionId();
                            $isChecked = true;
                        }
                        $options[] = $option;
                    }
                    $data['options'] = $options;
                    $data['value'] = $value ? implode(', ', $value) : '';
                    if($isChecked){
                        $data['config']['checked'] = $isChecked;
                    }
                    break;
                case  NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST:
                case  NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION:
                    $subItems = $item->getSubItems();
                    $options = [];
                    $isChecked = false;

                    $needFormRequestAnswer = NeedFormRequestAnswer::findFirst([
                        'conditions' => 'need_form_request_id = :need_form_request_id: and need_form_gabarit_item_id = :need_form_gabarit_item_id:',
                        'bind' => [
                            'need_form_request_id' => $request->getId(),
                            'need_form_gabarit_item_id' => $item->getId(),
                        ]
                    ]);

                    foreach ($subItems as $subItem){
                        $option = [];
                        $option['id'] = $subItem->getId();
                        $option['value'] = $subItem->getQuestion();
                        if($subItem->getNextSectionId() != null){
                            $option['next_section_id'] = (int)$subItem->getNextSectionId();
                            $isChecked = true;
                        }
                        $options[] = $option;
                    }
                    if($needFormRequestAnswer){
                        $data['need_form_request_answer_id'] = (int)$needFormRequestAnswer->getId();
                        $data['value'] = $needFormRequestAnswer->getAnswer();

                        if($needFormRequestAnswer->getAnswer() == NeedFormGabaritItem::OTHER_TYPE){
                            $data['content'] = $needFormRequestAnswer->getContent();
                        }
                    }

                    $data['options'] = $options;
                    if($isChecked){
                        $data['config']['checked'] = $isChecked;
                    }
                    break;
                case NeedFormGabaritItem::ANSWER_FORMAT_TIME:
                    $needFormRequestAnswer = NeedFormRequestAnswer::findFirst([
                        'conditions' => 'need_form_request_id = :need_form_request_id: and need_form_gabarit_item_id = :need_form_gabarit_item_id:',
                        'bind' => [
                            'need_form_request_id' => $request->getId(),
                            'need_form_gabarit_item_id' => $item->getId(),
                        ]
                    ]);

                    if($needFormRequestAnswer){
                        $data['value'] = $needFormRequestAnswer->getAnswer();

                        if($needFormRequestAnswer->getAnswer()){
                            $times =  explode(":", $needFormRequestAnswer->getAnswer());
                            $hourNumber = is_numeric($times[0]) ? intval($times[0]) : 0;
                            $minuteNumber = is_numeric($times[1]) ? intval($times[1]) : 0;

                            $hour = str_pad($hourNumber, 2, 0, STR_PAD_LEFT);
                            $minute = str_pad($minuteNumber, 2, 0, STR_PAD_LEFT);

                            $data['value'] = $hour . ':' . $minute;
                        }

                        $data['need_form_request_answer_id'] = (int)$needFormRequestAnswer->getId();
                    }
                    break;
                case NeedFormGabaritItem::ANSWER_FORMAT_DATE:
                case NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER:
                    $needFormRequestAnswer = NeedFormRequestAnswer::findFirst([
                        'conditions' => 'need_form_request_id = :need_form_request_id: and need_form_gabarit_item_id = :need_form_gabarit_item_id:',
                        'bind' => [
                            'need_form_request_id' => $request->getId(),
                            'need_form_gabarit_item_id' => $item->getId(),
                        ]
                    ]);

                    if($needFormRequestAnswer){
                        $data['value'] = (int)$needFormRequestAnswer->getAnswer();
                        $data['need_form_request_answer_id'] = (int)$needFormRequestAnswer->getId();
                    }
                    break;
                default:
                    $needFormRequestAnswer = NeedFormRequestAnswer::findFirst([
                        'conditions' => 'need_form_request_id = :need_form_request_id: and need_form_gabarit_item_id = :need_form_gabarit_item_id:',
                        'bind' => [
                            'need_form_request_id' => $request->getId(),
                            'need_form_gabarit_item_id' => $item->getId(),
                        ]
                    ]);

                    if($needFormRequestAnswer){
                        $data['value'] = $needFormRequestAnswer->getAnswer();
                        $data['need_form_request_answer_id'] = (int)$needFormRequestAnswer->getId();
                    }
                    break;
            }

            $return[] = $data;
        }

        return $return;
    }
}
