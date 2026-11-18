# Verification record

The implementation was exercised with **PHP 8.4.27, PDO MySQL and Oracle MySQL Community Server 8.4.13**. The live preview uses the original `online_clothing_store` database. Mutating end-to-end checks used a disposable, separately seeded database on the same MySQL service, with the test copy of the connection pointed only at that schema. The distributed connection and DDL retain the original database name.

| Check performed | Observed result |
|---|---|
| Native PDO connection, original schema and workbook import | 6 tables, 38 columns, 5 FKs; row counts 3 / 3 / 5 / 3 / 5 / 4 |
| Catalogue, search, category filter, price sorting and variation navigation | Five live SKUs; the T-shirt category returns three; a size change opens the correct SKU |
| Bag add, update, remove, quantity above availability | Correct totals and updates; unavailable quantity rejected |
| Checkout with two tees and one pair of jeans | New order total 380,000 ₭; two order lines, one pending payment; stock 18→16 and 8→7 |
| Deliberate payment-insert failure late in checkout (temporary QA-only trigger, then removed) | Customer, order, line and stock changes rolled back together; bag retained |
| Re-submit the same checkout | Same receipt; no second order or stock decrement |
| Two separate database sessions competing for one last unit | One order accepted, one stock error; remaining stock zero |
| Confirm two full-balance attempts concurrently | Only one verified; other confirmation rejected, no overpayment |
| Phone, optional email, separate recipient and repeat customer | Leading zero preserved, existing customer reused, order-specific address saved |
| Multiple attempts: fail the first; pay 100,000 and 280,000 on separate attempts | All three attempts preserved, paid total exactly 380,000; `paid_at` set only on paid attempts |
| Sales after this test order | Increased from 635,000 to 1,015,000 ₭; no pending/failed amount counted |
| Fulfilment and cancellation | Confirm → ship → complete worked; cancelling another unpaid order restored inventory once, even on repeated submission |
| Studio management | Login, category/product create/edit/delete, stock edit, photo re-encoding to JPEG, customer search and order history worked |
| Historical records | Cannot delete a referenced product or a category with products |
| Privacy and forms | Guest admin requests redirect to sign-in; receipts not available from another session; missing CSRF token rejected; sign-out ends access |
| Newsletter | Sign-up saved privately; no extra database table |
| Browser | Headless Chromium: hero controls, collection tabs, mobile menu, search, variation links, quantity stepper, shipping recalculation and separate-recipient fields exercised |
| Layout | Public pages checked at 360, 390, 768, 1366 and/or 1440 px; private tables scroll inside their container at 390 px. No page-wide overflow or JavaScript exceptions in tested flows |

## Repeat on your own local installation

Use an expendable local database or make a backup before a demonstration. `php tests/verify.php --sample` is safe to run on a fresh import; it does not create or change records. For a complete demonstration:

1. Confirm `1001` is 370,000 ₭ and that only 635,000 ₭ has been verified across the sample payments.
2. Search `TEE`, choose a size, add two units, and change the quantity in the bag.
3. Order two `TEE-WHT-M` and one `JEAN-BLU-30` with standard delivery; compare the receipt, stock and admin totals.
4. Confirm the order remains pending; verify a payment manually or mark an unsuccessful attempt failed and record a new attempt. Compare the ledger with the dashboard.
5. Try deleting the ordered product: the application should preserve it. Use a fresh, unreferenced test SKU to demonstrate deletion.
6. Sign out and revisit a private page. Open a receipt in a different browser session. Both checks should prevent access to private data.
