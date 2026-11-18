-- ຖານຂໍ້ມູນຮ້ານຂາຍເສື້ອຜ້າອອນລາຍ | MySQL 8.0.16+
-- ລັນຄັ້ງດຽວເພື່ອສ້າງ 6 ຕາຕະລາງເປົ່າ. ບໍ່ມີຄຳສັ່ງລຶບຂໍ້ມູນ.
-- ລາຄາ, ຄ່າຈັດສົ່ງ ແລະ ຍອດຊຳລະ ໃຊ້ສະກຸນເງິນກີບ (LAK).

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS online_clothing_store
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE online_clothing_store;

-- 1. ລູກຄ້າ
CREATE TABLE customers (
    customer_id  INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດລູກຄ້າ',
    full_name    VARCHAR(120) NOT NULL COMMENT 'ຊື່ ແລະ ນາມສະກຸນ',
    phone        VARCHAR(25) NOT NULL COMMENT 'ເບີໂທລະສັບ',
    email        VARCHAR(254) NULL COMMENT 'ອີເມວ',
    address      VARCHAR(255) NULL COMMENT 'ທີ່ຢູ່ປົກກະຕິຂອງລູກຄ້າ',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'ວັນເວລາລົງທະບຽນ',
    PRIMARY KEY (customer_id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ໝວດສິນຄ້າ
CREATE TABLE categories (
    category_id    INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດໝວດສິນຄ້າ',
    category_name  VARCHAR(100) NOT NULL COMMENT 'ຊື່ໝວດສິນຄ້າ',
    description    VARCHAR(255) NULL COMMENT 'ຄຳອະທິບາຍໝວດສິນຄ້າ',
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_categories_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ສິນຄ້າ: ແຕ່ລະລຸ້ນ, ສີ ແລະ ຂະໜາດ ມີ SKU ແລະ ຈຳນວນຄົງເຫຼືອຂອງຕົນເອງ.
CREATE TABLE products (
    product_id    INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດສິນຄ້າ',
    category_id   INT UNSIGNED NOT NULL COMMENT 'ໝວດຂອງສິນຄ້າ',
    sku           VARCHAR(50) NOT NULL COMMENT 'ລະຫັດສິນຄ້າແຍກຕາມລຸ້ນ, ສີ ແລະ ຂະໜາດ',
    product_name  VARCHAR(160) NOT NULL COMMENT 'ຊື່ສິນຄ້າ',
    size_label    VARCHAR(20) NOT NULL COMMENT 'ຂະໜາດ ເຊັ່ນ M, L ຫຼື 30',
    color         VARCHAR(60) NOT NULL COMMENT 'ສີ',
    price         DECIMAL(15,2) NOT NULL COMMENT 'ລາຄາຂາຍປັດຈຸບັນ (ກີບ)',
    stock_qty     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ຈຳນວນຄົງເຫຼືອ',
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_products_sku (sku),
    KEY ix_products_category (category_id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (category_id) ON DELETE RESTRICT,
    CONSTRAINT ck_products_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ໃບສັ່ງຊື້: ບັນທຶກຜູ້ຮັບ ແລະ ທີ່ຢູ່ຈັດສົ່ງຕາມໃບສັ່ງຊື້ນັ້ນ.
CREATE TABLE orders (
    order_id          INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ເລກທີໃບສັ່ງຊື້',
    customer_id       INT UNSIGNED NOT NULL COMMENT 'ລູກຄ້າຜູ້ສັ່ງຊື້',
    ordered_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'ວັນເວລາສັ່ງຊື້',
    order_status      ENUM('pending','confirmed','shipped','completed','cancelled')
                      NOT NULL DEFAULT 'pending' COMMENT 'ສະຖານະໃບສັ່ງຊື້',
    recipient_name    VARCHAR(120) NOT NULL COMMENT 'ຊື່ຜູ້ຮັບ',
    recipient_phone   VARCHAR(25) NOT NULL COMMENT 'ເບີໂທຜູ້ຮັບ',
    shipping_address  VARCHAR(255) NOT NULL COMMENT 'ທີ່ຢູ່ຈັດສົ່ງໃນຄັ້ງນີ້',
    shipping_fee      DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'ຄ່າຈັດສົ່ງ (ກີບ)',
    PRIMARY KEY (order_id),
    KEY ix_orders_customer_date (customer_id, ordered_at),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id) ON DELETE RESTRICT,
    CONSTRAINT ck_orders_shipping_fee CHECK (shipping_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ລາຍການສິນຄ້າໃນໃບສັ່ງຊື້
CREATE TABLE order_details (
    order_detail_id  INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດລາຍການ',
    order_id         INT UNSIGNED NOT NULL COMMENT 'ເລກທີໃບສັ່ງຊື້',
    product_id       INT UNSIGNED NOT NULL COMMENT 'ສິນຄ້າທີ່ສັ່ງ',
    quantity         INT UNSIGNED NOT NULL COMMENT 'ຈຳນວນທີ່ສັ່ງ',
    unit_price       DECIMAL(15,2) NOT NULL COMMENT 'ລາຄາຂາຍຈິງຕໍ່ໜ່ວຍໃນເວລາສັ່ງຊື້ (ກີບ)',
    PRIMARY KEY (order_detail_id),
    UNIQUE KEY uq_order_details_order_product (order_id, product_id),
    KEY ix_order_details_product (product_id),
    CONSTRAINT fk_order_details_order FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_details_product FOREIGN KEY (product_id)
        REFERENCES products (product_id) ON DELETE RESTRICT,
    CONSTRAINT ck_order_details_quantity CHECK (quantity > 0),
    CONSTRAINT ck_order_details_unit_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ການຊຳລະເງິນ: ນັບສະເພາະລາຍການ 'paid' ເຂົ້າໃນຍອດທີ່ຈ່າຍແລ້ວ.
CREATE TABLE payments (
    payment_id       INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ລະຫັດການຊຳລະ',
    order_id         INT UNSIGNED NOT NULL COMMENT 'ໃບສັ່ງຊື້ທີ່ຊຳລະ',
    payment_method   ENUM('bank_transfer','qr','cash_on_delivery') NOT NULL COMMENT 'ວິທີຊຳລະ',
    amount           DECIMAL(15,2) NOT NULL COMMENT 'ຈຳນວນເງິນຂອງລາຍການ (ກີບ)',
    payment_status   ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending' COMMENT 'ສະຖານະການຊຳລະ',
    transaction_ref  VARCHAR(100) NULL COMMENT 'ເລກອ້າງອີງທຸລະກຳ ຖ້າມີ',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'ວັນເວລາສ້າງລາຍການ',
    paid_at          DATETIME NULL COMMENT 'ວັນເວລາທີ່ຈ່າຍສຳເລັດ',
    PRIMARY KEY (payment_id),
    UNIQUE KEY uq_payments_ref (payment_method, transaction_ref),
    KEY ix_payments_order_status (order_id, payment_status),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE RESTRICT,
    CONSTRAINT ck_payments_amount CHECK (amount > 0),
    CONSTRAINT ck_payments_paid_at CHECK (
        (payment_status = 'paid' AND paid_at IS NOT NULL) OR
        (payment_status <> 'paid' AND paid_at IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ຕົວຢ່າງ: ຄິດຍອດຂອງແຕ່ລະໃບສັ່ງຊື້ ໂດຍບວກຄ່າຈັດສົ່ງພຽງຄັ້ງດຽວ.
-- SELECT o.order_id,
--        COALESCE(SUM(d.quantity * d.unit_price), 0) AS items_total,
--        o.shipping_fee,
--        COALESCE(SUM(d.quantity * d.unit_price), 0) + o.shipping_fee AS order_total
-- FROM orders AS o
-- LEFT JOIN order_details AS d ON d.order_id = o.order_id
-- GROUP BY o.order_id, o.shipping_fee
-- ORDER BY o.order_id;
