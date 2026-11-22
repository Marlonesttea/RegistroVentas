USE registro_ventas_zapatos;

ALTER TABLE sales
  ADD COLUMN liquidated_at DATETIME NULL DEFAULT NULL,
  ADD INDEX idx_sales_liquidation_status_date (liquidation_status, liquidated_at);
