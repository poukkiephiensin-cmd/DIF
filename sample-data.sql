-- Exact sample rows from online_clothing_examples.xlsx. Import ONCE into empty tables.

SET NAMES utf8mb4;

USE online_clothing_store;

START TRANSACTION;

INSERT INTO `customers` (`customer_id`, `full_name`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'ນາລີ ວົງສາ', '020 0000 0001', 'naly@example.com', 'ບ້ານທາດຫຼວງໃຕ້, ເມືອງໄຊເສດຖາ', '2026-11-01 09:15:00'),
(2, 'ສຸລິຍາ ອິນທະວົງ', '020 0000 0002', 'souliya@example.com', 'ບ້ານສີສະຫວາດ, ເມືອງຈັນທະບູລີ', '2026-11-02 13:20:00'),
(3, 'ມະນີ ສີຫາລາດ', '020 0000 0003', NULL, 'ບ້ານທ່າພະລານໄຊ, ເມືອງສີສັດຕະນາກ', '2026-11-05 11:00:00');

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'ເສື້ອຍືດ', 'ເສື້ອຍືດແຂນສັ້ນ ແລະ ແຂນຍາວ'),
(2, 'ໂສ້ງ', 'ໂສ້ງຢີນ ແລະ ໂສ້ງຜ້າ'),
(3, 'ສິ້ນ', 'ສິ້ນຝ້າຍ ແລະ ສິ້ນໄໝ');

INSERT INTO `products` (`product_id`, `category_id`, `sku`, `product_name`, `size_label`, `color`, `price`, `stock_qty`) VALUES
(1, 1, 'TEE-WHT-M', 'ເສື້ອຍືດຄໍມົນ', 'M', 'ຂາວ', 85000, 18),
(2, 1, 'TEE-WHT-L', 'ເສື້ອຍືດຄໍມົນ', 'L', 'ຂາວ', 85000, 12),
(3, 1, 'TEE-BLK-M', 'ເສື້ອຍືດຄໍມົນ', 'M', 'ດຳ', 85000, 10),
(4, 2, 'JEAN-BLU-30', 'ໂສ້ງຢີນຂາຊື່', '30', 'ນ້ຳເງິນ', 195000, 8),
(5, 3, 'SIN-IND-M', 'ສິ້ນຝ້າຍທໍມື', 'M', 'ຄາມ', 245000, 6);

INSERT INTO `orders` (`order_id`, `customer_id`, `ordered_at`, `order_status`, `recipient_name`, `recipient_phone`, `shipping_address`, `shipping_fee`) VALUES
(1001, 1, '2026-11-10 10:00:00', 'completed', 'ນາລີ ວົງສາ', '020 0000 0001', 'ບ້ານທາດຫຼວງໃຕ້, ເມືອງໄຊເສດຖາ', 15000),
(1002, 2, '2026-11-12 15:20:00', 'shipped', 'ສຸລິຍາ ອິນທະວົງ', '020 0000 0002', 'ບ້ານສີສະຫວາດ, ເມືອງຈັນທະບູລີ', 20000),
(1003, 1, '2026-11-16 09:40:00', 'pending', 'ມະນີ ສີຫາລາດ', '020 0000 0003', 'ບ້ານທ່າພະລານໄຊ, ເມືອງສີສັດຕະນາກ', 15000);

INSERT INTO `order_details` (`order_detail_id`, `order_id`, `product_id`, `quantity`, `unit_price`) VALUES
(1, 1001, 1, 2, 80000),
(2, 1001, 4, 1, 195000),
(3, 1002, 5, 1, 245000),
(4, 1003, 2, 1, 85000),
(5, 1003, 3, 1, 85000);

INSERT INTO `payments` (`payment_id`, `order_id`, `payment_method`, `amount`, `payment_status`, `transaction_ref`, `created_at`, `paid_at`) VALUES
(5001, 1001, 'bank_transfer', 370000, 'paid', 'DEMO-BNK-001', '2026-11-10 10:03:00', '2026-11-10 10:06:00'),
(5002, 1002, 'qr', 265000, 'failed', 'DEMO-QR-002', '2026-11-12 15:22:00', NULL),
(5003, 1002, 'qr', 265000, 'paid', 'DEMO-QR-003', '2026-11-12 15:25:00', '2026-11-12 15:26:00'),
(5004, 1003, 'cash_on_delivery', 185000, 'pending', NULL, '2026-11-16 09:41:00', NULL);

COMMIT;
