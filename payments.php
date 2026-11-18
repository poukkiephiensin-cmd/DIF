<?php require_once __DIR__.'/../includes/studio.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_post();
    $returnId = 0;
    try {
        $returnId = positive($_POST['return_order'] ?? 0, 4294967295, 0);
        $ref = field('transaction_ref', 100, false);
        $act = choice('action', ['attempt','resolve']);
        if ($act === 'attempt') {
            record_attempt(positive($_POST['order_id'] ?? ''), choice('payment_method', array_keys(METHODS)), amount_field('amount'), $ref);
        } else {
            resolve_payment(positive($_POST['payment_id'] ?? ''), choice('payment_status', ['paid','failed']), $ref);
        }
        flash('Payment record saved.');
    } catch (Throwable $e) {
        flash(admin_error($e), 'error');
    }
    go($returnId ? 'admin/order-detail.php?id='.$returnId : 'admin/payments.php');
}
$st = strval_input($_GET, 'status');
if (!in_array($st, PAY_STATES, true)) {
    $st = '';
}
studio_head('Payment ledger', 'payments', 'Only verified paid records contribute to sales. Open an order to record an additional attempt.');?><form class="admin-filter admin-toolbar"><select name="status" aria-label="Filter payment status"><option value="">All payment statuses</option><?php foreach (PAY_STATES as $v):?><option value="<?=$v?>" <?=$v === $st ? 'selected' : ''?>><?=ucfirst($v)?></option><?php endforeach;?></select><button class="btn outline">Filter</button></form><?php payment_table(rows('SELECT * FROM payments'.($st ? ' WHERE payment_status=?' : '').' ORDER BY payment_id DESC', $st ? [$st] : []));
studio_foot();?>
