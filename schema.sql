CREATE DATABASE IF NOT EXISTS registro_ventas_zapatos
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE registro_ventas_zapatos;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand VARCHAR(100) NOT NULL DEFAULT '',
  model VARCHAR(150) NOT NULL,
  description VARCHAR(1000) NOT NULL DEFAULT '',
  cost_price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_brand_model (brand, model),
  INDEX idx_products_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_photos_product_id (product_id),
  CONSTRAINT fk_photos_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  total DECIMAL(12,2) NOT NULL,
  gross_profit DECIMAL(12,2) NOT NULL,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_collected DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  net_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  buyer_name VARCHAR(150) NOT NULL DEFAULT '',
  buyer_phone VARCHAR(30) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  neighborhood VARCHAR(150) NOT NULL DEFAULT '',
  status VARCHAR(40) NOT NULL DEFAULT 'sold',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_sales_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  brand VARCHAR(100) NOT NULL DEFAULT '',
  model VARCHAR(150) NOT NULL,
  size VARCHAR(20) NOT NULL,
  ramoneado TINYINT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL,
  unit_cost DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_sale_items_sale_id (sale_id),
  INDEX idx_sale_items_product_id (product_id),
  CONSTRAINT fk_items_sale
    FOREIGN KEY (sale_id) REFERENCES sales (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_items_product
    FOREIGN KEY (product_id) REFERENCES products (id)
    ON DELETE SET NULL
) ENGINE=InnoDB;
