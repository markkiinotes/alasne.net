<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Repositories\CarrierIntegrationRepository;
use App\Repositories\ReturnRepository;
use App\Repositories\ReturnShippingQuoteRepository;
use App\Repositories\ReturnShippingRepository;
use App\Services\Shipping\CarrierProviderFactory;
use PDO;
use RuntimeException;

class ReturnCarrierService
{
    public function __construct(
        private PDO $db,
        private ReturnRepository $returns,
        private ReturnShippingRepository $shipments,
        private ReturnShippingQuoteRepository $quotes,
        private CarrierIntegrationRepository $integrations,
        private CarrierProviderFactory $providers
    ) {}

    public function quote(int $returnId, array $parcelInput): array
    {
        $return=$this->returns->find($returnId);
        if(!$return) throw new RuntimeException('Return not found.');
        if(empty($return['rma_number'])||in_array($return['status'],['requested','cancelled'],true)) throw new RuntimeException('Approve the return before requesting live carrier rates.');
        if($this->shipments->findByReturn($returnId)) throw new RuntimeException('A return shipment already exists. Cancel or remove it before requesting new rates.');
        $config=$this->enabledConfig((int)$return['store_id']);
        $apiKey=$this->requiredEnvironment($config['api_key_env']);
        $provider=$this->providers->make($config['provider'],$apiKey);
        $parcel=$this->parcel($config,$parcelInput);
        $payload=[
            'from_address'=>$this->storeAddress($config),
            'to_address'=>$this->customerAddress($return),
            'parcel'=>$parcel,'is_return'=>true,'reference'=>$return['rma_number'],
            'options'=>['label_format'=>$config['label_format'],'label_size'=>'4x6'],
        ];
        $shipment=$provider->createShipment($payload);
        $responseMode = strtolower(
            (string) ($shipment['mode'] ?? '')
        );
        if (
            $responseMode !== ''
            && $responseMode !== strtolower(
                (string) $config['mode']
            )
        ) {
            throw new RuntimeException(
                'EasyPost API-key mode does not match the store integration mode.'
            );
        }
        $rates=[];
        foreach(($shipment['rates']??[]) as $rate){
            if(!is_array($rate)||empty($rate['id'])||empty($rate['carrier'])||empty($rate['service']))continue;
            $rates[]=['id'=>$rate['id'],'carrier'=>$rate['carrier'],'service'=>$rate['service'],'rate'=>$rate['rate']??0,
                'currency'=>$rate['currency']??'USD','delivery_days'=>$rate['delivery_days']??null,
                'delivery_date'=>!empty($rate['delivery_date'])?date('Y-m-d H:i:s',strtotime($rate['delivery_date'])):null,
                'delivery_date_guaranteed'=>$rate['delivery_date_guaranteed']??false];
        }
        if(!$rates) throw new RuntimeException('EasyPost did not return any purchasable rates.');
        $this->quotes->replaceForReturn($returnId,(int)$return['store_id'],$provider->providerName(),(string)$shipment['id'],$rates);
        return $this->quotes->availableForReturn($returnId);
    }

