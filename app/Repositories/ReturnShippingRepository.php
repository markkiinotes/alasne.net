<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ReturnShippingRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function findByReturn(
        int $returnId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM return_shipments
            WHERE return_id = :return_id
            LIMIT 1
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        $shipment = $stmt->fetch();

        return $shipment ?: null;
    }

    public function lockByReturn(
        int $returnId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM return_shipments
            WHERE return_id = :return_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        $shipment = $stmt->fetch();

        return $shipment ?: null;
    }

    public function publicForReturn(
        int $returnId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                return_id,
                rma_number,
                status,
                carrier_code,
                carrier_name,
                service_name,
                tracking_number,
                tracking_url,
                label_source,
                provider,
                provider_mode,
                provider_shipment_id,
                provider_tracker_id,
                provider_label_url,
                provider_label_pdf_url,
                provider_label_zpl_url,
                purchased_at,
                from_name,
                from_address_snapshot,
                to_name,
                to_address_snapshot,
                public_note,
                shipped_at,
                delivered_at,
                last_event_at,
                created_at,
                updated_at
            FROM return_shipments
            WHERE return_id = :return_id
            LIMIT 1
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        $shipment = $stmt->fetch();

        return $shipment ?: null;
    }

    public function events(
        int $shipmentId,
        bool $publicOnly = false
    ): array {
        $sql = "
            SELECT *
            FROM return_shipment_events
            WHERE return_shipment_id = :shipment_id
        ";

        if ($publicOnly) {
            $sql .= ' AND is_public = 1';
        }

        $sql .= ' ORDER BY event_at ASC, id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'shipment_id' => $shipmentId,
        ]);

        return $stmt->fetchAll();
    }

    public function save(
        int $returnId,
        array $data
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO return_shipments (
                return_id,
                store_id,
                order_id,
                rma_number,
                status,
                carrier_code,
                carrier_name,
                service_name,
                tracking_number,
                tracking_url,
                label_source,
                label_cost,
                currency,
                from_name,
                from_address_snapshot,
                to_name,
                to_address_snapshot,
                public_note,
                last_event_at,
                created_at,
                updated_at
            ) VALUES (
                :return_id,
                :store_id,
                :order_id,
                :rma_number,
                :status,
                :carrier_code,
                :carrier_name,
                :service_name,
                :tracking_number,
                :tracking_url,
                'manual',
                :label_cost,
                :currency,
                :from_name,
                :from_address_snapshot,
                :to_name,
                :to_address_snapshot,
                :public_note,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                carrier_code = VALUES(carrier_code),
                carrier_name = VALUES(carrier_name),
                service_name = VALUES(service_name),
                tracking_number = VALUES(tracking_number),
                tracking_url = VALUES(tracking_url),
                label_cost = VALUES(label_cost),
                currency = VALUES(currency),
                from_name = VALUES(from_name),
                from_address_snapshot =
                    VALUES(from_address_snapshot),
                to_name = VALUES(to_name),
                to_address_snapshot =
                    VALUES(to_address_snapshot),
                public_note = VALUES(public_note),
                updated_at = NOW()
        ");

        $stmt->execute([
            'return_id' => $returnId,
            'store_id' => (int) $data['store_id'],
            'order_id' => (int) $data['order_id'],
            'rma_number' => $data['rma_number'],
            'status' => $data['status'],
            'carrier_code' => $data['carrier_code'],
            'carrier_name' => $data['carrier_name'],
            'service_name' =>
                $this->nullable($data['service_name'] ?? null),
            'tracking_number' => $data['tracking_number'],
            'tracking_url' =>
                $this->nullable($data['tracking_url'] ?? null),
            'label_cost' => $this->money(
                $data['label_cost'] ?? 0
            ),
            'currency' => strtoupper(
                (string) ($data['currency'] ?? 'USD')
            ),
            'from_name' =>
                $this->nullable($data['from_name'] ?? null),
            'from_address_snapshot' =>
                $this->nullable(
                    $data['from_address_snapshot'] ?? null
                ),
            'to_name' =>
                $this->nullable($data['to_name'] ?? null),
            'to_address_snapshot' =>
                $data['to_address_snapshot'],
            'public_note' =>
                $this->nullable($data['public_note'] ?? null),
        ]);

        $shipment = $this->findByReturn($returnId);

        return (int) ($shipment['id'] ?? 0);
    }


    public function saveProviderPurchase(int $returnId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO return_shipments (
                return_id,store_id,order_id,rma_number,status,carrier_code,carrier_name,service_name,
                tracking_number,tracking_url,label_source,provider,provider_mode,provider_shipment_id,
                provider_rate_id,provider_tracker_id,provider_label_url,provider_label_pdf_url,
                provider_label_zpl_url,provider_payload,purchased_at,label_cost,currency,from_name,
                from_address_snapshot,to_name,to_address_snapshot,public_note,last_event_at,created_at,updated_at
            ) VALUES (
                :return_id,:store_id,:order_id,:rma_number,'label_ready',:carrier_code,:carrier_name,:service_name,
                :tracking_number,:tracking_url,'provider',:provider,:provider_mode,:provider_shipment_id,
                :provider_rate_id,:provider_tracker_id,:provider_label_url,:provider_label_pdf_url,
                :provider_label_zpl_url,:provider_payload,NOW(),:label_cost,:currency,:from_name,
                :from_address_snapshot,:to_name,:to_address_snapshot,:public_note,NOW(),NOW(),NOW()
            ) ON DUPLICATE KEY UPDATE
                status='label_ready',carrier_code=VALUES(carrier_code),carrier_name=VALUES(carrier_name),
                service_name=VALUES(service_name),tracking_number=VALUES(tracking_number),tracking_url=VALUES(tracking_url),
                label_source='provider',provider=VALUES(provider),provider_mode=VALUES(provider_mode),
                provider_shipment_id=VALUES(provider_shipment_id),provider_rate_id=VALUES(provider_rate_id),
                provider_tracker_id=VALUES(provider_tracker_id),provider_label_url=VALUES(provider_label_url),
                provider_label_pdf_url=VALUES(provider_label_pdf_url),provider_label_zpl_url=VALUES(provider_label_zpl_url),
                provider_payload=VALUES(provider_payload),purchased_at=NOW(),label_cost=VALUES(label_cost),
                currency=VALUES(currency),from_name=VALUES(from_name),from_address_snapshot=VALUES(from_address_snapshot),
                to_name=VALUES(to_name),to_address_snapshot=VALUES(to_address_snapshot),public_note=VALUES(public_note),
                last_event_at=NOW(),updated_at=NOW()
        ");
        $stmt->execute([
            'return_id'=>$returnId,'store_id'=>$data['store_id'],'order_id'=>$data['order_id'],'rma_number'=>$data['rma_number'],
            'carrier_code'=>strtolower($data['carrier_name']),'carrier_name'=>$data['carrier_name'],'service_name'=>$data['service_name'],
            'tracking_number'=>$data['tracking_number'],'tracking_url'=>$this->nullable($data['tracking_url']??null),
            'provider'=>$data['provider'],'provider_mode'=>$data['provider_mode'],'provider_shipment_id'=>$data['provider_shipment_id'],
            'provider_rate_id'=>$data['provider_rate_id'],'provider_tracker_id'=>$this->nullable($data['provider_tracker_id']??null),
            'provider_label_url'=>$this->nullable($data['provider_label_url']??null),'provider_label_pdf_url'=>$this->nullable($data['provider_label_pdf_url']??null),
            'provider_label_zpl_url'=>$this->nullable($data['provider_label_zpl_url']??null),'provider_payload'=>$data['provider_payload'],
            'label_cost'=>$this->money($data['label_cost']),'currency'=>$data['currency'],'from_name'=>$this->nullable($data['from_name']??null),
            'from_address_snapshot'=>$this->nullable($data['from_address_snapshot']??null),'to_name'=>$this->nullable($data['to_name']??null),
            'to_address_snapshot'=>$data['to_address_snapshot'],'public_note'=>$this->nullable($data['public_note']??null),
        ]);
        $row=$this->findByReturn($returnId); return (int)($row['id']??0);
    }

    public function findByProviderTracker(string $provider, string $trackerId, string $trackingNumber): ?array
    {
        $stmt=$this->db->prepare("SELECT * FROM return_shipments WHERE provider=:provider AND (provider_tracker_id=:tracker_id OR tracking_number=:tracking_number) LIMIT 1");
        $stmt->execute(['provider'=>$provider,'tracker_id'=>$trackerId,'tracking_number'=>$trackingNumber]);
        $row=$stmt->fetch(); return $row?:null;
    }

    public function updateStatus(
        int $shipmentId,
        string $status,
        string $eventAt
    ): void {
        $stmt = $this->db->prepare("
            UPDATE return_shipments
            SET
                status = :status_value,
                shipped_at = CASE
                    WHEN :status_for_shipped = 'in_transit'
                    AND shipped_at IS NULL
                    THEN :shipped_event_at
                    ELSE shipped_at
                END,
                delivered_at = CASE
                    WHEN :status_for_delivered = 'delivered'
                    THEN :delivered_event_at
                    ELSE delivered_at
                END,
                last_event_at = :last_event_at,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $shipmentId,
            'status_value' => $status,
            'status_for_shipped' => $status,
            'shipped_event_at' => $eventAt,
            'status_for_delivered' => $status,
            'delivered_event_at' => $eventAt,
            'last_event_at' => $eventAt,
        ]);
    }

    public function recordEvent(
        int $shipmentId,
        string $status,
        string $title,
        ?string $description,
        ?string $location,
        string $eventAt,
        bool $isPublic = true
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO return_shipment_events (
                return_shipment_id,
                status,
                title,
                description,
                location,
                event_at,
                is_public,
                created_at
            ) VALUES (
                :return_shipment_id,
                :status,
                :title,
                :description,
                :location,
                :event_at,
                :is_public,
                NOW()
            )
        ");

        $stmt->execute([
            'return_shipment_id' => $shipmentId,
            'status' => $status,
            'title' => $title,
            'description' =>
                $this->nullable($description),
            'location' => $this->nullable($location),
            'event_at' => $eventAt,
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    private function money(mixed $value): string
    {
        return number_format(
            round(max(0, (float) $value), 2),
            2,
            '.',
            ''
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
