USE registro_ventas_zapatos;

ALTER TABLE sales
  ADD COLUMN liquidation_status VARCHAR(20) NOT NULL DEFAULT 'pending';
