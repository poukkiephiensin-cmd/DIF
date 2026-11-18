<?php require_once __DIR__.'/../includes/studio.php';
$q = mb_substr(strval_input($_GET, 'q'), 0, 100);
$cid = isset($_GET['id']) ? rid() : 0;
$customer = $cid ? row('SELECT * FROM customers WHERE customer_id=?', [$cid]) : null;
if ($cid && !$customer) {
    fail_page(404, 'Customer not found.');
}
studio_head($customer ? 'Customer record' : 'Customers', 'customers', 'Contact information and order history.');
if ($customer):?><article class="admin-form"><p class="eyebrow">CUSTOMER #<?=$cid?></p><h2><?=h($customer['full_name'])?></h2><p><?=h($customer['phone'])?> · <?=h($customer['email'] ?: 'No email supplied')?></p><p><?=nl2br(h($customer['address'] ?: 'No address supplied'))?></p><p class="small muted">Registered <?=h($customer['created_at'])?></p><a class="mini-link" href="<?=u('admin/customers.php')?>">All customers</a></article><div class="admin-section-title"><h2>Order history</h2></div><?php orders_table(rows(ORDER_TOTALS.' WHERE o.customer_id=? ORDER BY o.ordered_at DESC', [$cid]));
else:$list = rows('SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.customer_id) order_count FROM customers c WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY c.customer_id DESC', ['%'.$q.'%','%'.$q.'%','%'.$q.'%']);?><form class="admin-filter admin-toolbar" method="get"><input type="search" name="q" maxlength="100" aria-label="Customer search" placeholder="Search name, phone or email" value="<?=h($q)?>"><button class="btn outline">Search</button></form><div class="table-scroll"><table class="admin-table"><thead><tr><th>Customer</th><th>Phone</th><th>Email</th><th>Address</th><th>Orders</th></tr></thead><tbody><?php foreach ($list as $c):?><tr><td><a class="linked" href="<?=u('admin/customers.php?id='.$c['customer_id'])?>"><?=h($c['full_name'])?> ↗</a></td><td><?=h($c['phone'])?></td><td><?=h($c['email'] ?: '—')?></td><td><?=h($c['address'] ?: '—')?></td><td><?=$c['order_count']?></td></tr><?php endforeach;
    if (!$list) {
        echo '<tr><td colspan="5">No matching customers.</td></tr>';
    }?></tbody></table></div><?php endif;
studio_foot();?>
