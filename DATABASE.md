# Schema contract

The DDL in `database/online_clothing_store.sql` is the original, unmodified file. The seed was generated from the named Excel table ranges, not from the worksheet headings, comments or educational notes. It preserves IDs, timestamps, leading-zero telephone strings, `NULL` values and all prices. `database/reference/` contains the source workbook and the latest simplified 1:N ER diagram.

| Table | Columns | Parent(s) | Role |
|---|---:|---|---|
| customers | 6 | — | Contact identity; optional unique email |
| categories | 3 | — | Existing catalogue groups |
| products | 8 | categories | One SKU per variation; current price and stock |
| orders | 8 | customers | Order header and shipping snapshot; no stored total |
| order_details | 5 | orders, products | Quantity and unit price at sale time; unique order/product pair |
| payments | 8 | orders | Independent attempts and verified amounts; reference uniqueness scoped to method |

## Totals without multiplying rows

`includes/functions.php` defines `ORDER_TOTALS`. Order lines and payments are **aggregated separately** before joining their results to the order. Joining both raw child tables directly would duplicate rows when an order has multiple lines and multiple attempts.

```sql
SELECT o.order_id,
       COALESCE(d.subtotal, 0) + o.shipping_fee AS grand_total,
       COALESCE(p.paid_total, 0) AS paid_total
FROM orders o
LEFT JOIN (
  SELECT order_id, SUM(quantity * unit_price) AS subtotal
  FROM order_details GROUP BY order_id
) d ON d.order_id = o.order_id
LEFT JOIN (
  SELECT order_id, SUM(amount) AS paid_total
  FROM payments WHERE payment_status = 'paid' GROUP BY order_id
) p ON p.order_id = o.order_id;
```

The dashboard's sales figure is `SUM(payments.amount)` **only where `payment_status='paid'`**. Checkout uses MySQL `DECIMAL` values and integer minor units in PHP, rather than using client-side arithmetic as a source of truth. JavaScript delivery totals are a display convenience only.

## Transaction boundaries

1. Checkout validates CSRF, its session-specific single-use nonce, the bag, customer fields, method and delivery service.
2. Begin a transaction. Lock product rows in primary-key order with `SELECT ... FOR UPDATE`; compare live prices and inventory with the reviewed bag.
3. Match the customer by email with matching name and phone, or match the name/phone pair, or create a customer. Ordinary checkout does not rewrite the profile of a different person. Save recipient details independently on the order.
4. Write order lines using database prices. Decrement each stock with a guarded update. Compute the payable amount with a SQL `JOIN` and `SUM`, adding delivery once. Insert one pending payment.
5. Commit **before** clearing the bag. On error roll everything back, preserve the bag and ask the shopper to review it. A repeated submission in the same session returns the existing receipt instead of placing a second order.
6. Administrative payment and fulfilment actions lock the parent order before verifying the outstanding balance or changing state. This serializes competing confirmations. Prevent overpayment, reopening a terminal status and restoring cancelled inventory twice.

Authentication, uploaded photos and newsletter signups live outside the six-table data model. The application never disables foreign keys to delete records. No migration, extra application table, trigger or view is required.
