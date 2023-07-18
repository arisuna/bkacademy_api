<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Mvc\Controller\Base;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\FaqCategory;
use Reloday\Gms\Models\FaqContent;
use Reloday\Gms\Models\FaqReview;
use Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/hr/api")
 */
class FaqController extends BaseController
{
    /**
     * @Route("/faq", paths={module="gms"}, methods={"GET"}, name="gms-faq-index")
     */
    public function indexAction()
    {
        $this->view->disable();

        $language = ModuleModel::$language;

        $categories = FaqCategory::find("language = '" . $language . "' AND environment = 'gms'");

        $data = [];

        foreach ($categories as $category) {
            $item = new \stdClass;
            $item->info = $category;
            $faqContents = $category->getFaqContents([
                'conditions' => 'status = :status_active:',
                'bind' => [
                    'status_active' => FaqContent::STATUS_ACTIVE
                ]
            ]);
            foreach ($faqContents as $faqContent) {
                $faq = new \stdClass;
                $faq->id = $faqContent->getId();
                $faq->info = $faqContent;
                $reviewUserProfile = $faqContent->getReviewOfUserProfile(ModuleModel::$user_profile->getId());
                if ($reviewUserProfile) {
                    $faq->review = $reviewUserProfile;
                }else{
                    $faq->review = null;
                }
                $item->faqs[] = $faq;
            }

            $data[] = $item;
        }

        $this->response->setJsonContent(['success' => true, 'data' => $data]);
        return $this->response->send();
    }

    /**
     * @param $faq_content_id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function setReviewPositiveAction($faq_content_id)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        if (!$faq_content_id > 0) $faq_content_id = Helpers::__getRequestValue('faq_content_id');

        $faqContent = FaqContent::findFirstById($faq_content_id);
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($faqContent) {
            $resultUpdate = $faqContent->addNewReview(ModuleModel::$user_profile->getId(), true);
            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
            } else {
                $return = ['success' => true, 'data' => $faqContent->getLastFaqReviews(ModuleModel::$user_profile->getId())];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $faq_content_id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function setReviewNegativeAction($faq_content_id)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        if (!$faq_content_id > 0) $faq_content_id = Helpers::__getRequestValue('faq_content_id');

        $faqContent = FaqContent::findFirstById($faq_content_id);
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($faqContent) {
            $resultUpdate = $faqContent->addNewReview(ModuleModel::$user_profile->getId(), false);
            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
            } else {
                $return = ['success' => true, 'data' => $faqContent->getLastFaqReviews(ModuleModel::$user_profile->getId())];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
