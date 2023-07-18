<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use phpDocumentor\Reflection\Types\This;
use Reloday\Application\Models\MediaAttachmentExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Addon extends \Reloday\Application\Models\AddonExt
{

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

    const PAID = 1;
    const UN_PAID = 0;

    const LIMIT_PER_PAGE = 20;

	public function initialize(){
		parent::initialize();
	}


    public static function __findWithFilters(array $options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\SubscriptionAddon', 'SubscriptionAddon');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Subscription', 'SubscriptionAddon.subscription_id = Subscription.id', 'Subscription');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Addon', 'SubscriptionAddon.addon_id = Addon.id', 'Addon');

        $queryBuilder->where('Addon.id > 0');
        $queryBuilder->andWhere('Addon.is_gms = 1 ');
        $queryBuilder->andWhere('Subscription.status = :status:', [
            'status' => self::STATUS_ACTIVE
        ]);

        $queryBuilder->andWhere('Subscription.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andWhere('Subscription.is_paid = :is_paid:', [
            'is_paid' => self::PAID
        ]);

        $queryBuilder->groupBy('SubscriptionAddon.id');

        try{

            $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 && $options['limit'] <= self::LIMIT_PER_PAGE ? $options['limit'] : self::LIMIT_PER_PAGE;
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
            if ($page == 0) $page = intval($start / $limit) + 1;

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $addonArray = [];
            if ($pagination->items->count() > 0) {
                $i = 0;
                foreach ($pagination->items as $addon) {
                    $addonArray[$i] = $addon->toArray();

                    $addonContent = AddonContent::find([
                        'conditions' => 'addon_id = :addon_id: AND language = :language: ',
                        'bind' => [
                            'addon_id' => $addon->getAddonId(),
                            'language' => isset($options['language']) ? $options['language'] : 'en'
                        ],
                    ]);

                    $addonArray[$i]['addon_content'] = $addonContent ? $addonContent->toArray() : [];
                    $addonModule = Addon::findFirstById($addon->getAddonId());

                    if($addonModule && !empty($addonArray[$i]['addon_content']) ){
                        $addonArray[$i]['addon_content'][0]['is_public_api_addon'] = self::isPublicApiAddon($addon->getAddonId()) ? 1 : 0;
                        $addonArray[$i]['addon_content'][0]['image'] = self::getAddonImage($addonModule->getUuid());
                    }

                    $i++;
                }
            }

            return [
                'data' => array_values($addonArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'success' => true
            ];

        }catch (\Phalcon\Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            \Sentry\captureException($e);
            return ['susccess' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }



    public static function isPublicApiAddon($addonId = 0){
        if($addonId > 0){
            $addon = Addon::findFirstById($addonId);
            if($addon && $addon->getCode() == self::CODE_PUBLIC_API){
                return true;
            }
        }
        return false;
    }

    public static function getAddonImage($objectUuid = ''){
        $media = [];
        if($objectUuid != ''){
            $media = MediaAttachment::__getLastImage($objectUuid);
        }
        return $media;
    }


}
