<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CarrierIntegrationRepository
{
    public function __construct(private PDO $db) {}

    public function forStore(int $storeId, string $provider = 'easypost'): array
    {
        $stmt = $this->db->prepare("SELECT * FROM carrier_integrations WHERE store_id = :store_id AND provider = :provider LIMIT 1");
        $stmt->execute(['store_id'=>$storeId,'provider'=>$provider]);
        $row = $stmt->fetch();
        return $row ? $this->normalize($row) : $this->defaults($storeId, $provider);
    }

    public function save(int $storeId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO carrier_integrations (
                store_id, provider, is_enabled, mode, api_key_env, webhook_secret_env,
                return_name, return_company, return_street1, return_street2, return_city,
                return_state, return_postal_code, return_country, return_phone, return_email,
                default_length, default_width, default_height, default_weight_oz, label_format,
                created_at, updated_at
            ) VALUES (
                :store_id, 'easypost', :is_enabled, :mode, :api_key_env, :webhook_secret_env,
                :return_name, :return_company, :return_street1, :return_street2, :return_city,
                :return_state, :return_postal_code, :return_country, :return_phone, :return_email,
                :default_length, :default_width, :default_height, :default_weight_oz, :label_format,
                NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                is_enabled=VALUES(is_enabled), mode=VALUES(mode), api_key_env=VALUES(api_key_env),
                webhook_secret_env=VALUES(webhook_secret_env), return_name=VALUES(return_name),
                return_company=VALUES(return_company), return_street1=VALUES(return_street1),
                return_street2=VALUES(return_street2), return_city=VALUES(return_city),
                return_state=VALUES(return_state), return_postal_code=VALUES(return_postal_code),
                return_country=VALUES(return_country), return_phone=VALUES(return_phone),
                return_email=VALUES(return_email), default_length=VALUES(default_length),
                default_width=VALUES(default_width), default_height=VALUES(default_height),
                default_weight_oz=VALUES(default_weight_oz), label_format=VALUES(label_format),
                updated_at=NOW()
        ");
        $stmt->execute([
            'store_id'=>$storeId,'is_enabled'=>!empty($data['is_enabled'])?1:0,
            'mode'=>$data['mode'],'api_key_env'=>$data['api_key_env'],
            'webhook_secret_env'=>$data['webhook_secret_env'],'return_name'=>$this->nullable($data['return_name']??null),
            'return_company'=>$this->nullable($data['return_company']??null),'return_street1'=>$this->nullable($data['return_street1']??null),
            'return_street2'=>$this->nullable($data['return_street2']??null),'return_city'=>$this->nullable($data['return_city']??null),
            'return_state'=>$this->nullable($data['return_state']??null),'return_postal_code'=>$this->nullable($data['return_postal_code']??null),
            'return_country'=>strtoupper($data['return_country']??'US'),'return_phone'=>$this->nullable($data['return_phone']??null),
            'return_email'=>$this->nullable($data['return_email']??null),'default_length'=>$this->decimal($data['default_length']??10),
            'default_width'=>$this->decimal($data['default_width']??8),'default_height'=>$this->decimal($data['default_height']??4),
            'default_weight_oz'=>$this->decimal($data['default_weight_oz']??16),'label_format'=>$data['label_format']??'PDF',
        ]);
    }

    public function environmentValue(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if ($value === false || trim((string)$value) === '') return null;
        return trim((string)$value);
    }

    private function defaults(int $storeId, string $provider): array
    {
        return ['id'=>null,'store_id'=>$storeId,'provider'=>$provider,'is_enabled'=>0,'mode'=>'test',
            'api_key_env'=>'EASYPOST_API_KEY','webhook_secret_env'=>'EASYPOST_WEBHOOK_SECRET',
            'return_name'=>null,'return_company'=>null,'return_street1'=>null,'return_street2'=>null,
            'return_city'=>null,'return_state'=>null,'return_postal_code'=>null,'return_country'=>'US',
            'return_phone'=>null,'return_email'=>null,'default_length'=>10.0,'default_width'=>8.0,
            'default_height'=>4.0,'default_weight_oz'=>16.0,'label_format'=>'PDF'];
    }
    private function normalize(array $row): array { $row['is_enabled']=(int)$row['is_enabled']; return $row; }
    private function nullable(mixed $v): ?string { $v=trim((string)$v); return $v!==''?$v:null; }
    private function decimal(mixed $v): string { return number_format(max(.01,(float)$v),2,'.',''); }
}
