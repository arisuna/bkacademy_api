<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Tag extends \Reloday\Application\Models\TagExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}


	/**
	 * [__save description]
	 * @param  {Array}  $custom [description]
	 * @return {[type]}         [description]
	 */
	public function __save($custom = []){

		$req = new Request();
        $model = $this;

        if( !($model->getId() > 0) ){
            if ($req->isPut()) {
                $data_id = isset($custom['tag_id']) && $custom['tag_id']  > 0 ? $custom['tag_id'] : $req->getPut('tag_id');
                if( $data_id > 0 ){
                    $model = $this->findFirstById($data_id);
                    if (!$model instanceof $this) {
                        return [
                            'success' => false,
                            'message' => 'DATA_NOT_FOUND_TEXT',
                        ];
                    }
                }
            }
        }

        /** @var [varchar] [set uunique id] */
        if( property_exists($model, 'uuid') && method_exists( $model,'getUuid')  && method_exists( $model,'setUuid')  ){
	        if( property_exists($model, 'uuid') && method_exists( $model,'getUuid')  && method_exists( $model,'setUuid')  ){
                if( $model->getUuid() == ''){
                    $random = new Random;
                    $uuid = $random->uuid();
                    $model->setUuid( $uuid );
                }
            }
	    }
	    /****** ALL ATTRIBUTES ***/
	    $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach( $fields as $key => $field_name  ){
            if( $field_name != 'id'
                && $field_name != 'uuid'
                && $field_name !='created_at'
                &&  $field_name !='updated_at'
                && $field_name!="password"){

                if( !isset($fields_numeric[$field_name] ) ){
                    $field_name_value = Helpers::__getRequestValueWithCustom( $field_name, $custom );
                    $field_name_value = Helpers::__coalesce( $field_name_value , $model->get($field_name) );
                    $field_name_value = $field_name_value != '' ? $field_name_value:$model->get($field_name);
                    $model->set( $field_name, $field_name_value );

                }else{

                    $field_name_value = Helpers::__getRequestValueWithCustom( $field_name, $custom );
                    $field_name_value = Helpers::__coalesce( $field_name_value , $model->get($field_name) );
                    if( $field_name_value != '' && !is_null($field_name_value)){
                        $model->set( $field_name, $field_name_value );
                    }
                }
            }
        }
	    /****** YOUR CODE ***/

	    /****** END YOUR CODE **/
	    try{
            if( $model->getId() == null ){

            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        }catch(\PDOException $e){
            $result = [
                'success'   => false,
                'message'       => 'DATA_SAVE_FAIL_TEXT',
                'detail'    => $e->getMessage(),
            ];
            return $result;
        }catch(Exception $e){
             $result = [
                 'success'   => false,
                 'message'       => 'DATA_SAVE_FAIL_TEXT',
                 'detail'    => $e->getMessage(),
             ];
             return $result;
         }
	}

}
