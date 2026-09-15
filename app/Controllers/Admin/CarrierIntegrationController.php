<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CarrierIntegrationRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class CarrierIntegrationController extends Controller
{
    public function __construct(private StoreRepository $stores, private CarrierIntegrationRepository $integrations, private CsrfService $csrf){parent::__construct();}
    public function edit(Request $request){$id=(int)$request->route('store_id');$store=$this->stores->find($id);if(!$store){http_response_code(404);return '404 - Store not found';}
        $config=$this->integrations->forStore($id);$success=$_SESSION['carrier_success']??null;$error=$_SESSION['carrier_error']??null;$old=$_SESSION['carrier_old']??[];unset($_SESSION['carrier_success'],$_SESSION['carrier_error'],$_SESSION['carrier_old']);
        return $this->view('admin.carrier-integrations.edit',['title'=>'Carrier Integration | '.$store['name'],'store'=>$store,'config'=>$config,'old'=>$old,'csrf_token'=>$this->csrf->token(),'success'=>$success,'error'=>$error,
            'api_key_present'=>$this->integrations->environmentValue($config['api_key_env'])!==null,
            'webhook_secret_present'=>$this->integrations->environmentValue($config['webhook_secret_env'])!==null,
            'webhook_url'=>app_url('/webhooks/carriers/easypost/'.$id)],'admin');}
    public function update(Request $request){$id=(int)$request->route('store_id');$store=$this->stores->find($id);if(!$store){http_response_code(404);return '404 - Store not found';}
        if(!$this->csrf->validate((string)$this->request->input('_csrf_token'))){$_SESSION['carrier_error']='Security token expired. Please try again.';$this->response->redirect('/admin/stores/'.$id.'/carrier-integration');return;}
        $d=['is_enabled'=>(string)$this->request->input('is_enabled','0')==='1'?1:0,'mode'=>trim((string)$this->request->input('mode')),
            'api_key_env'=>trim((string)$this->request->input('api_key_env')),'webhook_secret_env'=>trim((string)$this->request->input('webhook_secret_env')),
            'return_name'=>trim((string)$this->request->input('return_name')),'return_company'=>trim((string)$this->request->input('return_company')),
            'return_street1'=>trim((string)$this->request->input('return_street1')),'return_street2'=>trim((string)$this->request->input('return_street2')),
            'return_city'=>trim((string)$this->request->input('return_city')),'return_state'=>trim((string)$this->request->input('return_state')),
            'return_postal_code'=>trim((string)$this->request->input('return_postal_code')),'return_country'=>strtoupper(trim((string)$this->request->input('return_country'))),
            'return_phone'=>trim((string)$this->request->input('return_phone')),'return_email'=>trim((string)$this->request->input('return_email')),
            'default_length'=>(float)$this->request->input('default_length'),'default_width'=>(float)$this->request->input('default_width'),
            'default_height'=>(float)$this->request->input('default_height'),'default_weight_oz'=>(float)$this->request->input('default_weight_oz'),
            'label_format'=>strtoupper(trim((string)$this->request->input('label_format')))]; $_SESSION['carrier_old']=$d;
        try{$this->validate($d);$this->integrations->save($id,$d);$this->csrf->regenerate();unset($_SESSION['carrier_old']);$_SESSION['carrier_success']='EasyPost carrier integration updated.';}
        catch(\Throwable $e){$_SESSION['carrier_error']=$e->getMessage()?:'Unable to update carrier integration.';}
        $this->response->redirect('/admin/stores/'.$id.'/carrier-integration');}
    private function validate(array $d): void {if(!in_array($d['mode'],['test','production'],true))throw new RuntimeException('Select test or production mode.');
        if(!preg_match('/^[A-Z_][A-Z0-9_]*$/',$d['api_key_env'])||!preg_match('/^[A-Z_][A-Z0-9_]*$/',$d['webhook_secret_env']))throw new RuntimeException('Environment variable names may contain uppercase letters, numbers, and underscores.');
        if(!in_array($d['label_format'],['PDF','PNG','ZPL'],true))throw new RuntimeException('Select PDF, PNG, or ZPL label format.');
        foreach(['default_length','default_width','default_height','default_weight_oz'] as $f){if((float)$d[$f]<=0)throw new RuntimeException('Parcel dimensions and weight must be greater than zero.');}
        if((int)$d['is_enabled']===1){foreach(['return_street1','return_city','return_state','return_postal_code','return_country'] as $f){if($d[$f]==='')throw new RuntimeException('Complete the structured return address before enabling EasyPost.');}}
        if($d['return_email']!==''&&!filter_var($d['return_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Return email address is invalid.'); if(!preg_match('/^[A-Z]{2}$/',$d['return_country']))throw new RuntimeException('Return country must be a two-letter code.');}
}
