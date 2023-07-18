<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class NeedFormGabaritItem extends \Reloday\Application\Models\NeedFormGabaritItemExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const REQUIRED = 1;
    const NOT_REQUIRED = 0;

    const ANSWER_FORMAT_PARAGRAPH = 0;
    const ANSWER_FORMAT_INPUT_TEXT = 1;
    const ANSWER_FORMAT_INPUT_NUMBER = 2;
    const ANSWER_FORMAT_SINGLE_OPTION = 3;
    const ANSWER_FORMAT_MULTIPLE_OPTION = 4;
    const ANSWER_FORMAT_DROPDOWN_LIST = 5;
    const ANSWER_FORMAT_LINEAR = 6;
    const ANSWER_FORMAT_MATRIX = 7;
    const ANSWER_FORMAT_DATE = 8;
    const ANSWER_FORMAT_TIME = 9;
    const ANSWER_FORMAT_YESNO = 10;
    const ANSWER_FORMAT_AMOUNT = 11;

    const DIRECTION_HORIZONTAL = 1;
    const DIRECTION_VERTICAL = 0;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('need_form_gabarit_id', 'Reloday\Gms\Models\NeedFormGabarit', 'id', ['alias' => 'NeedFormGabarit']);

        $this->hasMany('id', 'Reloday\Gms\Models\NeedFormGabaritItem', 'need_form_gabarit_item_id', [
            'alias' => 'SubItems',
            'params'   => [
//                'conditions' => "need_form_gabarit_item_id = :id:",
//                'bind' => [
//                    'id' => $this->getId()
//                ],
                'order' => 'position ASC'
            ],
        ]);

        $this->belongsTo('need_form_gabarit_section_id', 'Reloday\Gms\Models\NeedFormGabaritSection', 'id', ['alias' => 'NeedFormGabaritSection']);
    }

    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        if ($this->getNeedFormGabarit() && $this->getNeedFormGabarit()->belongsToGms()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function __savePosition($position = 0)
    {
        if ($this->getId() > 0) {
            $model = $this;
            $model->setPosition($position);
            try {
                if ($model->save()) {
                    return true;
                } else {
                    return false;
                }
            } catch (\PDOException $e) {
                return false;
            } catch (Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function parseAnswerContent()
    {
        $return = [];
        if ($this->getAnswerFormat() == self::ANSWER_FORMAT_MULTIPLE_OPTION ||
            $this->getAnswerFormat() == self::ANSWER_FORMAT_SINGLE_OPTION ||
            $this->getAnswerFormat() == self::ANSWER_FORMAT_SURVEY_OPTION) {

            $array = json_decode($this->getAnswerContent(), true);

            foreach ($array as $value) {
                $return[] = ['value' => $value, 'selected' => false];
            }
        }
        return $return;
    }

    public function setType($item)
    {
        switch ($item->type) {
            case "textarea":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_PARAGRAPH);
                break;
            case "input":
                if (isset($item->config->type) && $item->config->type == "number")
                    $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER);
                else $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT);
                break;
            case "multipleChoices":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION);
                break;
            case "checkboxes":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION);
                break;
            case "chooseFromList":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST);
                break;
            case "linear":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_LINEAR);
                break;
            case "matrix":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_MATRIX);
                break;
            case "date":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_DATE);
                break;
            case "time":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_TIME);
                break;
            case 'yesno':
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_YESNO);
                break;
        }
        return;
    }


    public function setTypeV2($item)
    {
        switch ($item['type']) {
            case "textarea":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_PARAGRAPH);
                break;
            case "text":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT);
                break;
            case "number":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER);
                break;
            case "input":
                if (isset($item['config']['type']) && $item['config']['type'] == "number")
                    $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER);
                else $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT);
                break;
            case "multipleChoices":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION);
                break;
            case "checkboxes":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION);
                break;
            case "chooseFromList":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST);
                break;
            case "linear":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_LINEAR);
                break;
            case "matrix":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_MATRIX);
                break;
            case "date":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_DATE);
                break;
            case "time":
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_TIME);
                break;
            case 'yesno':
                $this->setAnswerFormat(NeedFormGabaritItem::ANSWER_FORMAT_YESNO);
                break;
        }
        return;
    }

    /**
     * @return array
     */
    public function getContent()
    {
        $type_inside = "number";
        $type = "";
        switch ($this->getAnswerFormat()) {
            case NeedFormGabaritItem::ANSWER_FORMAT_PARAGRAPH:
                $type = "textarea";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT:
                $type = "input";
                $type_inside = "text";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER:
                $type = "input";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION:
                $type = "multipleChoices";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION:
                $type = "checkboxes";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST:
                $type = "chooseFromList";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_LINEAR:
                $type = "linear";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_MATRIX:
                $type = "matrix";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_DATE:
                $type = "date";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_TIME:
                $type = "time";
                break;
        }
        $options = [];
        if (Helpers::__isJsonValid($this->getAnswerContent())) {
            $options = json_decode($this->getAnswerContent(), true);

            if (!is_null($options) && is_array($options) && count($options) > 0) {
                foreach ($options as $key => $option) {
                    if (is_numeric($key) && is_array($option)) {
                        if (is_numeric($key)) {
                            if (isset($option['goToSection']) && is_numeric($option['goToSection']) && $option['goToSection'] >= 0) {
                                $options[$key]['next_section_id'] = $options[$key]['goToSection'];
                            } elseif (isset($option['next_section_id']) && is_numeric($option['next_section_id']) && $option['next_section_id'] >= 0) {
                                $options[$key]['goToSection'] = $options[$key]['next_section_id'];
                            }
                            if (is_array($option) && count($option) > 0) {
                                $options[$key]['next_section_id'] = isset($options[$key]['next_section_id']) ? intval($options[$key]['next_section_id']) : null;
                                $options[$key]['goToSection'] = isset($options[$key]['goToSection']) ? intval($options[$key]['goToSection']) : null;
                            }
                        } else {
                            if ($key == 'columns' || $key == 'rows') {
                                unset($options[$key]['goToSection']);
                                unset($options[$key]['next_section_id']);
                            }
                        }
                    }
                }
            }
        }


        $item = [
            'config' => [
                'required' => $this->getIsMandatory() == self::REQUIRED ? true : false,
                'maxSelections' => intval($this->getLimit()),
                'direction' => $this->getDirection() == self::DIRECTION_HORIZONTAL ? "horizontal" : "vertical",
                'type' => $type_inside,
                'checked' => $this->getChecked()
            ],
            'type' => $type,
            'props' => [
                'title' => $this->getQuestion()
            ],
            'options' => $options,
            'id' => $this->getId()
        ];
        return $item;
    }

    /**
     * @return bool
     */
    public function getChecked()
    {
        $answers = json_decode($this->getAnswerContent(), true);
        if (is_array($answers) && count($answers) > 0) {
            foreach ($answers as $answer) {
                if (isset($answer["goToSection"]) || isset($answer["next_section_id"])) {
                    return true;
                }
            }
        }
        return false;
    }


    public function getType(){
        $type_inside = "number";
        $type = "";
        switch ($this->getAnswerFormat()) {
            case NeedFormGabaritItem::ANSWER_FORMAT_PARAGRAPH:
                $type = "textarea";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_INPUT_TEXT:
                $type = "text";
                $type_inside = "text";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_INPUT_NUMBER:
                $type = "number";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION:
                $type = "multipleChoices";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION:
                $type = "checkboxes";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST:
                $type = "chooseFromList";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_LINEAR:
                $type = "linear";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_MATRIX:
                $type = "matrix";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_DATE:
                $type = "date";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_TIME:
                $type = "time";
                break;
            case NeedFormGabaritItem::ANSWER_FORMAT_YESNO:
                $type = "yesno";
                break;
        }
        return $type;
    }
}
