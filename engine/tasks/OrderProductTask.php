<?php

use Dotenv\Dotenv;

class OrderProductTask extends \Phalcon\Cli\Task
{
    use TaskTrait;

    public function migrateOrderProductAction()
    {
        echo date("Y-m-d H:i:s") . " - " . "[BEGIN] Migrate order product" .  ".... \r\n";
        $businessOrders = \SMXD\Application\Models\BusinessOrderExt::find();

        $this->db->begin();
        echo date("Y-m-d H:i:s") . " - " . "[COUNT] order record: " . count($businessOrders) .  ".... \r\n";
        foreach ($businessOrders as $businessOrder){

            echo date("Y-m-d H:i:s") . " - " . "[START] Business Order " . $businessOrder->getUuid() .   ".... \r\n";

            $businessOrderProduct = \SMXD\Application\Models\BusinessOrderProductExt::findFirst([
                'conditions' => 'product_id = :product_id: and business_order_uuid = :business_order_uuid:',
                'bind' => [
                    'product_id' => $businessOrder->getProductId(),
                    'business_order_uuid' => $businessOrder->getUuid()
                ]
            ]);

            if(!$businessOrderProduct){
                $businessOrderProduct = new \SMXD\Application\Models\BusinessOrderProductExt();
                $businessOrderProduct->setUuid(\SMXD\Application\Lib\Helpers::__uuid());
                $businessOrderProduct->setQuantity($businessOrder->getQuantity());
                $businessOrderProduct->setProductId($businessOrder->getProductId());
                $businessOrderProduct->setProductSaleInfoId($businessOrder->getProductSaleInfoId());
                $businessOrderProduct->setProductRentInfoId($businessOrder->getProductRentInfoId());
                $businessOrderProduct->setProductAuctionInfoId($businessOrder->getProductAuctionInfoId());
                $businessOrderProduct->setBusinessOrderUuid($businessOrder->getUuid());

                $result = $businessOrderProduct->__quickCreate();

                if(!$result['success']){
                    $this->db->rollback();
                    echo date("Y-m-d H:i:s") . " - " . "[FAILED] Business Order " . $businessOrder->getUuid() .   ".... \r\n";
                    goto end;
                }

                echo date("Y-m-d H:i:s") . " - " . "[SUCCESS] Business Order " . $businessOrder->getUuid() .   ".... \r\n";

            }else{
                echo date("Y-m-d H:i:s") . " - " . "[INFO] Business Order product existed" . $businessOrderProduct->getUuid() .   ".... \r\n";
            }
        }

        $this->db->commit();

        end:
        echo date("Y-m-d H:i:s") . " - " . "[END] Migrate order product" .   ".... \r\n";
        return true;
    }

}