<?php

declare(strict_types=1);
$escape=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$value=static fn(string $f,mixed $d='')=>array_key_exists($f,$old)?$old[$f]:($config[$f]??$d);
$checked=(int)$value('is_enabled',0)===1?'checked':'';
?>
<style>
.carrier-page{display:grid;gap:22px}.carrier-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:22px;align-items:start}.carrier-panel{padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:16px}.carrier-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.carrier-alert{padding:14px 16px;border-radius:12px;font-weight:700}.carrier-alert.success{background:#dcfce7;color:#166534}.carrier-alert.error{background:#fee2e2;color:#991b1b}.carrier-status{padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;margin-bottom:12px}.carrier-status span{display:block;color:#64748b;font-size:12px;font-weight:800;text-transform:uppercase}.carrier-code{padding:12px;border-radius:10px;background:#0f172a;color:#e2e8f0;overflow-wrap:anywhere}@media(max-width:900px){.carrier-grid,.carrier-two{grid-template-columns:1fr}}</style>
<div class="carrier-page">
<section class="page-header"><h1>EasyPost Carrier Integration</h1><p><?= $escape($store['name']) ?> · Live rates, purchased postage, scannable labels, and tracking webhooks</p><a href="/admin/stores/<?= $escape($store['id']) ?>" class="button-muted">Back to Store</a></section>
<?php if($success):?><div class="carrier-alert success"><?= $escape($success) ?></div><?php endif;?>
<?php if($error):?><div class="carrier-alert error"><?= $escape($error) ?></div><?php endif;?>
<form method="POST" action="/admin/stores/<?= $escape($store['id']) ?>/carrier-integration" class="carrier-grid">
<input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
<section class="carrier-panel">
<h2>Connection</h2>
<label style="display:flex;gap:12px;align-items:flex-start;padding:14px;background:#f8fafc;border-radius:12px"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" <?= $checked ?>><span><strong>Enable EasyPost for this store</strong><br><small>Manual labels remain available when disabled.</small></span></label><br>
<div class="carrier-two">
<div class="form-group"><label>Mode</label><select name="mode"><option value="test" <?= $value('mode','test')==='test'?'selected':'' ?>>Test</option><option value="production" <?= $value('mode')==='production'?'selected':'' ?>>Production</option></select></div>
<div class="form-group"><label>Label Format</label><select name="label_format"><?php foreach(['PDF','PNG','ZPL'] as $f):?><option value="<?= $f ?>" <?= $value('label_format','PDF')===$f?'selected':'' ?>><?= $f ?></option><?php endforeach;?></select></div>
<div class="form-group"><label>API Key Environment Variable</label><input type="text" name="api_key_env" value="<?= $escape($value('api_key_env','EASYPOST_API_KEY')) ?>" required></div>
<div class="form-group"><label>Webhook Secret Environment Variable</label><input type="text" name="webhook_secret_env" value="<?= $escape($value('webhook_secret_env','EASYPOST_WEBHOOK_SECRET')) ?>" required></div>
</div>
<h2>Structured Return Address</h2><p class="form-help">Required by carriers for rating and label purchase. This is separate from the human-readable policy address.</p>
<div class="carrier-two">
<div class="form-group"><label>Name</label><input name="return_name" value="<?= $escape($value('return_name')) ?>"></div><div class="form-group"><label>Company</label><input name="return_company" value="<?= $escape($value('return_company')) ?>"></div>
<div class="form-group"><label>Street 1</label><input name="return_street1" value="<?= $escape($value('return_street1')) ?>"></div><div class="form-group"><label>Street 2</label><input name="return_street2" value="<?= $escape($value('return_street2')) ?>"></div>
<div class="form-group"><label>City</label><input name="return_city" value="<?= $escape($value('return_city')) ?>"></div><div class="form-group"><label>State/Province</label><input name="return_state" value="<?= $escape($value('return_state')) ?>"></div>
<div class="form-group"><label>Postal Code</label><input name="return_postal_code" value="<?= $escape($value('return_postal_code')) ?>"></div><div class="form-group"><label>Country</label><input name="return_country" maxlength="2" value="<?= $escape($value('return_country','US')) ?>"></div>
<div class="form-group"><label>Phone</label><input name="return_phone" value="<?= $escape($value('return_phone')) ?>"></div><div class="form-group"><label>Email</label><input type="email" name="return_email" value="<?= $escape($value('return_email')) ?>"></div>
</div>
<h2>Default Parcel</h2><div class="carrier-two"><div class="form-group"><label>Length (in)</label><input type="number" step="0.01" min="0.01" name="default_length" value="<?= $escape($value('default_length',10)) ?>"></div><div class="form-group"><label>Width (in)</label><input type="number" step="0.01" min="0.01" name="default_width" value="<?= $escape($value('default_width',8)) ?>"></div><div class="form-group"><label>Height (in)</label><input type="number" step="0.01" min="0.01" name="default_height" value="<?= $escape($value('default_height',4)) ?>"></div><div class="form-group"><label>Weight (oz)</label><input type="number" step="0.01" min="0.01" name="default_weight_oz" value="<?= $escape($value('default_weight_oz',16)) ?>"></div></div>
<button type="submit" class="button-primary">Save Carrier Integration</button>
</section>
<aside class="carrier-panel"><h2>Environment Status</h2><div class="carrier-status"><span>API Key</span><strong><?= $api_key_present?'Environment variable found':'Missing environment variable' ?></strong></div><div class="carrier-status"><span>Webhook Secret</span><strong><?= $webhook_secret_present?'Environment variable found':'Missing environment variable' ?></strong></div><h3>Webhook URL</h3><div class="carrier-code"><?= $escape($webhook_url) ?></div><p><small>EasyPost requires a publicly reachable HTTPS endpoint. Localhost will not receive live webhooks without a tunnel.</small></p><h3>.env entries</h3><div class="carrier-code"><?= $escape($value('api_key_env','EASYPOST_API_KEY')) ?>=your_key<br><?= $escape($value('webhook_secret_env','EASYPOST_WEBHOOK_SECRET')) ?>=your_secret</div><p><small>Secrets are never stored in the database or displayed here.</small></p></aside>
</form></div>
