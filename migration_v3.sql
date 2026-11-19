USE registro_ventas_zapatos;

ALTER TABLE sales
  ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'sold';