    public function purchase(int $returnId, int $quoteId): array
    {
        $return=$this->returns->find($returnId);
        if(!$return) throw new RuntimeException('Return not found.');
        if($this->shipments->findByReturn($returnId)) throw new RuntimeException('A return shipment already exists.');
        $quote=$this->quotes->lock($quoteId,$returnId);
        if(!$quote||$quote['purchased_at']!==null||strtotime($quote['expires_at'])<=time()) throw new RuntimeException('The selected carrier rate expired. Request rates again.');
        if(!$this->quotes->claim($quoteId,$returnId)) throw new RuntimeException('This rate is already being purchased. Refresh the return before trying again.');

        try {
            $config=$this->enabledConfig((int)$return['store_id']);
            $provider=$this->providers->make($config['provider'],$this->requiredEnvironment($config['api_key_env']));
            $bought=$provider->retrieveShipment($quote['provider_shipment_id']);
            if(empty($bought['tracking_code'])) {
                $bought=$provider->buyShipment($quote['provider_shipment_id'],$quote['provider_rate_id']);
            }
            $responseMode = strtolower(
                (string) ($bought['mode'] ?? '')
            );
            if (
                $responseMode !== ''
                && $responseMode !== strtolower(
                    (string) $config['mode']
                )
            ) {
                throw new RuntimeException(
                    'EasyPost API-key mode does not match the store integration mode.'
                );
            }
            $tracking=trim((string)($bought['tracking_code']??''));
            if($tracking==='') throw new RuntimeException('EasyPost did not return a tracking code after label purchase.');
            $label=$bought['postage_label']??[]; $tracker=$bought['tracker']??[];
            $trackingUrl=$tracker['public_url']??null;

            $this->db->beginTransaction();
            $lockedReturn=$this->returns->lock($returnId);
            if(!$lockedReturn) throw new RuntimeException('Return no longer exists.');
            if($this->shipments->lockByReturn($returnId)) throw new RuntimeException('A return shipment was created while the label purchase was processing.');
            $shipmentId=$this->shipments->saveProviderPurchase($returnId,[
                'store_id'=>(int)$lockedReturn['store_id'],'order_id'=>(int)$lockedReturn['order_id'],'rma_number'=>$lockedReturn['rma_number'],
                'carrier_name'=>$quote['carrier'],'service_name'=>$quote['service'],'tracking_number'=>$tracking,'tracking_url'=>$trackingUrl,
                'provider'=>$quote['provider'],'provider_mode'=>$config['mode'],'provider_shipment_id'=>$quote['provider_shipment_id'],
                'provider_rate_id'=>$quote['provider_rate_id'],'provider_tracker_id'=>$tracker['id']??null,
                'provider_label_url'=>$label['label_url']??null,'provider_label_pdf_url'=>$label['label_pdf_url']??null,
                'provider_label_zpl_url'=>$label['label_zpl_url']??null,'provider_payload'=>json_encode($bought,JSON_UNESCAPED_SLASHES),
                'label_cost'=>$quote['rate'],'currency'=>$quote['currency'],'from_name'=>$lockedReturn['customer_name']??null,
                'from_address_snapshot'=>$this->customerAddressText($lockedReturn),'to_name'=>$config['return_name']?:($config['return_company']??$lockedReturn['store_name']),
                'to_address_snapshot'=>$this->storeAddressText($config),'public_note'=>'A live carrier label was purchased through EasyPost.',
            ]);
            $this->quotes->markPurchased($quoteId);
            $this->shipments->recordEvent($shipmentId,'label_ready','Live carrier label purchased',
                $quote['carrier'].' '.$quote['service'].' label purchased through EasyPost.',null,date('Y-m-d H:i:s'),true);
            $this->returns->recordEvent($returnId,'shipment_label_ready','Live carrier label purchased',
                $quote['carrier'].' tracking '.$tracking.' was assigned.',null,'label_ready');
            $this->returns->recordOrderEvent((int)$lockedReturn['order_id'],'shipment_label_ready','Return shipping label ready',
                $lockedReturn['rma_number'].' has '.$quote['carrier'].' tracking '.$tracking.'.',null,'label_ready',true);
            $this->db->commit();
            return $this->shipments->findByReturn($returnId)??[];
        } catch(\Throwable $e) {
            if($this->db->inTransaction()) $this->db->rollBack();
            $this->quotes->markFailed($quoteId,$e->getMessage());
            throw $e;
        }
    }

    private function enabledConfig(int $storeId): array
    {
        $c=$this->integrations->forStore($storeId);
        if((int)$c['is_enabled']!==1) throw new RuntimeException('EasyPost is not enabled for this store.');
        return $c;
    }
    private function requiredEnvironment(string $name): string
    {
        $v=$this->integrations->environmentValue($name);
        if($v===null) throw new RuntimeException('Environment variable '.$name.' is not configured.');
        return $v;
    }
    private function parcel(array $c,array $i): array
    {
        $p=['length'=>(float)($i['length']??$c['default_length']),'width'=>(float)($i['width']??$c['default_width']),
            'height'=>(float)($i['height']??$c['default_height']),'weight'=>(float)($i['weight_oz']??$c['default_weight_oz'])];
        foreach($p as $k=>$v){if($v<=0)throw new RuntimeException('Package '.$k.' must be greater than zero.');}
        return $p;
    }
    private function storeAddress(array $c): array
    {
        foreach(['return_street1','return_city','return_state','return_postal_code','return_country'] as $f){if(trim((string)($c[$f]??''))==='')throw new RuntimeException('Complete the structured carrier return address before requesting rates.');}
        return ['name'=>$c['return_name'],'company'=>$c['return_company'],'street1'=>$c['return_street1'],'street2'=>$c['return_street2'],
            'city'=>$c['return_city'],'state'=>$c['return_state'],'zip'=>$c['return_postal_code'],'country'=>$c['return_country'],
            'phone'=>$c['return_phone'],'email'=>$c['return_email']];
    }
    private function customerAddress(array $r): array
    {
        foreach(['customer_address_line_1','customer_city','customer_state','customer_postal_code'] as $f){if(trim((string)($r[$f]??''))==='')throw new RuntimeException('The customer order address is incomplete for live carrier rates.');}
        return ['name'=>$r['customer_name'],'street1'=>$r['customer_address_line_1'],'street2'=>$r['customer_address_line_2'],
            'city'=>$r['customer_city'],'state'=>$r['customer_state'],'zip'=>$r['customer_postal_code'],'country'=>$r['customer_country']?:'US',
            'phone'=>$r['customer_phone'],'email'=>$r['customer_email']];
    }
    private function storeAddressText(array $c): string { return trim(implode("\n",array_filter([$c['return_name']?:$c['return_company'],$c['return_street1'],$c['return_street2'],trim($c['return_city'].', '.$c['return_state'].' '.$c['return_postal_code']),$c['return_country']]))); }
    private function customerAddressText(array $r): string { return trim(implode("\n",array_filter([$r['customer_address_line_1'],$r['customer_address_line_2'],trim($r['customer_city'].', '.$r['customer_state'].' '.$r['customer_postal_code']),$r['customer_country']]))); }
}
