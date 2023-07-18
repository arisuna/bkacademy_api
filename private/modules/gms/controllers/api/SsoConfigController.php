<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Lib\SamlHelper;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\SsoIdpConfig;

class SsoConfigController extends BaseController
{
    /**
     * @Route("/sso-idp-config", paths={module="gms"}, methods={"GET"}")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $results = ['success' => true, 'data' => []];
        $ssoIdpConfig = SsoIdpConfig::findFirst([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId()
            ],
            "order" => "created_at DESC",
        ]);
        if ($ssoIdpConfig) {
            $results['data'] = $ssoIdpConfig;
        }
        end:
        $this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * @Route("/sso-idp-config", paths={module="hr"}, methods={"GET"}")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $results = ['success' => false,'message' => 'DATA_NOT_FOUND_TEXT', 'data' => []];

        $company_uuid = Helpers::__getRequestValue('company_uuid');
        $company = Company::findFirstByUuid($company_uuid);
        if ($company){
            $ssoIdpConfigs = SsoIdpConfig::find([
                'conditions' => 'company_id = :company_id:',
                'bind' => [
                    'company_id' => $company->getId()
                ],
                "order" => "created_at DESC",
            ]);
            if (count($ssoIdpConfigs) > 0) {
                $results['data'] = $ssoIdpConfigs;
            }
        }

        end:
        $this->response->setJsonContent($results);
        $this->response->send();
    }


    /**
     *
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $results = ['success' => true, 'data' => []];
        $name = Helpers::__getRequestValue('name');
        $sso_id = Helpers::__getRequestValue('sso_id');
        $sso_url_login = Helpers::__getRequestValue('sso_url_login');
        $sso_certificate = Helpers::__getRequestValue('sso_certificate');
        $is_active = Helpers::__getRequestValue('is_active');
        $type = Helpers::__getRequestValue('type');
        $ssoIdpConfig = new SsoIdpConfig();
        $ssoIdpConfig->setUuid(Helpers::__uuid());
        $ssoIdpConfig->setName($name);
        $ssoIdpConfig->setSsoId($sso_id);
        $ssoIdpConfig->setSsoUrlLogin($sso_url_login);
        $ssoIdpConfig->setSsoCertificate($sso_certificate);
        $ssoIdpConfig->setCompanyId(ModuleModel::$company->getId());
        $ssoIdpConfig->setIsActive($is_active);
        $ssoIdpConfig->setType($type);
        $results = $ssoIdpConfig->__quickCreate();

        $this->response->setJsonContent($results);
        end:
        $this->response->send();
    }


    /**
     *
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $results = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => []];
        $id = Helpers::__getRequestValue('id');
        $name = Helpers::__getRequestValue('name');
        $sso_id = Helpers::__getRequestValue('sso_id');
        $sso_url_login = Helpers::__getRequestValue('sso_url_login');
        $sso_certificate = Helpers::__getRequestValue('sso_certificate');
        $is_active = Helpers::__getRequestValue('is_active');
        $type = Helpers::__getRequestValue('type');
        $ssoIdpConfig = SsoIdpConfig::findFirstById($id);
        if ($ssoIdpConfig){
            $ssoIdpConfig->setName($name);
            $ssoIdpConfig->setSsoId($sso_id);
            $ssoIdpConfig->setSsoUrlLogin($sso_url_login);
            $ssoIdpConfig->setSsoCertificate($sso_certificate);
            $ssoIdpConfig->setIsActive($is_active);
            $ssoIdpConfig->setType($type);
            $results = $ssoIdpConfig->__quickUpdate();
        }
        end:

        $this->response->setJsonContent($results);
        $this->response->send();
    }

    public function saveAction(){
        $this->view->disable();
        $this->checkAjaxPutPost();
        $results = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => []];

        $id = Helpers::__getRequestValue('id');

        $isCreate = false;
        $ssoIdpConfig = SsoIdpConfig::findFirstById($id);

        if (!$ssoIdpConfig){
            $isCreate = true;
            $ssoIdpConfig = new SsoIdpConfig();
            $ssoIdpConfig->setUuid(Helpers::__uuid());
        }


        $name = Helpers::__getRequestValue('name');
        $sso_id = Helpers::__getRequestValue('sso_id');
        $sso_url_login = Helpers::__getRequestValue('sso_url_login');
        $sso_certificate = Helpers::__getRequestValue('sso_certificate');
        $is_active = Helpers::__getRequestValue('is_active');
        $type = Helpers::__getRequestValue('type');

        error_reporting(0);
        $checkCertFormat = openssl_x509_read($sso_certificate);
        if (!$checkCertFormat){
            $sso_certificate = SamlHelper::__toPem($sso_certificate);
        }

        $ssoIdpConfig->setName($name);
        $ssoIdpConfig->setSsoId($sso_id);
        $ssoIdpConfig->setSsoUrlLogin($sso_url_login);
        $ssoIdpConfig->setSsoCertificate($sso_certificate);
        $ssoIdpConfig->setCompanyId(ModuleModel::$company->getId());
        $ssoIdpConfig->setIsActive($is_active);
        $ssoIdpConfig->setType($type);

        if ($isCreate){
            $results = $ssoIdpConfig->__quickCreate();
        }else{
            $results = $ssoIdpConfig->__quickUpdate();
        }


        $this->response->setJsonContent($results);
        $this->response->send();
    }


    public function parseXmlToDataAction(){
        $this->view->disable();

        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }

        $company = ModuleModel::$company;
        $files = $this->request->getUploadedFiles();
        $file = $files[0];

        $file_info = new \stdClass();
        $file_info->extension = strtolower($file->getExtension());
        $file_info->type = $file->getType();
        $file_info->key = $file->getKey();
        $file_info->real_type = $file->getRealType();

        /** CHECK FILE TYPE IMAGE */

