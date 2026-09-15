<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Shipping\CarrierProviderInterface;
use RuntimeException;

class EasyPostCarrierProvider implements CarrierProviderInterface
{
    private const API_BASE = 'https://api.easypost.com/v2';
    public function __construct(private string $apiKey) {}
    public function providerName(): string { return 'easypost'; }
    public function createShipment(array $shipment): array { return $this->request('POST','/shipments',['shipment'=>$shipment]); }
    public function buyShipment(string $shipmentId, string $rateId): array { return $this->request('POST','/shipments/'.rawurlencode($shipmentId).'/buy',['rate'=>['id'=>$rateId]]); }
    public function retrieveShipment(string $shipmentId): array { return $this->request('GET','/shipments/'.rawurlencode($shipmentId),[]); }

    private function request(string $method, string $path, array $payload): array
    {
        if (! function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for EasyPost.');
        $ch=curl_init(self::API_BASE.$path);
        $json=json_encode($payload,JSON_UNESCAPED_SLASHES);
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_USERPWD=>$this->apiKey.':',
            CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30];
        if ($method !== 'GET') { $options[CURLOPT_POSTFIELDS]=$json; }
        curl_setopt_array($ch,$options);
        $body=curl_exec($ch); $errno=curl_errno($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($errno!==0) throw new RuntimeException('EasyPost connection failed: '.$error);
        $data=json_decode((string)$body,true);
        if (!is_array($data)) throw new RuntimeException('EasyPost returned an invalid response.');
        if ($status<200||$status>=300){
            $message=$data['error']['message']??$data['message']??('EasyPost request failed with HTTP '.$status.'.');
            $errors=$data['error']['errors']??[];
            if (is_array($errors)&&$errors){ $extra=[]; foreach($errors as $item){ if(is_array($item)&&!empty($item['message']))$extra[]=$item['message']; } if($extra)$message.=' '.implode(' ',$extra); }
            throw new RuntimeException($message);
        }
        return $data;
    }
}
