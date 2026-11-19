USE registro_ventas_zapatos;

ALTER TABLE sales
  ADD COLUMN buyer_name VARCHAR(150) NOT NULL DEFAULT '',
  ADD COLUMN buyer_phone VARCHAR(30) NOT NULL DEFAULT '';
