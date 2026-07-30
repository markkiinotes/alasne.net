<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CarrierIntegrationRepository;
use App\Services\Shipping\EasyPostWebhookService;

class CarrierWebhookController extends Controller
{
    public function __construct(private CarrierIntegrationRepository $integrations, private EasyPostWebhookService $webhooks){parent::__construct();}
    public function easyPost(Request $request)
    {
        $storeId=(int)$request->route('store_id'); $config=$this->integrations->forStore($storeId);
        if((int)$config['is_enabled']!==1){return $this->json(['ok'=>false,'message'=>'Integration disabled'],404);}
        $secret=$this->integrations->environmentValue($config['webhook_secret_env']);
        if($secret===null)return $this->json(['ok'=>false,'message'=>'Webhook secret unavailable'],503);
        $raw=(string)file_get_contents('php://input'); $signature=$_SERVER['HTTP_X_HMAC_SIGNATURE']??'';
        $normalizedSecret = class_exists('Normalizer') ? (\Normalizer::normalize($secret, \Normalizer::FORM_KD) ?: $secret) : $secret;
        $expected='hmac-sha256-hex='.hash_hmac('sha256',$raw,$normalizedSecret);
        if($signature===''||!hash_equals($expected,$signature))return $this->json(['ok'=>false,'message'=>'Invalid signature'],401);
        $event=json_decode($raw,true); if(!is_array($event))return $this->json(['ok'=>false,'message'=>'Invalid JSON'],400);
        $eventMode = strtolower(
            (string) ($event['mode'] ?? '')
        );
        if (
            $eventMode !== ''
            && $eventMode !== strtolower(
                (string) $config['mode']
            )
        ) {
            return $this->json(
                ['ok'=>false,'message'=>'Webhook mode mismatch'],
                409
            );
        }
        try{$result=$this->webhooks->process($storeId,$event,$raw);return $this->json(['ok'=>true,'result'=>$result],200);}
        catch(\Throwable $e){return $this->json(['ok'=>false,'message'=>$e->getMessage()],500);}
    }
    private function json(array $payload,int $status): string {http_response_code($status);header('Content-Type: application/json');return (string)json_encode($payload,JSON_UNESCAPED_SLASHES);}
}
