<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\ChargeBeeHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\Acl;
use Reloday\Gms\Models\Addon;
use Reloday\Gms\Models\ChargebeeCustomer;
use Reloday\Gms\Models\GoPremiumTemplate;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Plan;
use Reloday\Gms\Models\Subscription;
use Reloday\Gms\Models\SubscriptionAcl;
use Reloday\Gms\Models\Relocation;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SubscriptionController extends BaseController
{
    /**
     * @Route("/subscription", paths={module="gms"}, methods={"GET"}, name="getPortalUrl")
     */
    public function getPortalUrlAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $chargebeeHelper = new ChargeBeeHelper();
        $chargebeeCustomer = ChargebeeCustomer::findFirstByCompanyId(ModuleModel::$company->getId());
        $portalSession = $chargebeeHelper->createPortalSession($chargebeeCustomer->getChargebeeReferenceId());
        $return  = [
            "success" => true,
            "data" => [
                "id" => $portalSession->id,
                "token" => $portalSession->token,
                "accessUrl" => $portalSession->accessUrl,
                "redirectUrl" => $portalSession->redirectUrl
            ]
        ];
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @Route("/subscription", paths={module="gms"}, methods={"GET"}, name="getPortalUrl")
     */
    public function getGoPremiumPageOldAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $controller = Helpers::__getRequestValue("controller");
        $go_premium_page = GoPremiumTemplate::findFirst([
            "conditions" => "controller = :controller: and is_on_modalbox = 0",
            "bind" => [
                "controller" => $controller,
            ]
        ]);
        if ($go_premium_page instanceof GoPremiumTemplate) {
            $return = [
                "success" => true,
                "data" => $go_premium_page
            ];
        } else {
            $return = [
                "success" => false,
                "data" => "DATA_NOT_FOUND_TEXT"
            ];
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @Route("/subscription", paths={module="gms"}, methods={"GET"}, name="getPortalUrl")
     */
    public function getGoPremiumPageAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $controller = Helpers::__getRequestValue("controller");
        $go_premium_page = GoPremiumTemplate::findFirst([
            "conditions" => "controller = :controller: and is_on_modalbox = 0",
            "bind" => [
                "controller" => $controller,
            ]
        ]);
        if ($go_premium_page instanceof GoPremiumTemplate) {
            $return = [
                "success" => true,
                "data" => $go_premium_page
            ];
        } else {
            $return = [
                "success" => false,
                "data" => "DATA_NOT_FOUND_TEXT"
            ];
        }
        $return['language'] = ModuleModel::$language;;
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @Route("/subscription", paths={module="gms"}, methods={"GET"}, name="getPortalUrl")
     */
    public function getGoPremiumModalAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');


        $features = GoPremiumTemplate::find([
            "conditions" => "is_on_modalbox = 1"
        ]);
        $return = [
            "success" => true,
            "data" => $features
        ];

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function getCheckLockModuleAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $controller = Helpers::__getRequestValue("controller");
        $subscription = Subscription::findFirstByCompany(ModuleModel::$company->getId());
        $indexAcl = Acl::findFirst([
            "conditions" => "is_gms = 1 and action = :action: and status = 1 and controller = :controller:",
            "bind" => [
                "action" => "index",
                "controller" => $controller
            ]
        ]);

        if ($indexAcl instanceof Acl) {
            $subscription_acl = SubscriptionAcl::findFirst([
                "conditions" => "subscription_id =:subscription_id: and acl_id = :acl_id:",
                "bind" => [
                    "subscription_id" => $subscription->getId(),
                    "acl_id" => $indexAcl->getId()
                ]
            ]);
            if (!$subscription_acl instanceof SubscriptionAcl) {
                $return = [
                    "success" => true,
                    "lock" => true
                ];
            } else if ($subscription_acl->getLimit() > 0) {
                $return = [
                    "success" => true,
                    "limit" => $subscription_acl->getLimit()
                ];
            } else {
                $return = [
                    "success" => true,
                    "lock" => false
                ];
            }
        } else {
            $return = [
                "success" => false,
                "data" => "DATA_NOT_FOUND_TEXT"
            ];
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * get Current Plan Info of your company
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCurrentPlanAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $company = ModuleModel::$company;
        $currentSubscription = $company->getSubscription();
        $currentPlan = null;
        if ($currentSubscription) {
            $currentPlan = $currentSubscription->getPlan();
            $currentPlanData = $currentPlan->toArray();
            $currentPlanData["content"] = [];
            $plan_contents = $currentPlan->getPlanContents();
            if (count($plan_contents) > 0) {
                foreach ($plan_contents as $plan_content) {
                    $currentPlanData["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                    $currentPlanData["content"][$plan_content->getLanguage()]["features"] = $plan_content->getDecodeFeatures();
                }
            }
            $currentPlanData['modules'] = $currentPlan->getModules();
            $currentPlanData['active_relocation_count'] = Relocation::countActive();
            $resultAddon = Addon::__findWithFilters(['language' => 'en', 'limit' => 1]);
            $number_addon = 0;
            if($resultAddon['success'] == true){
                $number_addon = $resultAddon['total_items'];
            }
            $currentPlanData['number_addon'] = $number_addon;
        }
        $return = [
            'success' => true,
            'data' => $currentPlanData ? $currentPlanData : null,
            'subscription' => $currentSubscription,
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getPlanListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $plans = Plan::__getList();
        $plan_array = [];
        if (count($plans) > 0) {
            foreach ($plans as $plan) {
                $item = $plan->toArray();
                $item["content"] = [];
                $plan_contents = $plan->getPlanContents();
                if (count($plan_contents) > 0) {
                    foreach ($plan_contents as $plan_content) {
                        $item["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                        $item["content"][$plan_content->getLanguage()]["features"] = $plan_content->getDecodeFeatures();
                    }
                }
                $item['modules'] = $plan->getModules();
                $item['is_current'] = $plan->getId() == ModuleModel::$plan->getId();
                $plan_array[] = $item;
            }
        }
        $return = [
            "success" => true,
            "data" => $plan_array
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCheckoutPageAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();
        $planId = Helpers::__getRequestValue('plan_id');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $plan = Plan::findFirstById($planId);

        $chargebeeHelper = new ChargeBeeHelper();
        $chargebeeCustomer = ChargebeeCustomer::findFirstByCompanyId(ModuleModel::$company->getId());

        if ($chargebeeCustomer && $chargebeeCustomer->getChargebeeReferenceId() != '' && $plan && $plan->getChargebeeReferenceId() != '') {

            $chargebeeSubscription = $chargebeeHelper->getSubscription($chargebeeCustomer->getChargebeeReferenceId());

            if ($chargebeeSubscription) {
                $checkoutHostedPage = $chargebeeHelper->checkoutSubscription($chargebeeSubscription->id, $plan->getChargebeeReferenceId());
                $return = [
                    "success" => true,
                    "data" => [
                        "id" => $checkoutHostedPage->id,
                        "url" => $checkoutHostedPage->url,
                    ]
                ];
            }
        } else {
            $return['$chargebeeCustomer'] = $chargebeeCustomer;
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * get Current Plan Info of your company
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getPlanAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $company = ModuleModel::$company;
        $currentPlan = Plan::findFirstByUuid($uuid);
        $currentPlanData = null;
        if ($currentPlan) {
            $currentPlanData = $currentPlan->toArray();
            $currentPlanData["content"] = [];
            $plan_contents = $currentPlan->getPlanContents();
            if (count($plan_contents) > 0) {
                foreach ($plan_contents as $plan_content) {
                    $currentPlanData["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                    $currentPlanData["content"][$plan_content->getLanguage()]["features"] = $plan_content->getDecodeFeatures();
                }
            }
            $currentPlanData['modules'] = $currentPlan->getModules();
        }
        $return = [
            'success' => true,
            'data' => $currentPlanData ? $currentPlanData : null,
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function reloadCurrentPlanAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $chargebeeHelper = new ChargeBeeHelper();
        $chargebeeCustomer = ChargebeeCustomer::findFirstByCompanyId(ModuleModel::$company->getId());
        if ($chargebeeCustomer && $chargebeeCustomer->getChargebeeReferenceId() != '') {
            $chargebeeSubscription = $chargebeeHelper->getSubscription($chargebeeCustomer->getChargebeeReferenceId());

            if ($chargebeeSubscription && $chargebeeSubscription->planId) {

                //var_dump((array)$chargebeeSubscription); die();

                $plan = Plan::findFirstByChargebeeReferenceId($chargebeeSubscription->planId);
                if ($plan && $chargebeeSubscription->planId) {
                    ModuleModel::$subscription->setPlanId($plan->getId());
                    ModuleModel::$subscription->setNextPaymentDate($chargebeeSubscription->nextBillingAt);
                    ModuleModel::$subscription->setFirstPaymentDate($chargebeeSubscription->startedAt);

                    if ($chargebeeSubscription->trialEnd > time() || $chargebeeSubscription->status == Subscription::STATUS_TRIAL_TEXT) {
                        ModuleModel::$subscription->setIsTrial(ModelHelper::YES);
                    } else {
                        ModuleModel::$subscription->setIsTrial(ModelHelper::NO);
                    }

                    if ($chargebeeSubscription->status == Subscription::STATUS_ACTIVE_TEXT) {
                        ModuleModel::$subscription->setStatus(Subscription::STATUS_ACTIVE);
                        ModuleModel::$subscription->setIsPaid(ModelHelper::YES);
                    }

                    $result = ModuleModel::$subscription->__quickUpdate();

                }
                //Modified our subscription

                $subscriptionChargebee = [
                    'charbeeId' => $chargebeeSubscription->id,
                    'planId' => $chargebeeSubscription->planId,
                    'trialEndTime' => $chargebeeSubscription->trialEnd,
                    'trialEnd' => date('Y-m-d H:i:s', $chargebeeSubscription->trialEnd),
                    'trialStartTime' => $chargebeeSubscription->trialStart,
                    'trialStart' => date('Y-m-d H:i:s', $chargebeeSubscription->trialStart),
                ];
                $return = [
                    "success" => isset($result) ? $result['success'] : false,
                    "raw" => (array)$chargebeeSubscription,
                    "subscription" => ModuleModel::$subscription,
                    "subscriptionChargebee" => $subscriptionChargebee,
                    "data" => $plan->getParsedData(),
                    "active_relocation_count" => Relocation::countActive()
                ];
            }
        } else {
            $return['$chargebeeCustomer'] = $chargebeeCustomer;
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $providerUuid
     */
    public function checkTrialProviderAction( $providerUuid ){
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => true, 'data' => ['isInTrial' => true, 'days' => 26 ]];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
