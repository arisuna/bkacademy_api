<?php

namespace Reloday\App\Controllers\API;

use Phalcon\Security\Random;
use Reloday\App\Controllers\ModuleApiController;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ChargeBeeHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\AddonExt;
use Reloday\Application\Models\AddonModuleExt;
use Reloday\Application\Models\AppExt;
use Reloday\Application\Models\ChargebeeCustomerExt;
use Reloday\Application\Models\CompanyExt;
use Reloday\Application\Models\CompanyTypeExt;
use Reloday\Application\Models\ModuleAclExt;
use Reloday\Application\Models\ModuleExt;
use Reloday\Application\Models\ModuleLimitExt;
use Reloday\Application\Models\Plan;
use Reloday\Application\Models\PlanExt;
use Reloday\Application\Models\SubscriptionAclExt;
use Reloday\Application\Models\SubscriptionAddonExt;
use Reloday\Application\Models\SubscriptionExt;
use Reloday\Application\Models\SubscriptionLockObjectExt;
use Reloday\Gms\Models\CompanyType;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SubscriptionController extends ModuleApiController
{

    /**
     * @Route("/subscription", paths={module="app"}, methods={"POST"}, name="index")
     */
    public function indexAction()
    {
        $this->view->disable();

        $id = Helpers::__getRequestValue("id");

        $chargebeeEvent = Helpers::__getRequestValuesArray();
        $subscription = null;
        $customer = null;
        $event_type = $chargebeeEvent["event_type"];
        $random = new Random();
        switch ($event_type) {
            case "subscription_trial_end_reminder":

            case "subscription_activated":

            case "subscription_changed":

            case "subscription_cancellation_scheduled":

            case "subscription_cancellation_reminder":

            case "subscription_cancelled":

            case "subscription_reactivated":

            case "subscription_renewed":

            case "subscription_scheduled_cancellation_removed":

            case "subscription_changes_scheduled":

            case "subscription_scheduled_changes_removed":

            case "subscription_created":

            case "subscription_paused":

            case "subscription_scheduled_pause_removed":

            case "subscription_resumed":

            case "subscription_resumption_scheduled":

            case "subscription_scheduled_resumption_removed":
                $customer = $chargebeeEvent["content"]["customer"];
                $customerId = $customer["id"];
                $checkLockQueue = new RelodayQueue(getenv('QUEUE_CHECK_SUBSCRIPTION'));

                $addQueue = $checkLockQueue->addQueue([
                    'action' => "checkSubscription",
                    'event_type' => $event_type,
                    'customer_id' => $customerId,
                ]);

                $return = [
                    "success" => true,
                    "addQueue" => $addQueue,
                ];
                goto end_of_function;
                break;

            case "subscription_deleted":
                $customer = $chargebeeEvent["content"]["customer"];
                $customerId = $customer["id"];
                $subscriptionid = $chargebeeEvent["content"]["subscription"]["id"];
                $checkLockQueue = new RelodayQueue(getenv('QUEUE_CHECK_SUBSCRIPTION'));

                $addQueue = $checkLockQueue->addQueue([
                    'action' => "checkSubscription",
                    'event_type' => $event_type,
                    'customer_id' => $customerId,
                    'subscription_id' => $subscriptionid
                ]);

                $return = [
                    "success" => true,
                    "addQueue" => $addQueue,
                ];
                goto end_of_function;
                break;
            default:
                break;
        }
        $return = [
            "success" => true
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *  get Plans
     */
    public function getHrPlansAction()
    {
        $this->view->disable();
        $plan_id = Helpers::__getRequestValue("plan_id");
        $plans = PlanExt::find([
            "conditions" => "company_type_id = :company_type_id: and price > 0",
            "bind" => [
                "company_type_id" => CompanyTypeExt::TYPE_HR
            ],
            "order" => "price ASC"
        ]);
        $plan_array = [];
        if (count($plans) > 0) {
            foreach ($plans as $plan) {
                if ($plan->getId() != $plan_id) {
                    $item = $plan->toArray();
                    $item["content"] = [];
                    $plan_contents = $plan->getPlanContents();
                    if (count($plan_contents) > 0) {
                        foreach ($plan_contents as $plan_content) {
                            $item["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                            $item["content"][$plan_content->getLanguage()]["features"] = json_decode($plan_content->getFeatures());
                        }
                    }
                    $plan_array[] = $item;
                }
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
     * Get All Actives Plan of CompanyTypeId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAllPlansAction()
    {
        $this->view->disable();
        $app_type = Helpers::__getRequestValue("app_type");
        if ($app_type == CompanyTypeExt::TYPE_GMS) {
            $plans = PlanExt::find([
                "conditions" => "company_type_id = :company_type_id: AND status = :status_active: AND is_legacy = :no: AND is_display = :yes:",
                "bind" => [
                    "status_active" => ModelHelper::YES,
                    "company_type_id" => $app_type,
                    "no" => ModelHelper::NO,
                    "yes" => ModelHelper::YES
                ],
                "order" => "price ASC"
            ]);
        } else {
            $plans = PlanExt::find([
                "conditions" => "company_type_id = :company_type_id: AND status = :status_active:",
                "bind" => [
                    "status_active" => ModelHelper::YES,
                    "company_type_id" => $app_type
                ],
                "order" => "price ASC"
            ]);
        }
        $plan_array = [];
        if (count($plans) > 0) {
            $i = 0;
            foreach ($plans as $plan) {

                $item = $plan->toArray();
                $item['price'] = round($plan->getPrice() / 100, 0);
                $item["content"] = [];
                $plan_contents = $plan->getPlanContents();
                if (count($plan_contents) > 0) {
                    foreach ($plan_contents as $plan_content) {

                        $item["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                        $item["content"][$plan_content->getLanguage()]["features"] = json_decode($plan_content->getFeatures());
                    }
                }
                $item["index"] = $i;
                $plan_array[] = $item;
                $i++;

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
     * Get All Actives Plan of CompanyTypeId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getLoginUrlAction($subscription_id)
    {
        $this->view->disable();
        $chargebeeHelper = new ChargeBeeHelper();
        $chargebee_customer = $chargebeeHelper->getCustomerFromSubscriptionId($subscription_id);
        if (!$chargebee_customer) {
            $return = [
                "success" => false,
                "data" => "customer does not exist on chargebee"
            ];
            goto end_of_function;
        }
        $chargebeeCustomer = ChargebeeCustomerExt::findFirstByChargebeeReferenceId($chargebee_customer->id);
        if (!$chargebeeCustomer instanceof ChargebeeCustomerExt) {
            $return = [
                "success" => false,
                "customer" => $chargebee_customer
            ];
            goto end_of_function;
        }
        $company = $chargebeeCustomer->getCompany();
        if (!$company instanceof CompanyExt) {
            $return = [
                "success" => false,
                "customer" => $chargebeeCustomer
            ];
            goto end_of_function;
        }
        $app = $company->getApp();
        if (!$app instanceof AppExt) {
            $return = [
                "success" => false,
                "company" => $company
            ];
            goto end_of_function;
        }
        $return = [
            "success" => true,
            "url" => $app->getLoginUrl()
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Get All Actives Plan of CompanyTypeId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDefaultGmsPlanAction()
    {
        $this->view->disable();
        $plan = PlanExt::findFirst([
            "conditions" => "company_type_id = :company_type_id: AND status = :status_active: AND is_legacy = :no: AND is_default = :yes:",
            "bind" => [
                "status_active" => ModelHelper::YES,
                "company_type_id" => CompanyTypeExt::TYPE_GMS,
                "no" => ModelHelper::NO,
                "yes" => ModelHelper::YES,
            ],
            "order" => "price ASC"
        ]);
        $planArray = null;
        if ($plan) {
            $planArray = $plan->toArray();
            $planArray['price'] = round($plan->getPrice() / 100, 0);
            $planArray["content"] = [];
            $plan_contents = $plan->getPlanContents();
            if (count($plan_contents) > 0) {
                foreach ($plan_contents as $plan_content) {
                    $planArray["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                    $planArray["content"][$plan_content->getLanguage()]["features"] = json_decode($plan_content->getFeatures());
                }
            }
        }
        $return = [
            "success" => true,
            "data" => $planArray
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Get All Actives Plan of CompanyTypeId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDefaultHrmPlanAction()
    {
        $this->view->disable();
        $plan = PlanExt::findFirst([
            "conditions" => "company_type_id = :company_type_id: AND status = :status_active: AND is_legacy = :no: AND is_default = :yes:",
            "bind" => [
                "status_active" => ModelHelper::YES,
                "company_type_id" => CompanyTypeExt::TYPE_HR,
                "no" => ModelHelper::NO,
                "yes" => ModelHelper::YES,
            ],
            "order" => "price ASC"
        ]);
        $planArray = null;
        if ($plan) {
            $planArray = $plan->toArray();
            $planArray['price'] = round($plan->getPrice() / 100, 0);
            $planArray["content"] = [];
            $plan_contents = $plan->getPlanContents();
            if (count($plan_contents) > 0) {
                foreach ($plan_contents as $plan_content) {
                    $planArray["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                    $planArray["content"][$plan_content->getLanguage()]["features"] = json_decode($plan_content->getFeatures());
                }
            }
        }
        $return = [
            "success" => true,
            "data" => $planArray
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Get All Actives Plan of CompanyTypeId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getPlanDetailAction($codeUID = '')
    {
        $this->view->disable();
        if (Helpers::__isValidUuid($codeUID)) {
            $plan = PlanExt::findFirstByUuid($codeUID);
        } else {
            $plan = PlanExt::findFirstByCode($codeUID);
        }

        $return = [
            "success" => false,
            "message" => "PLAN_NOT_FOUND_TEXT"
        ];

        $planArray = null;
        if ($plan && $plan->isActive()) {
            $planArray = $plan->toArray();
            $planArray['price'] = round($plan->getPrice() / 100, 0);
            $planArray["content"] = [];
            $plan_contents = $plan->getPlanContents();
            if (count($plan_contents) > 0) {
                foreach ($plan_contents as $plan_content) {
                    $planArray["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                    $planArray["content"][$plan_content->getLanguage()]["features"] = json_decode($plan_content->getFeatures());
                }
            }
            $return = [
                "success" => true,
                "data" => $planArray
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}