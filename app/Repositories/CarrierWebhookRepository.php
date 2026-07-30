<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CarrierWebhookRepository
{
    public function __construct(private PDO $db) {}

    public function begin(int $storeId, string $provider, string $eventId, string $eventType, ?string $objectId, string $payload): bool
    {
        try {
            $stmt=$this->db->prepare("INSERT INTO carrier_webhook_events (store_id,provider,provider_event_id,event_type,provider_object_id,processing_status,payload,received_at) VALUES (:store_id,:provider,:event_id,:event_type,:object_id,'received',:payload,NOW())");
            $stmt->execute(['store_id'=>$storeId,'provider'=>$provider,'event_id'=>$eventId,'event_type'=>$eventType,'object_id'=>$objectId,'payload'=>$payload]);
            return true;
        } catch (\PDOException $e) {
            if ((string)$e->getCode()==='23000') return false;
            throw $e;
        }
    }

    public function finish(string $provider, string $eventId, string $status, string $message): void
    {
        $stmt=$this->db->prepare("UPDATE carrier_webhook_events SET processing_status=:status,response_message=:message,processed_at=NOW() WHERE provider=:provider AND provider_event_id=:event_id");
        $stmt->execute(['status'=>$status,'message'=>mb_substr($message,0,1000),'provider'=>$provider,'event_id'=>$eventId]);
    }
}
