<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Exception;
use Phalcon\Security\Random;
use \SMXD\Api\Controllers\ModuleApiController;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\RelodayS3Helper;
use SMXD\Application\Models\AppExt;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\CompanyExt;
use SMXD\Application\Models\MediaAttachmentExt;
use SMXD\Application\Models\MediaExt;
use SMXD\Application\Models\PropertyDataExt;
use SMXD\Application\Models\PropertyExt;
use SMXD\Application\Models\UserAuthorKeyExt;
use SMXD\Application\Models\UserProfileExt;
use Phalcon\Utils\Slug as PhpSlug;

/**
 * Concrete implementation of Api module controller
 *
 * @RoutePrefix("/api/api")
 */
class PropertyController extends ModuleApiController
{
    /**
     * @Route("/index", paths={module="api"}, methods={"GET"}, name="api-index-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->response->setJsonContent([
            'success' => true
        ]);
        $this->response->send();
    }

    /**
     * Add New Propert from Call API with AddOn Key
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addAction()
    {

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Headers: Token');
        $this->view->disable();
        $token = Helpers::__getHeaderValue('token');
        $isTest = Helpers::__getHeaderValue('is-test');


        // Find user by token key
        $user = UserAuthorKeyExt::__findUserByAddonKey($token);

        if ($user && $user->isGms()) {

            $company = $user->getCompany();

            if ($company) {

                $property = new PropertyExt();
                $property->setUuid(ApplicationModel::uuid());
                $property->setCompanyId($user->getCompanyId());
                $property->setStatus(PropertyExt::STATUS_ACTIVATED);
                $property->setNumber($property->generateNumber());

                $property->setUrl($this->request->getPost('link'));
                $property->setName($this->request->getPost('title'));

                $summary = Helpers::limit_text_length($this->request->getPost('description'), 250);
                $property->setSummary($summary);

                $property->setIsBuilding((int)$this->request->getPost('is_building'));
                $property->setType($this->request->getPost('property_type'));
                $property->setTown($this->request->getPost('town'));
                $property->setCountryId($this->request->getPost('country'));
                $property->setRentAmount($this->request->getPost('rent_amount'));
                $property->setRentPeriod($this->request->getPost('period'));
                $property->setRentCurrency($this->request->getPost('currency'));
                $property->setSize($this->request->getPost('surface'));
                $property->setSizeUnit($this->request->getPost('surface_unit'));

                $images = (array)$this->request->getPost('images');
                $image_selected = [];
                foreach ($images as $img) {
                    $image_selected[] = "1";
                }

                $resultCreate = $property->__quickCreate();

                if ($resultCreate['success'] == true) {
                    // save property data
                    $property_data = new PropertyDataExt();
                    $property_data->setDescription($this->request->getPost('description'));
                    $property_data->setPropertyId($property->getId());
                    $property_data->setScrapeContent(json_encode([
                        'images' => $images,
                        'image_selected' => $image_selected,
                        'meta_title' => $this->request->getPost('title'),
                        'meta_description' => $this->request->getPost('description')
                    ]));
                    $resultCreatePropertyData = $property_data->__quickCreate();


                    // Find app of current user
                    $app = AppExt::findFirst((int)$company->getAppId());
                    $app_name = $app instanceof AppExt ? $app->getUrl() : '';
                    $url_detail = '';
                    if ($app_name) {
                        $detail = $property->getIsBuilding() ? '/#/app/properties/edit_building/' . $property->getId() : '/#/app/properties/edit/' . $property->getId();
                        $url_detail = $app->getFrontendUrl() . $detail;
                    }

                    // Upload and save media
                    $upload = $this->__processFileUploaded($file_content = $this->request->getPost('image_data'), [
                        'user_login_id' => $user->getUserLoginId(),
                        'user_profile_uuid' => $user->getUuid(),
                        'company_id' => $company->getId()
                    ]);

                    if ($upload['success']) {

                        $mediaFiles = $upload['media'];
                        $resultAttach = MediaAttachmentExt::__createAttachments([
                            'objectUuid' => $property->getUuid(),
                            'ownerCompany' => $company,
                            'fileList' => $upload['media'],
                            'userProfile' => $user,
                        ]);
                    }

                    $return = [
                        'success' => true,
                        'msg' => 'Save succeed',
                        'view' => $url_detail,
                        '$resultAttach' => isset($resultAttach) ? $resultAttach : false,
                    ];


                } else {
                    $return = [
                        'success' => false,
                        'msg' => 'Save failed',
                        'detail' => $resultCreate['detail']
                    ];
                }
            } else {
                $return = [
                    'success' => false,
                    'msg' => 'User did not exist'
                ];
            }
        } else {
            $return = [
                'success' => false,
                'msg' => 'Can not authorized'
            ];
        }


        end_of_function:

        $return['postData'] = $this->request->getPost();
        $return['ip'] = $this->request->getClientAddress();

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function errorAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false]);
        return $this->response->send();
    }

    /**
     * @param array $data
     * @param array $params user_login_id, company_id
     * @return array
     */
    private function __processFileUploaded($data = [], $params = [])
    {
        $result = [
            'success' => true,
            'media' => []
        ];

        if (!is_array($data) || count($data) == 0) {
            $result['message'] = 'Property has no url';
            goto end_of_function;
        }

        foreach ($data as $k => $v) {
            if ($k == 'url') {
                foreach ($v as $k1 => $url) {
                    $url_arr = preg_split('/\//', $url);
                    $file_name = end($url_arr);

                    try {
                        $contentFile = Helpers::__urlGetContent($url);
                        $fileObject = new \finfo(FILEINFO_MIME_TYPE);
                        $fileSize = strlen($contentFile);
                        $mime_type = $fileObject->buffer($contentFile);
                    } catch (Exception $e) {
                        $return['success'] = false;
                        $return['message'] = $e->getMessage();
                        goto end_of_function;
                    }


                    if (isset(MediaExt::$images_extensions[$mime_type])) {

                        $file_info = new \stdClass();
                        $file_info->name = $file_name;
                        $file_info->basename = $file_name; //PhpSlug::generate($real_name);
                        $file_info->extension = MediaExt::$images_extensions[$mime_type]['extension'];
                        $file_info->type = MediaExt::$images_extensions[$mime_type]['type']; // type
                        $file_info->mime = $mime_type;
                        $file_info->size = $fileSize;
                        $file_info->real_type = MediaExt::$images_extensions[$mime_type]['type']; // type of file

                    } else {
                        $result['message'] = 'Type is not supported';
                        goto end_of_function;
                    }


                    $mediaUuid = ApplicationModel::uuid();
                    /** SAVE TO DATABASE */
                    $media = new MediaExt();
                    $media->setName($mediaUuid);
                    $media->setNameStatic($file_info->name);
                    $media->setCompanyId($params['company_id']);
                    $media->setUuid($mediaUuid);
                    $media->setFilename($mediaUuid . '.' . $file_info->extension);
                    $media->setFileExtension($file_info->extension);
                    $media->setFileType(MediaExt::__getFileType($file_info->extension));
                    $media->setMimeType($file_info->type);
                    $media->loadMediaType();
                    $media->setCreatedAt(date('Y-m-d H:i:s'));
                    $media->setUserLoginId($params['user_login_id']);
                    $media->setUserProfileUuid($params['user_profile_uuid']);
                    $media->setIsHosted(MediaExt::STATUS_HOSTED);
                    $media->setIsHidden(ModelHelper::YES);
                    $uploader = $media->uploadToS3FromContent($contentFile);

                    if ($uploader['success'] == true) {
                        $resultSaveMedia = $media->__quickCreate();
                        if ($resultSaveMedia['success'] == false) {
                            $result['success'] = false;
                            $result['message'] = $resultSaveMedia['message'];
                        } else {
                            $result['media'][] = $media;
                        }
                    } else {
                        $result = $uploader;
                        break;
                    }
                }
            } else {
                // base64
                foreach ($v as $k2 => $obj) {

                    $random = new Random();
                    $uuidName = $random->uuid();
                    $objectData = base64_decode($obj['data']);
                    try {
                        $fileObject = new \finfo(FILEINFO_MIME_TYPE);
                        $fileSize = strlen($objectData);
                        $mime_type = $fileObject->buffer($objectData);
                    } catch (Exception $e) {
                        $result = ['success' => false, 'message' => $e->getMessage()];
                        goto end_of_function;
                    }

                    if (isset(MediaExt::$images_extensions[$mime_type])) {

                        $file_info = new \stdClass();
                        $file_info->name = $uuidName;
                        $file_info->basename = $uuidName; //PhpSlug::generate($real_name);
                        $file_info->extension = MediaExt::$images_extensions[$mime_type]['extension'];
                        $file_info->type = MediaExt::$images_extensions[$mime_type]['type']; // type
                        $file_info->mime = $mime_type;
                        $file_info->size = $fileSize;
                        $file_info->real_type = MediaExt::$images_extensions[$mime_type]['type']; // type of file

                    } else {
                        goto end_of_function;
                    }


                    $mediaUuid = ApplicationModel::uuid();

                    /** SAVE TO DATABASE */
                    $media = new MediaExt();
                    $media->setName($mediaUuid);
                    $media->setNameStatic($file_info->name);
                    $media->setCompanyId($params['company_id']);
                    $media->setUuid($mediaUuid);
                    $media->setFilename($mediaUuid . '.' . $file_info->extension);
                    $media->setFileExtension($file_info->extension);
                    $media->setFileType(MediaExt::__getFileType($file_info->extension));
                    $media->setMimeType($file_info->type);
                    $media->loadMediaType();
                    $media->setCreatedAt(date('Y-m-d H:i:s'));
                    $media->setUserLoginId($params['user_login_id']);
                    $media->setUserProfileUuid($params['user_profile_uuid']);
                    $media->setIsHosted(MediaExt::STATUS_HOSTED);
                    $media->setIsHidden(ModelHelper::YES);
                    $uploader = $media->uploadToS3FromContent($objectData);

                    if ($uploader['success'] == true) {
                        $resultSaveMedia = $media->__quickCreate();
                        if ($resultSaveMedia['success'] == false) {
                            $result['success'] = false;
                            $result['message'] = $resultSaveMedia['message'];
                        } else {
                            $result['media'][] = $media;
                        }
                    } else {
                        $result = $uploader;
                        break;
                    }
                }
            }
        }

        end_of_function:
        return $result;
    }

    /** Format Size of file */
    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

}
