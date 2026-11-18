<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_once __DIR__.'/orders.php';
require_admin();
function studio_head(string $title, string $current, string $note = ''): void
{?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> · DIF Studio</title><link rel="icon" href="<?=u('assets/images/mark.svg')?>"><link rel="stylesheet" href="<?=u('assets/css/style.css')?>"><link rel="stylesheet" href="<?=u('assets/css/admin.css')?>"><script defer src="<?=u('assets/js/script.js')?>"></script></head><body class="studio"><header class="studio-bar"><a class="wordmark" href="<?=u('admin/dashboard.php')?>"><b>DIF<span>.</span></b><small>STUDIO<br>MANAGEMENT</small></a><div><a class="text-link" href="<?=u('index.php')?>">Storefront <?=icon('up')?></a><form action="<?=u('admin/logout.php')?>" method="post"><?=csrf()?><button class="btn outline">Sign out</button></form></div></header><div class="studio-layout"><aside class="studio-side"><p class="eyebrow">WORKSPACE</p><nav aria-label="Studio navigation"><?php foreach (['dashboard' => 'Overview','products' => 'Products','categories' => 'Categories','customers' => 'Customers','orders' => 'Orders','payments' => 'Payments'] as $k => $v):?><a href="<?=u('admin/'.$k.'.php')?>" class="<?=$current === $k ? 'selected' : ''?>"><?=$v?><span>↗</span></a><?php endforeach;?></nav><p class="sidebar-note">DIF SHOP<br><small>Vientiane · LAK</small></p></aside><main class="studio-main"><p class="eyebrow"><b></b> STUDIO / <?=h(strtoupper($current))?></p><h1 class="admin-title"><?=h($title)?></h1><?php if ($note !== ''):?><p class="intro"><?=h($note)?></p><?php endif;
    notices();
}
function studio_foot(): void
{
    echo '</main></div></body></html>';
}
function orders_table(array $list): void
{?><div class="table-scroll"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Placed</th><th>Status</th><th>Total</th><th>Paid</th></tr></thead><tbody><?php foreach ($list as $o):?><tr><td><a class="linked" href="<?=u('admin/order-detail.php?id='.(int)$o['order_id'])?>">#<?=$o['order_id']?> ↗</a></td><td><?=h($o['full_name'])?></td><td><?=h($o['ordered_at'])?></td><td><?=status_tag($o['order_status'])?></td><td class="no-wrap"><?=money($o['grand_total'])?></td><td class="no-wrap"><?=money($o['paid_total'])?></td></tr><?php endforeach;
    if (!$list) {
        echo '<tr><td colspan="6">No orders to show.</td></tr>';
    }?></tbody></table></div><?php }
function payment_table(array $list, int $returnId = 0): void
{?><div class="table-scroll"><table class="admin-table"><thead><tr><th>Attempt / order</th><th>Method</th><th>Amount</th><th>Status</th><th>Reference / dates</th><th>Verify</th></tr></thead><tbody><?php foreach ($list as $p):?><tr><td>#<?=$p['payment_id']?><br><a class="linked" href="<?=u('admin/order-detail.php?id='.$p['order_id'])?>">Order #<?=$p['order_id']?></a></td><td><?=h(METHODS[$p['payment_method']])?></td><td class="no-wrap"><?=money($p['amount'])?></td><td><?=status_tag($p['payment_status'])?></td><td><?=h($p['transaction_ref'] ?: '—')?><small>Created <?=h($p['created_at'])?></small><?php if ($p['paid_at']):?><small>Paid <?=h($p['paid_at'])?></small><?php endif;?></td><td><?php if ($p['payment_status'] === 'pending'):?><form class="compact-form" action="<?=u('admin/payments.php')?>" method="post" data-confirm="Have you checked this payment against the funds actually received?"><?=csrf()?><input type="hidden" name="action" value="resolve"><input type="hidden" name="payment_id" value="<?=$p['payment_id']?>"><input type="hidden" name="return_order" value="<?=$returnId?>"><input type="text" name="transaction_ref" aria-label="Transaction reference" maxlength="100" placeholder="Reference (optional)" value="<?=h($p['transaction_ref'])?>"><select name="payment_status" aria-label="Payment decision" required><option value="">Choose status</option><option value="paid">Verified paid</option><option value="failed">Failed</option></select><button class="btn dark">Save</button></form><?php else:?>Resolved<?php endif;?></td></tr><?php endforeach;
    if (!$list) {
        echo '<tr><td colspan="6">No payments to show.</td></tr>';
    }?></tbody></table></div><?php }
function admin_error(Throwable $e): string
{
    if ($e instanceof InputError) {
        return $e->getMessage();
    }
    error_log('[DIF studio] '.$e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        return 'This change conflicts with an existing unique value or a linked record. No records were removed.';
    }
    return 'The change could not be saved. Review your inputs and try again.';
}
