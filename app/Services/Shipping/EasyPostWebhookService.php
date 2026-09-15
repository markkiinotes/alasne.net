<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Repositories\CarrierWebhookRepository;
use App\Repositories\ReturnRepository;
use App\Repositories\ReturnShippingRepository;
use App\Services\Returns\ReturnShippingNotificationService;
use App\Services\Mail\EmailOutboxSender;
use PDO;
use RuntimeException;

class EasyPostWebhookService
{
    public function __construct(
        private PDO $db,
        private CarrierWebhookRepository $webhooks,
        private ReturnShippingRepository $shipments,
        private ReturnRepository $returns,
        private ReturnShippingNotificationService $notifications,
        private EmailOutboxSender $emailSender
    ) {}

    public function process(int $storeId, array $event, string $raw): string
    {
        $eventId=trim((string)($event['id']??'')); $type=trim((string)($event['description']??''));
        $result=is_array($event['result']??null)?$event['result']:[]; $objectId=$result['id']??null;
        if($eventId===''||$type==='') throw new RuntimeException('EasyPost webhook is missing its event identity.');
        if(!$this->webhooks->begin($storeId,'easypost',$eventId,$type,$objectId,$raw)) return 'duplicate';
        try {
            if($type!=='tracker.updated'){ $this->webhooks->finish('easypost',$eventId,'ignored','Event type is not tracker.updated.'); return 'ignored'; }
            $trackerId=(string)($result['id']??''); $tracking=(string)($result['tracking_code']??'');
            $shipment=$this->shipments->findByProviderTracker('easypost',$trackerId,$tracking);
            if(!$shipment||(int)$shipment['store_id']!==$storeId){ $this->webhooks->finish('easypost',$eventId,'ignored','No matching Alasne return shipment.'); return 'unmatched'; }
            $mapped=$this->mapStatus((string)($result['status']??'unknown'));
            $detail=$this->latestDetail($result['tracking_details']??[]);
            $eventAt=$detail['datetime']??($event['created_at']??date(DATE_ATOM)); $eventAtSql=date('Y-m-d H:i:s',strtotime((string)$eventAt));
            $description=$detail['message']??('EasyPost tracker status: '.($result['status']??'unknown'));
            $location=$this->location($detail['tracking_location']??[]);
            $this->db->beginTransaction();
            $this->shipments->updateStatus((int)$shipment['id'],$mapped,$eventAtSql);
            $this->shipments->recordEvent((int)$shipment['id'],$mapped,$this->title($mapped),$description,$location,$eventAtSql,true);
            $this->returns->recordEvent((int)$shipment['return_id'],'shipment_'.$mapped,$this->title($mapped),$description,$shipment['status'],$mapped);
            $this->returns->recordOrderEvent((int)$shipment['order_id'],'shipment_'.$mapped,$this->title($mapped),$shipment['rma_number'].': '.$description,$shipment['status'],$mapped,true);
            $this->db->commit();
            $notificationEvent='shipment_'.$mapped;
            if(in_array($notificationEvent,['shipment_in_transit','shipment_delivered','shipment_exception','shipment_cancelled'],true)){
                try { $id=$this->notifications->queueForEvent((int)$shipment['return_id'],$notificationEvent); if($id!==null)$this->emailSender->sendOne($id); } catch(\Throwable) {}
            }
            $this->webhooks->finish('easypost',$eventId,'processed','Shipment status synchronized to '.$mapped.'.');
            return 'processed';
        } catch(\Throwable $e){ if($this->db->inTransaction())$this->db->rollBack(); $this->webhooks->finish('easypost',$eventId,'failed',$e->getMessage()); throw $e; }
    }

    private function mapStatus(string $s): string { return match(strtolower($s)){'pre_transit','unknown'=>'label_ready','in_transit','out_for_delivery'=>'in_transit','delivered'=>'delivered','failure','return_to_sender'=>'exception',default=>'exception'}; }
    private function title(string $s): string { return match($s){'label_ready'=>'Return shipping label ready','in_transit'=>'Return shipment in transit','delivered'=>'Return shipment delivered','cancelled'=>'Return shipment cancelled',default=>'Return shipment exception'}; }
    private function latestDetail(mixed $d): array { if(!is_array($d)||!$d)return []; $last=end($d); return is_array($last)?$last:[]; }
    private function location(mixed $l): ?string { if(!is_array($l))return null; $v=trim(implode(', ',array_filter([$l['city']??null,$l['state']??null,$l['zip']??null,$l['country']??null]))); return $v!==''?$v:null; }
}