        if($file_info->extension != SsoIdpConfig::xmlExtension){
            $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
            goto end_upload_function;
        }

        try{
            error_reporting(0);
            $data = SamlHelper::__loadMetadata(file_get_contents($file->getTempName()));

            $returnDataIdp = [];
            $returnDataSp = [];
            if ($data){
                // Get IDP SSO info
                $idpSso = $data->getFirstIdpSsoDescriptor();
                //Sso id is entity id
                $returnDataIdp['sso_id'] = $data->getEntityID();
                // URL of IDP SSO
                $returnDataIdp['sso_url_login'] = method_exists($idpSso, 'getFirstSingleSignOnService') ? $idpSso->getFirstSingleSignOnService()->getLocation() : null;
                // Certificate of Key Descriptor
                if ($idpSso && $idpSso->getAllKeyDescriptors()){
                    $sign = $idpSso->getAllKeyDescriptorsByUse('signing');
                    if (is_array($sign) && count($sign) > 0){
                        $ssoCertificate = $sign[0]->getCertificate()->getData();
                    }else{
                        $ssoCertificate = method_exists($idpSso, 'getFirstKeyDescriptor') ? $idpSso->getFirstKeyDescriptor()->getCertificate()->getData() : null;
                    }
                }

                $returnDataIdp['sso_certificate'] = isset($ssoCertificate) ? SamlHelper::__toPem($ssoCertificate) : null;

                // Get SP SSO info
                $spSso = $data->getFirstSpSsoDescriptor();
                $returnDataSp['sso_id'] = $data->getEntityID();
                $returnDataSp['sso_url_login'] = method_exists($spSso, 'getFirstSingleSignOnService') ? $spSso->getFirstSingleSignOnService()->getLocation() : null;

                if ($spSso && $spSso->getAllKeyDescriptors()){
                    $signSp = $spSso->getAllKeyDescriptorsByUse('signing');
                    if (is_array($signSp) && count($signSp) > 0){
                        $spSsoCertificate = $signSp[0]->getCertificate()->getData();
                    }else{
                        $spSsoCertificate = method_exists($spSso, 'getFirstKeyDescriptor') ? $spSso->getFirstKeyDescriptor()->getCertificate()->getData() : null;
                    }
                }

                $returnDataSp['sso_certificate'] = isset($spSsoCertificate) ? SamlHelper::__toPem($spSsoCertificate) : null;
            }


            $return['success'] = true;
            $return['data_idp'] = $returnDataIdp;
            $return['data_sp'] = $returnDataSp;
        }catch(\Exception $e){
            $return['success'] = false;
            $return['message'] = "METADATA_NOT_FOUND_TEXT";
            goto end_upload_function;
        }


        end_upload_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    public function parseUrlToDataAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $company = ModuleModel::$company;
        $url = Helpers::__getRequestValue('url');

