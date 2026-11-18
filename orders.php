<?php

declare(strict_types=1);
require_once __DIR__.'/functions.php';
/** Server-authoritative checkout: locks variants in PK order; writes all four records and stock atomically. */
function place_order(array $cart, array $data, array $quote): int
{
    if (!$cart || count($cart) > 40) {
        throw new InputError('Please review your bag before checkout.');
    }
    ksort($cart, SORT_NUMERIC);
    $ids = array_keys($cart);
    $db = db();
    $db->beginTransaction();
    try {
        $products = rows('SELECT product_id,sku,price,stock_qty FROM products WHERE product_id IN ('.implode(',', array_fill(0, count($ids), '?')).') ORDER BY product_id FOR UPDATE', $ids);
        if (count($products) !== count($ids)) {
            throw new InputError('A piece is no longer available. Please review your bag.');
        }
        $fee = SHIPPING[$data['service']];
        $guard = cents($fee);
        foreach ($products as $p) {
            $id = $p['product_id'];
            $n = positive($cart[$id], 99);
            if ($n > $p['stock_qty']) {
                throw new InputError('Not enough stock for '.$p['sku'].'. Please update your bag.');
            }
            if (!isset($quote[$id]) || cents($quote[$id]) !== cents($p['price'])) {
                throw new InputError('A price has changed. Review the updated summary and submit again.');
            }
            $guard += cents($p['price']) * $n;
            if ($guard > 999999999999999) {
                throw new InputError('The order amount exceeds the supported payment limit.');
            }
        }
        $customer = null;
        if ($data['email'] !== '') {
            $customer = row('SELECT customer_id,full_name,phone,email FROM customers WHERE email=? FOR UPDATE', [$data['email']]);
            if ($customer && ($customer['phone'] !== $data['phone'] || $customer['full_name'] !== $data['full_name'])) {
                throw new InputError('These contact details could not be matched. Check the name, phone and email, or omit the optional email.');
            }
        }
        if (!$customer) {
            $customer = row('SELECT customer_id,full_name,phone,email FROM customers WHERE full_name=? AND phone=?'.($data['email'] !== '' ? ' AND email IS NULL' : '').' ORDER BY customer_id LIMIT 1 FOR UPDATE', [$data['full_name'],$data['phone']]);
        }
        if (!$customer) {
            stmt('INSERT INTO customers (full_name,phone,email,address) VALUES (?,?,?,?)', [$data['full_name'],$data['phone'],$data['email'] ?: null,$data['address']]);
            $cid = (int)$db->lastInsertId();
        } else {
            $cid = (int)$customer['customer_id'];
            if ($data['email'] !== '' && $customer['email'] === null) {
                stmt('UPDATE customers SET email=? WHERE customer_id=?', [$data['email'],$cid]);
            }
        }
        stmt('INSERT INTO orders (customer_id,recipient_name,recipient_phone,shipping_address,shipping_fee) VALUES (?,?,?,?,?)', [$cid,$data['recipient_name'],$data['recipient_phone'],$data['shipping_address'],$fee]);
        $oid = (int)$db->lastInsertId();
        foreach ($products as $p) {
            $n = $cart[$p['product_id']];
            stmt('INSERT INTO order_details (order_id,product_id,quantity,unit_price) VALUES (?,?,?,?)', [$oid,$p['product_id'],$n,$p['price']]);
            if (stmt('UPDATE products SET stock_qty=stock_qty-? WHERE product_id=? AND stock_qty>=?', [$n,$p['product_id'],$n])->rowCount() !== 1) {
                throw new InputError('Stock changed. Review the bag and try again.');
            }
        }
        $total = scalar('SELECT SUM(d.quantity*d.unit_price)+o.shipping_fee FROM orders o JOIN order_details d ON d.order_id=o.order_id WHERE o.order_id=? GROUP BY o.order_id,o.shipping_fee', [$oid]);
        stmt("INSERT INTO payments (order_id,payment_method,amount,payment_status,paid_at) VALUES (?,?,?,'pending',NULL)", [$oid,$data['payment_method'],$total]);
        $db->commit();
        return $oid;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
function order_lines(int $id): array
{
    return rows('SELECT d.quantity,d.unit_price,d.quantity*d.unit_price AS line_total,p.product_id,p.sku,p.product_name,p.color,p.size_label FROM order_details d JOIN products p ON p.product_id=d.product_id WHERE d.order_id=? ORDER BY d.order_detail_id', [$id]);
}
function transitions(string $s): array
{
    return ['pending' => ['confirmed','cancelled'],'confirmed' => ['shipped','cancelled'],'shipped' => ['completed'],'completed' => [],'cancelled' => []][$s] ?? [];
}
function locked_order(int $id): array
{
    $o = row('SELECT order_id,order_status,shipping_fee FROM orders WHERE order_id=? FOR UPDATE', [$id]);
    if (!$o) {
        throw new InputError('Order not found.');
    }
    return $o;
}
function balance_cents(int $id): int
{
    $o = row(ORDER_TOTALS.' WHERE o.order_id=?', [$id]);
    return cents($o['grand_total']) - cents($o['paid_total']);
}
function update_order(int $id, string $to): void
{
    $db = db();
    $db->beginTransaction();
    try {
        $o = locked_order($id);
        if (!in_array($to, transitions($o['order_status']), true)) {
            throw new InputError('This status change is not available. Reload the order.');
        }
        if ($to === 'completed' && balance_cents($id) !== 0) {
            throw new InputError('Verify the full payment before completing this order.');
        }
        if ($to === 'cancelled') {
            if (scalar("SELECT COUNT(*) FROM payments WHERE order_id=? AND payment_status='paid'", [$id])) {
                throw new InputError('An order with verified funds cannot be cancelled in this system.');
            }
            foreach (rows('SELECT product_id,quantity FROM order_details WHERE order_id=? ORDER BY product_id', [$id]) as $d) {
                stmt('UPDATE products SET stock_qty=stock_qty+? WHERE product_id=?', [$d['quantity'],$d['product_id']]);
            }
            stmt("UPDATE payments SET payment_status='failed',paid_at=NULL WHERE order_id=? AND payment_status='pending'", [$id]);
        }
        stmt('UPDATE orders SET order_status=? WHERE order_id=?', [$to,$id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
function record_attempt(int $oid, string $method, string $amount, string $ref): void
{
    $db = db();
    $db->beginTransaction();
    try {
        $o = locked_order($oid);
        if ($o['order_status'] === 'cancelled') {
            throw new InputError('Cancelled orders cannot receive payments.');
        }
        if (cents($amount) > balance_cents($oid)) {
            throw new InputError('The amount exceeds the remaining balance.');
        }
        stmt("INSERT INTO payments (order_id,payment_method,amount,transaction_ref,payment_status) VALUES (?,?,?,?,'pending')", [$oid,$method,$amount,$ref ?: null]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
function resolve_payment(int $pid, string $to, string $ref): void
{
    $db = db();
    $oid = (int)scalar('SELECT order_id FROM payments WHERE payment_id=?', [$pid]);
    $db->beginTransaction();
    try {
        $o = locked_order($oid);
        $p = row('SELECT * FROM payments WHERE payment_id=? FOR UPDATE', [$pid]);
        if (!$p || $p['payment_status'] !== 'pending') {
            throw new InputError('This attempt is already resolved. Add a new attempt for a retry.');
        }
        if (!in_array($to, ['paid','failed'], true)) {
            throw new InputError('Invalid payment status.');
        }
        if ($to === 'paid' && ($o['order_status'] === 'cancelled' || cents($p['amount']) > balance_cents($oid))) {
            throw new InputError('Cannot confirm this payment: the order is cancelled or the amount exceeds its balance.');
        }
        stmt("UPDATE payments SET payment_status=?,transaction_ref=?,paid_at=CASE WHEN ?='paid' THEN CURRENT_TIMESTAMP ELSE NULL END WHERE payment_id=?", [$to,$ref ?: ($p['transaction_ref'] ?: null),$to,$pid]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
