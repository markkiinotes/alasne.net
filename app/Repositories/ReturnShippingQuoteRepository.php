<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ReturnShippingQuoteRepository
{
    public function __construct(private PDO $db) {}

    public function replaceForReturn(int $returnId, int $storeId, string $provider, string $shipmentId, array $rates): void
    {
        $this->db->prepare("DELETE FROM return_shipping_quotes WHERE return_id = :return_id AND purchased_at IS NULL AND (purchase_status IN ('available','failed') OR (purchase_status='purchasing' AND purchase_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)))")
            ->execute(['return_id'=>$returnId]);
        $stmt=$this->db->prepare("
            INSERT INTO return_shipping_quotes (
                return_id,store_id,provider,provider_shipment_id,provider_rate_id,carrier,service,
                rate,currency,delivery_days,delivery_date,delivery_date_guaranteed,expires_at,created_at
            ) VALUES (
                :return_id,:store_id,:provider,:shipment_id,:rate_id,:carrier,:service,:rate,:currency,
                :delivery_days,:delivery_date,:guaranteed,DATE_ADD(NOW(), INTERVAL 30 MINUTE),NOW()
            )
        ");
        foreach($rates as $rate){
            $stmt->execute(['return_id'=>$returnId,'store_id'=>$storeId,'provider'=>$provider,
                'shipment_id'=>$shipmentId,'rate_id'=>$rate['id'],'carrier'=>$rate['carrier'],
                'service'=>$rate['service'],'rate'=>number_format((float)$rate['rate'],2,'.',''),
                'currency'=>strtoupper($rate['currency']??'USD'),'delivery_days'=>$rate['delivery_days']??null,
                'delivery_date'=>$rate['delivery_date']??null,'guaranteed'=>!empty($rate['delivery_date_guaranteed'])?1:0]);
        }
    }

    public function availableForReturn(int $returnId): array
    {
        $stmt=$this->db->prepare("SELECT * FROM return_shipping_quotes WHERE return_id=:return_id AND purchased_at IS NULL AND (purchase_status IN ('available','failed') OR (purchase_status='purchasing' AND purchase_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))) AND expires_at > NOW() ORDER BY rate ASC, carrier ASC, service ASC");
        $stmt->execute(['return_id'=>$returnId]); return $stmt->fetchAll();
    }

    public function lock(int $quoteId, int $returnId): ?array
    {
        $stmt=$this->db->prepare("SELECT * FROM return_shipping_quotes WHERE id=:id AND return_id=:return_id LIMIT 1");
        $stmt->execute(['id'=>$quoteId,'return_id'=>$returnId]); $row=$stmt->fetch(); return $row?:null;
    }

    public function claim(int $quoteId, int $returnId): bool
    {
        $stmt=$this->db->prepare("UPDATE return_shipping_quotes SET purchase_status='purchasing',purchase_started_at=NOW(),purchase_error=NULL WHERE id=:id AND return_id=:return_id AND expires_at > NOW() AND purchased_at IS NULL AND (purchase_status IN ('available','failed') OR (purchase_status='purchasing' AND purchase_started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)))");
        $stmt->execute(['id'=>$quoteId,'return_id'=>$returnId]);
        return $stmt->rowCount() === 1;
    }

    public function markPurchased(int $quoteId): void
    {
        $this->db->prepare("UPDATE return_shipping_quotes SET purchase_status='purchased',purchased_at=NOW(),purchase_error=NULL WHERE id=:id")
            ->execute(['id'=>$quoteId]);
    }

    public function markFailed(int $quoteId, string $message): void
    {
        $this->db->prepare("UPDATE return_shipping_quotes SET purchase_status='failed',purchase_error=:message WHERE id=:id")
            ->execute(['id'=>$quoteId,'message'=>mb_substr($message,0,1000)]);
    }
}