        /** CHECK FILE TYPE IMAGE */
        if (!$url || !Helpers::__isUrl($url)){
            $return = ['success' => false, 'message' => 'URL_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }


        try{
            $url = filter_var($url, FILTER_SANITIZE_URL);
            error_reporting(0);
            $content = Helpers::__urlGetContents($url);
            if ($content == false){
                $return['success'] = false;
                $return['message'] = "METADATA_NOT_FOUND_TEXT";
                $return['detail'] = $content;
                goto end_upload_function;
            }

            if (!SamlHelper::__isXmlStr($content)){
                $return['success'] = false;
                $return['message'] = "METADATA_NOT_FOUND_TEXT";
                $return['detail'] = "Url is not xml string";
                goto end_upload_function;
            }

            $data = SamlHelper::__loadMetadata($content);
            $returnDataIdp = [];
            $returnDataSp = [];
            if ($data){
                // Get IDP SSO info
                $idpSso = $data->getFirstIdpSsoDescriptor();

                //Sso id is entity id
                $returnDataIdp['sso_id'] = $data->getEntityID();
                // URL of IDP SSO
                $returnDataIdp['sso_url_login'] = method_exists($idpSso, 'getFirstSingleSignOnService') ? $idpSso->getFirstSingleSignOnService()->getLocation() : null;
                // Certificate of Key Descriptor
                if ($idpSso && $idpSso->getAllKeyDescriptors()){
                    $sign = $idpSso->getAllKeyDescriptorsByUse('signing');
                    if (is_array($sign) && count($sign) > 0){
                        $ssoCertificate = $sign[0]->getCertificate()->getData();
                    }else{
                        $ssoCertificate = method_exists($idpSso, 'getFirstKeyDescriptor') ? $idpSso->getFirstKeyDescriptor()->getCertificate()->getData() : null;
                    }
                }

                $returnDataIdp['sso_certificate'] = isset($ssoCertificate) ? SamlHelper::__toPem($ssoCertificate) : null;

                // Get SP SSO info
                $spSso = $data->getFirstSpSsoDescriptor();
                $returnDataSp['sso_id'] = $data->getEntityID();
                $returnDataSp['sso_url_login'] = method_exists($spSso, 'getFirstSingleSignOnService') ? $spSso->getFirstSingleSignOnService()->getLocation() : null;
                if ($spSso && $spSso->getAllKeyDescriptors()){
                    $signSp = $spSso->getAllKeyDescriptorsByUse('signing');
                    if (is_array($signSp) && count($signSp) > 0){
                        $spSsoCertificate = $signSp[0]->getCertificate()->getData();
                    }else{
                        $spSsoCertificate = method_exists($spSso, 'getFirstKeyDescriptor') ? $spSso->getFirstKeyDescriptor()->getCertificate()->getData() : null;
                    }
                }

                $returnDataSp['sso_certificate'] = isset($spSsoCertificate) ? SamlHelper::__toPem($spSsoCertificate) : null;
            }


            $return['success'] = true;
            $return['data_idp'] = $returnDataIdp;
            $return['data_sp'] = $returnDataSp;
        }catch (\Exception $e){
            \Sentry\captureException($e);
            $return['success'] = false;
            $return['message'] = "METADATA_NOT_FOUND_TEXT";
            goto end_upload_function;
        }


        end_upload_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    public function generateSpMetadataAction(){
//        $this->view->disable();
//        $this->checkAjaxGet();

        $spSsoDescriptor = SamlHelper::__generateSpMetadata([
            'entityId' => ModuleModel::$company->getFrontendUrl()
        ]);

        $metadata = SamlHelper::__serializeMetadataToXml($spSsoDescriptor);

        $this->response->resetHeaders();
        $this->response->setHeader('Content-Type', 'text/xml');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="relotalent-metadata.xml"');
        $this->response->setContent($metadata);
        return $this->response->send();
    }


    public function generateAuthnRequestAction(){
//        $this->view->disable();
//        $this->checkAjaxGet();

        $authnRequest = SamlHelper::__generateAuthnRequest([
            'sp_api_response' => RelodayUrlHelper::PROTOCOL_HTTPS . "://" . getenv('API_DOMAIN') . SamlHelper::POSTFIX_API_CALLBACK
        ]);

        $request = SamlHelper::__serializeAuthnRequestToXml($authnRequest);

        $this->response->resetHeaders();
        $this->response->setHeader('Content-Type', 'text/xml');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="saml-request.xml"');
        $this->response->setContent($request);
        return $this->response->send();
    }
}