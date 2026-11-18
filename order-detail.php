<?php require_once __DIR__.'/../includes/studio.php';
$id = rid();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    try {
        update_order($id, choice('order_status', ORDER_STATES));
        flash('Order status updated.');
    } catch (Throwable $e) {
        flash(admin_error($e), 'error');
    }
    go('admin/order-detail.php?id='.$id);
}
$o = row(ORDER_TOTALS.' WHERE o.order_id=?', [$id]);
if (!$o) {
    fail_page(404, 'Order not found.');
}
$lines = order_lines($id);
$bal = cents($o['grand_total']) - cents($o['paid_total']);
studio_head('Order #'.$id, 'orders', 'Placed '.$o['ordered_at'].' · Vientiane time.');?>
<div class="studio-panels"><section class="admin-panel"><h2>Contact & delivery</h2><a class="linked" href="<?=u('admin/customers.php?id='.$o['customer_id'])?>"><?=h($o['full_name'])?> ↗</a><p><?=h($o['email'] ?: 'No email')?> · <?=h($o['phone'])?></p><hr><p><b>Recipient</b><br><?=h($o['recipient_name'].' · '.$o['recipient_phone'])?><br><?=nl2br(h($o['shipping_address']))?></p></section><section class="admin-panel"><h2>Order status</h2><?=status_tag($o['order_status'])?><?php if ($moves = transitions($o['order_status'])):?><form class="compact-form spaced" method="post" data-confirm="Apply this order status? Cancelling an unpaid order restores its reserved stock once."><?=csrf()?><select name="order_status" aria-label="New order status" required><option value="">Select next status</option><?php foreach ($moves as $s):?><option value="<?=$s?>"><?=ucfirst($s)?></option><?php endforeach;?></select><button class="btn dark">Update</button></form><?php else:?><p class="small muted spaced">This is a final status.</p><?php endif;?></section></div><div class="admin-section-title"><h2>Purchased pieces</h2></div><div class="table-scroll"><table class="admin-table"><thead><tr><th>SKU / name</th><th>Size / colour</th><th>Quantity</th><th>Sale price</th><th>Line total</th></tr></thead><tbody><?php foreach ($lines as $p):?><tr><td><?=h($p['sku'])?><small><?=h($p['product_name'])?></small></td><td><?=h($p['size_label'].' / '.$p['color'])?></td><td><?=$p['quantity']?></td><td><?=money($p['unit_price'])?></td><td><?=money($p['line_total'])?></td></tr><?php endforeach;?></tbody></table></div><div class="admin-totals"><?php summary(cents($o['subtotal']), cents($o['shipping_fee']));?><p>Verified paid: <?=money($o['paid_total'])?> · Balance: <?=money(dec($bal))?></p></div><div class="admin-section-title"><h2>Payment attempts</h2></div><?php payment_table(rows('SELECT * FROM payments WHERE order_id=? ORDER BY payment_id DESC', [$id]), $id);
if ($o['order_status'] !== 'cancelled' && $bal > 0):?><form class="admin-form" method="post" action="<?=u('admin/payments.php')?>"><?=csrf()?><input type="hidden" name="action" value="attempt"><input type="hidden" name="order_id" value="<?=$id?>"><input type="hidden" name="return_order" value="<?=$id?>"><h2>Record a new attempt</h2><p class="small muted">Keep the original history. A new attempt is pending until staff verify the funds.</p><div class="field-grid"><label class="field">Method<select name="payment_method"><?php foreach (METHODS as $k => $v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?></select></label><?php input_box('Amount (LAK)', 'amount', dec($bal), 'text', true, 16);
    input_box('Reference (optional)', 'transaction_ref', '', 'text', false, 100);?></div><button class="btn dark">Record pending attempt</button></form><?php endif;
studio_foot();?>
