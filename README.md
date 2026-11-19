# Registro de ventas de zapatos

Aplicación personal en PHP y MySQL/MariaDB para administrar modelos, fotos, comandas y ganancias.

## Iniciar en XAMPP

1. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
2. Para una instalación nueva, abre phpMyAdmin e importa `schema.sql`.
3. Si actualizas desde una versión anterior, respalda primero la base y ejecuta las migraciones pendientes en orden: `migration_v2.sql`, `migration_v3.sql` y `migration_v4.sql`. Ejecuta cada una una sola vez.
4. Abre `http://localhost/RegistroVenta/`. El navbar separa **Inicio**, **Catálogo**, **Crear comanda** y **Comandas**.

La conexión predeterminada usa `127.0.0.1`, base de datos `registro_ventas_zapatos`, usuario `root` y contraseña vacía, como en una instalación típica de XAMPP. Si tu instalación tiene otros datos, configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` para Apache/PHP.

## Uso como aplicación web progresiva (PWA)

La aplicación incluye un manifest y un Service Worker. Para instalarla en un servidor, debe servirse por HTTPS; `http://localhost` también se considera un origen seguro para desarrollo. En Android, usa la opción de instalar aplicación del navegador. En iOS, abre el sitio en Safari y elige **Compartir > Añadir a pantalla de inicio**.

Los íconos de instalación están en `icons/icon-180.png`, `icons/icon-192.png` y `icons/icon-512.png`. El Service Worker guarda los recursos estáticos y las páginas e imágenes que se hayan cargado correctamente; esos recursos visitados pueden volver a mostrarse sin conexión. Las operaciones que guardan cambios necesitan conexión al servidor y a la base de datos.

La migración consolida registros del mismo modelo y marca, mueve sus fotos e historial de ventas al registro conservado y elimina del catálogo los campos de talla y stock. Si había registros duplicados con precios o costos distintos, conserva los precios del registro con el ID más antiguo; revisa esos casos antes de migrar.

## Uso

- Registra cada modelo una sola vez con marca, fotos, costo de compra por par y precio de venta por par. No se lleva stock.
- Para una comanda, indica cuántos pares incluye. Cada par permite elegir su modelo, talla del 34 al 44 y “Ramoneado” Sí/No.
- Completa nombre y celular del comprador, además del domicilio cobrado, el costo real del domicilio, dirección y barrio. El total se calcula con los modelos y el domicilio; puedes editar el total cobrado.
- En **Comandas**, se muestran hasta las 1000 comandas más recientes. Puedes filtrar por nombre del comprador, fecha, número de comanda, estado, barrio o celular; se aplica un criterio a la vez.
- Cada comanda muestra el resumen en columnas de cantidad, modelo y precio, junto con tallas, Ramoneado, domicilio y totales.
- La ganancia neta se calcula como total cobrado menos el costo de compra de cada par y el costo real del domicilio. “Ramoneado” solo se guarda como recordatorio y no afecta el cálculo.
- Cada comanda puede marcarse como **Vendido**, **Cancelado**, **Devuelto - pagó domicilio** o **Devuelto - no pagó domicilio**. Solo las vendidas cuentan como ingresos y pares vendidos; canceladas y devueltas con domicilio pagado suman $0 de ganancia. Una devolución sin domicilio pagado resta el costo real del domicilio como pérdida.
- Las fotos se guardan en `uploads/`. Se aceptan JPG, PNG, WEBP y GIF, hasta 5 MB por foto y 8 fotos por carga.

Para respaldar la aplicación, conserva tanto la base de datos como la carpeta `uploads/`. Si XAMPP rechaza fotos grandes, revisa `upload_max_filesize` y `post_max_size` en `php.ini`.
