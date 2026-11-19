USE registro_ventas_zapatos;

ALTER TABLE sales
  ADD COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN total_collected DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN net_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN neighborhood VARCHAR(150) NOT NULL DEFAULT '';

UPDATE sales
SET total_collected = total,
    net_profit = gross_profit;

ALTER TABLE sale_items
  ADD COLUMN ramoneado TINYINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TEMPORARY TABLE product_merge_map (
  product_id INT UNSIGNED NOT NULL PRIMARY KEY,
  keeper_id INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO product_merge_map (product_id, keeper_id)
SELECT p.id, grouped.keeper_id
FROM products p
JOIN (
  SELECT brand, model, MIN(id) AS keeper_id
  FROM products
  GROUP BY brand, model
) grouped ON grouped.brand = p.brand AND grouped.model = p.model;

UPDATE product_photos photo
JOIN product_merge_map merge_map ON merge_map.product_id = photo.product_id
SET photo.product_id = merge_map.keeper_id
WHERE merge_map.product_id <> merge_map.keeper_id;

UPDATE sale_items item
JOIN product_merge_map merge_map ON merge_map.product_id = item.product_id
SET item.product_id = merge_map.keeper_id
WHERE merge_map.product_id <> merge_map.keeper_id;

DELETE product
FROM products product
JOIN product_merge_map merge_map ON merge_map.product_id = product.id
WHERE merge_map.product_id <> merge_map.keeper_id;

DROP TEMPORARY TABLE product_merge_map;

ALTER TABLE products
  DROP INDEX idx_products_model_size,
  DROP COLUMN size,
  DROP COLUMN stock;

ALTER TABLE products
  ADD UNIQUE KEY uq_products_brand_model (brand, model);
