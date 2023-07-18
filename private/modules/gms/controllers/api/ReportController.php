<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\HtmlToPDFHelper;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\ModuleModel;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ReportController extends BaseController
{
    /**
     *
     */
    public function docxAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex();

        $data = $this->request->getJsonRawBody();
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_SAVE_HISTORY_TASK_TEXT', 'detail' => $data];
        $html = isset($data->html) && $data->html != '' ? $data->html : null;

        if ($html != '') {

            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            $section = $phpWord->addSection();
            $section->addText(
                "abc xyz"
            );

            $section->addText(
                '"Great achievement is usually born of great sacrifice, '
                . 'and is never the result of selfishness." '
                . '(Napoleon Hill)',
                array('name' => 'Tahoma', 'size' => 10)
            );

            // Saving the document as OOXML file...
            $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save("php://output");
        }

    }

    /**
     *
     */
    public function pdfAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);
        $this->checkAcl('index', $this->router->getControllerName());
        $data = $this->request->getJsonRawBody();
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_SAVE_HISTORY_TASK_TEXT', 'detail' => $data];
        $html = isset($data->html) && $data->html != '' ? $data->html : null;

        if ($html != '') {
            try {
                HtmlToPDFHelper::$tempDir = $this->di->getShared('appConfig')->application->cacheDir;
                return HtmlToPDFHelper::__generatePdfFromHTML($html);
            } catch (MpdfException $e) {
                $this->view->disable();
                $this->response->setJsonContent([
                    'success' => true,
                    'message' => $e->getMessage()
                ]);
                return $this->response->send();
            }
        }
    }
}
