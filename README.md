# Stock Bartolo — Sistema de Gestión de Inventario, Ventas y Préstamos

Aplicación web moderna de **página única (SPA)** para la administración integral de inventario, punto de venta (órdenes), reportes y **gestión de préstamos a clientes con interés opcional**. Migrada desde una arquitectura PHP tradicional hacia **Vue 3 + Pinia + Vue Router + Tailwind CSS v4**, respaldada por una API REST JSON en **PHP 8** y base de datos **MySQL / MariaDB** con endurecimiento de seguridad contra XSS, CSRF, fuerza bruta e inyección SQL.

---

## Tabla de Contenidos

1. [Características Principales](#características-principales)
2. [Stack Tecnológico](#stack-tecnológico)
3. [Estructura del Proyecto](#estructura-del-proyecto)
4. [Requisitos Previos](#requisitos-previos)
5. [Instalación y Configuración](#instalación-y-configuración)
6. [Ejecución en Desarrollo y Producción](#ejecución-en-desarrollo-y-producción)
7. [Rutas de la Aplicación (SPA)](#rutas-de-la-aplicación-spa)
8. [Referencia de Endpoints API](#referencia-de-endpoints-api)
9. [Medidas de Seguridad Implementadas](#medidas-de-seguridad-implementadas)
10. [Credenciales Iniciales](#credenciales-iniciales)

---

## Características Principales

### 1. Diseño Responsivo e Interfaz Moderna (`MainLayout`)
- Layout adaptable *mobile-first* implementado en [MainLayout.vue](src/layouts/MainLayout.vue) y estado global en [ui.js](src/stores/ui.js).
- Barra lateral colapsable en escritorio (`lg:w-64` ↔ `lg:w-20`) y cajón lateral deslizable (*drawer*) con fondo difuminado en móviles y tabletas.
- Vistas optimizadas con doble presentación: tablas interactivas en pantallas grandes y tarjetas resumidas en dispositivos móviles.

### 2. Nuevo Módulo de Préstamos (`/loans`)
- Permite a usuarios autenticados (`$_SESSION['userId']`) emitir préstamos registrados bajo su usuario emisor (`issued_by`) con generación automática de código único (`PREST-0001`, `PREST-0002`, etc.).
- **Interés opcional configurable por préstamo** mediante interruptor dedicado (`hasInterest`):
  - **Sin interés (`0%`)**: El cliente paga exactamente el capital prestado.
  - **Con interés**: Permite especificar tasa porcentual (`interestRate`), modalidad de cálculo (**porcentaje único** `flat` sobre capital o **mensual simple** `monthly` = tasa × plazo) y plazo en meses/cuotas (`termMonths`), mostrando un **resumen financiero en tiempo real** antes de guardar.
- Funcionalidades completas:
  - Asociación opcional a una orden de venta existente (`order_id`).
  - Abono inicial opcional al momento de emitir el préstamo.
  - Registro transaccional de abonos parciales o liquidación total (`loan_payments`) en [LoanPaymentModal.vue](src/components/loans/LoanPaymentModal.vue).
  - Vista de detalle con historial de pagos, barra de progreso y **comprobante imprimible** en [LoanDetailModal.vue](src/components/loans/LoanDetailModal.vue).
  - Tarjetas KPI (capital prestado, intereses generados, saldo por recuperar, préstamos activos/vencidos/liquidados) y detección automática de mora por fecha de vencimiento (`loan_status = 4`).

### 3. Gestión de Inventario, Órdenes y Reportes
- **Fabricantes (`/brands`)** y **Categorías (`/categories`)**: CRUD completo con búsqueda instantánea, filtros por disponibilidad y borrado lógico (`brand_status = 2`, `categories_status = 2`).
- **Productos (`/products`)**: CRUD completo con vista de imagen, validación segura de carga de archivos, edición independiente de fotografía (`editProductImage.php`), alertas de stock bajo (`<= 3` unidades) y filtro por estado.
- **Órdenes / POS (`/orders`, `/orders/create`, `/orders/:id/edit`)**:
  - Agregado dinámico de múltiples ítems con precios tomados desde base de datos.
  - Cálculo automático de Subtotal, IVA (`13%`), Descuento, Total y Saldo Pendiente.
  - Descuento y reposición **atómica/transaccional** del inventario (`BEGIN TRANSACTION` + `FOR UPDATE`) al crear, editar o anular una orden.
  - Edición rápida de abonos (`editPayment.php`) y comprobante imprimible sanitizado contra XSS.
- **Reportes (`/reports`)**: Consolidado imprimible por rango de fechas que cruza órdenes y préstamos emitidos con totales recaudados y saldos pendientes.

### 4. Páginas de Error HTTP (`400`–`504`)
- Manejador centralizado en SPA ([ErrorView.vue](src/views/ErrorView.vue), [httpErrors.js](src/constants/httpErrors.js), [router/index.js](src/router/index.js), [client.js](src/api/client.js)) y backend/Apache ([http_error.php](php_action/http_error.php), [error.php](php_action/error.php), [.htaccess](.htaccess)).
- Cobertura dedicada para: **`400`**, **`401`**, **`403`**, **`404`**, **`405`**, **`408`**, **`422`**, **`429`**, **`500`**, **`501`**, **`502`**, **`503`**, **`504`**.

### 5. Arquitectura SPA y Limpieza de Vistas `.php`
- Migración completa a rutas del lado cliente con `createWebHistory()` en Vue Router.
- Se eliminaron todas las vistas e includes `.php` legadas (`index.php`, `dashboard.php`, `brand.php`, `categories.php`, `product.php`, `orders.php`, `report.php`, `setting.php`, `logout.php`, `includes/*` y `modal/*`), conservando únicamente los endpoints API en `php_action/` y las imágenes en `assests/images/`.

---

## Stack Tecnológico

| Capa | Tecnología | Versión / Detalle |
| :--- | :--- | :--- |
| **Frontend Framework** | Vue 3 (`<script setup>`) | `^3.5.45` |
| **Estado Global** | Pinia | `^4.1.0` |
| **Enrutamiento SPA** | Vue Router | `^5.5.0` |
| **Estilos UI** | Tailwind CSS + `@tailwindcss/vite` | `^4.3.5` |
| **Cliente HTTP** | Axios (con interceptores CSRF y HTTP Errors) | `^1.21.1` |
| **Iconografía** | `@lucide/vue` | `^1.0.0` |
| **Bundler** | Vite + `@vitejs/plugin-vue` | `^8.3.7` / `^6.0.10` |
| **Backend API** | PHP (API JSON + Sesiones + `mysqli`) | PHP 8.1+ (probado en 8.3) |
| **Base de Datos** | MySQL / MariaDB (`utf8mb4` en tablas nuevas) | 5.7+ / MariaDB 10.4+ |
| **Servidor Web** | Apache (`mod_rewrite`, `mod_headers`) o servidor embebido PHP | `.htaccess` incluido |

---

## Estructura del Proyecto

```text
stock-bartolo/
├── index.html                   # Punto de entrada HTML de la SPA (con favicon SVG integrado)
├── package.json                 # Scripts y dependencias NPM
├── vite.config.js               # Configuración Vite, alias '@' y proxy hacia php_action y assests
├── .htaccess                    # Reescritura SPA, cabeceras de seguridad, CSP y ErrorDocument
├── pos_stock.sql                # Esquema completo MySQL (9 tablas + datos iniciales)
├── README.md                    # Documentación general del proyecto
├── PDR.md                       # Documento de Diseño Preliminar, Auditoría y Seguridad
├── assests/
│   └── images/
│       ├── .htaccess            # Bloqueo de listado y ejecución PHP/CGI en carpeta de subidas
│       ├── photo_default.png    # Imagen por defecto de productos
│       └── stock/               # Imágenes subidas de productos
├── php_action/                  # Backend API JSON en PHP 8
│   ├── core.php                 # Bootstrap de sesión, auth, CSRF, cabeceras y helpers JSON
│   ├── security.php             # CSP, sanitización XSS/código malicioso, CSRF, rate limit, uploads
│   ├── http_error.php           # Respuestas estandarizadas JSON/HTML para errores HTTP 400-504
│   ├── error.php                # Controlador ErrorDocument para Apache
│   ├── db_connect.php           # Conexión MySQLi vía variables de entorno o defaults locales
│   ├── ensure_schema.php        # Creación idempotente de tablas loans, loan_payments y login_attempts
│   ├── csrfToken.php            # Emisor público GET de token/cookie CSRF
│   ├── login.php / logout.php / checkAuth.php
│   ├── changeUsername.php / changePassword.php
│   ├── fetchDashboard.php / getOrderReport.php
│   ├── createBrand.php / fetchBrand.php / fetchSelectedBrand.php / editBrand.php / removeBrand.php
│   ├── createCategories.php / fetchCategories.php / fetchSelectedCategories.php / editCategories.php / removeCategories.php
│   ├── createProduct.php / fetchProduct.php / fetchProductData.php / fetchSelectedProduct.php / fetchProductImageUrl.php / editProduct.php / editProductImage.php / removeProduct.php
│   ├── createOrder.php / fetchOrder.php / fetchOrderData.php / editOrder.php / editPayment.php / removeOrder.php / printOrder.php
│   └── createLoan.php / fetchLoan.php / fetchLoanData.php / editLoan.php / addLoanPayment.php / removeLoan.php
└── src/
    ├── main.js                  # Inicialización Vue, Pinia, Router e interceptores Axios
    ├── App.vue                  # Raíz con RouterView y ToastContainer
    ├── style.css                # Tailwind CSS v4 + estilos de impresión
    ├── api/client.js            # Instancia Axios con auto-inyección CSRF y redirección de errores HTTP
    ├── constants/httpErrors.js  # Catálogo descriptivo de errores HTTP 400-504
    ├── layouts/MainLayout.vue   # Shell responsivo (sidebar + drawer móvil + navbar + breadcrumbs)
    ├── router/index.js          # Rutas protegidas, alias de errores HTTP y catch-all 404
    ├── stores/                  # 10 stores Pinia (auth, ui, error, dashboard, brands, categories, products, orders, loans, reports)
    ├── utils/
    │   ├── formatters.js        # Formateo de moneda, fechas y fechas ISO locales
    │   └── security.js          # escapeHtml(), detección de patrones peligrosos y lectura de cookies
    ├── components/
    │   ├── common/              # BaseModal, ConfirmModal, StatusBadge, ToastContainer
    │   └── loans/               # LoanFormModal, LoanPaymentModal, LoanDetailModal
    └── views/                   # 11 vistas SPA (Login, Dashboard, Brands, Categories, Products, OrdersList, OrderForm, Loans, Reports, Settings, ErrorView)
```

---

## Requisitos Previos

- **Node.js** `>= 20.x` y **npm** `>= 10.x`
- **PHP** `>= 8.1` con extensiones habilitadas:
  - `mysqli`
  - `fileinfo`
  - `mbstring`
  - `json`
- **MySQL** `>= 5.7` o **MariaDB** `>= 10.4`

---

## Instalación y Configuración

### 1. Instalar dependencias Node.js

```bash
npm install
```

### 2. Importar la base de datos

Importa [pos_stock.sql](pos_stock.sql) en tu servidor MySQL/MariaDB (crea la base `pos_stock` con todas las tablas, incluyendo `loans`, `loan_payments` y `login_attempts`):

```bash
mysql -u root -p < pos_stock.sql
```

> **Nota:** Adicionalmente, [ensure_schema.php](php_action/ensure_schema.php) crea de forma automática e idempotente las tablas `loans`, `loan_payments` y `login_attempts` si actualizas sobre una instalación previa que solo contaba con las tablas originales.

### 3. Configurar conexión a base de datos

[db_connect.php](php_action/db_connect.php) lee automáticamente las siguientes variables de entorno (con valores por defecto para entorno local si no están definidas):

| Variable | Descripción | Valor por defecto |
| :--- | :--- | :--- |
| `DB_HOST` | Host del servidor MySQL/MariaDB | `127.0.0.1` |
| `DB_USER` | Usuario de base de datos | `admin` |
| `DB_PASS` | Contraseña de base de datos | `011998$*` |
| `DB_NAME` | Nombre de la base de datos | `pos_stock` |
| `DB_PORT` | Puerto TCP de MySQL/MariaDB | `3306` |
| `VITE_API_TARGET` | Destino proxy de Vite en desarrollo | `http://127.0.0.1:8000` |

### 4. Permisos para subida de imágenes

Asegúrate de que el usuario del servidor web/PHP tenga permisos de lectura y escritura sobre `assests/images/stock/`:

```bash
chmod -R 775 assests/images/stock
```

---

## Ejecución en Desarrollo y Producción

### Modo Desarrollo (dos terminales)

1. **Terminal 1 — Backend API PHP**:
   ```bash
   npm run serve:api
   # Ejecuta: php -S 127.0.0.1:8000
   ```
2. **Terminal 2 — Servidor de desarrollo Vite**:
   ```bash
   npm run dev
   # Disponible en http://localhost:5173
   ```

Vite reenvía automáticamente las peticiones `/php_action/*` y `/assests/*` hacia `VITE_API_TARGET` (`http://127.0.0.1:8000`), manteniendo cookies de sesión y CSRF en el mismo origen.

### Compilación y Despliegue en Producción (Apache)

1. Genera los artefactos optimizados en `dist/`:
   ```bash
   npm run build
   ```
2. Puedes previsualizar el bundle compilado contra el backend PHP ejecutando `npm run serve:api` en una terminal y `npm run preview` (`http://localhost:4173`) en otra.
3. En un servidor **Apache**:
   - Apunta el `DocumentRoot` a la raíz del proyecto (el archivo [.htaccess](.htaccess) redirige `/` y rutas SPA hacia `dist/index.html`, sirve `/assets/*` desde `dist/assets/*`, expone `php_action/` e imágenes y bloquea archivos sensibles como `pos_stock.sql` y dotfiles).
   - Verifica que estén activos los módulos `mod_rewrite` y `mod_headers` y permitido `AllowOverride All`.

---

## Rutas de la Aplicación (SPA)

| Ruta | Vista | Acceso | Descripción |
| :--- | :--- | :--- | :--- |
| `/login` | `LoginView.vue` | Invitados (`guestOnly`) | Inicio de sesión con protección CSRF y bloqueo por intentos fallidos |
| `/dashboard` | `DashboardView.vue` | Autenticado | KPIs generales de inventario, ventas y resumen de préstamos |
| `/brands` | `BrandsView.vue` | Autenticado | Gestión de fabricantes |
| `/categories` | `CategoriesView.vue` | Autenticado | Gestión de categorías |
| `/products` | `ProductsView.vue` | Autenticado | Catálogo de productos e imágenes |
| `/orders` | `OrdersListView.vue` | Autenticado | Listado, pagos, impresión y eliminación de órdenes |
| `/orders/create` | `OrderFormView.vue` | Autenticado | Creación de orden multi-producto con cálculo de IVA y descuento |
| `/orders/:id/edit` | `OrderFormView.vue` | Autenticado | Edición de orden existente con ajuste de stock |
| `/loans` | `LoansView.vue` | Autenticado | Emisión de préstamos (con/sin interés), abonos e historial |
| `/reports` | `ReportsView.vue` | Autenticado | Reportes imprimibles por rango de fechas (órdenes y préstamos) |
| `/settings` | `SettingsView.vue` | Autenticado | Cambio de nombre de usuario y contraseña |
| `/error/:code` | `ErrorView.vue` | Pública | Página dinámica para códigos HTTP (`400`, `403`, `404`, `500`, `501`, `503`, etc.) |
| `/:code` (alias `400..504`) | `ErrorView.vue` | Pública | Accesos directos (`/404`, `/500`, `/403`, `/503`, etc.) |
| `/:pathMatch(.*)*` | `ErrorView.vue` | Pública | Captura de rutas inexistentes (renderiza HTTP 404) |

---

## Referencia de Endpoints API

Todas las rutas mutantes (`POST`, `PUT`, `DELETE`) exigen sesión autenticada (salvo `login.php` y `logout.php`) y cabecera `X-CSRF-Token` válida:

| Módulo | Endpoint | Método | Función principal |
| :--- | :--- | :--- | :--- |
| **Seguridad / Auth** | `/php_action/csrfToken.php` | `GET` | Inicializa sesión y emite cookie `XSRF-TOKEN` + JSON `csrfToken` |
| | `/php_action/checkAuth.php` | `GET` | Consulta estado de sesión actual y sincroniza token CSRF |
| | `/php_action/login.php` | `POST` | Autentica usuario, aplica rate limiting (`429`) y rota CSRF |
| | `/php_action/logout.php` | `POST` | Destruye sesión, expira cookies de sesión y `XSRF-TOKEN` |
| **Dashboard** | `/php_action/fetchDashboard.php` | `GET` | Métricas agregadas de productos, órdenes, stock bajo y préstamos |
| **Fabricantes** | `/php_action/fetchBrand.php` | `GET` | Lista fabricantes activos |
| | `/php_action/fetchSelectedBrand.php` | `GET`/`POST` | Obtiene detalle de un fabricante por `brandId` |
| | `/php_action/createBrand.php` | `POST` | Crea nuevo fabricante |
| | `/php_action/editBrand.php` | `POST`/`PUT` | Actualiza fabricante |
| | `/php_action/removeBrand.php` | `POST`/`DELETE` | Desactiva fabricante (`brand_status = 2`) |
| **Categorías** | `/php_action/fetchCategories.php` | `GET` | Lista categorías activas |
| | `/php_action/fetchSelectedCategories.php` | `GET`/`POST` | Obtiene detalle de una categoría |
| | `/php_action/createCategories.php` | `POST` | Crea categoría |
| | `/php_action/editCategories.php` | `POST`/`PUT` | Edita categoría |
| | `/php_action/removeCategories.php` | `POST`/`DELETE` | Desactiva categoría (`categories_status = 2`) |
| **Productos** | `/php_action/fetchProduct.php` | `GET` | Lista catálogo completo con fabricante y categoría |
| | `/php_action/fetchProductData.php` | `GET` | Lista productos para selects en órdenes (`?all=1` incluye sin stock) |
| | `/php_action/fetchSelectedProduct.php` | `GET`/`POST` | Obtiene un producto por `productId` |
| | `/php_action/fetchProductImageUrl.php` | `GET`/`POST` | Devuelve ruta normalizada de imagen de producto |
| | `/php_action/createProduct.php` | `POST` | Crea producto con imagen validada (`multipart/form-data`) |
| | `/php_action/editProduct.php` | `POST`/`PUT` | Actualiza datos, precio, stock y estado de producto |
| | `/php_action/editProductImage.php` | `POST` | Reemplaza la fotografía de producto |
| | `/php_action/removeProduct.php` | `POST`/`DELETE` | Elimina lógicamente producto (`status = 2`) |
| **Órdenes** | `/php_action/fetchOrder.php` | `GET` | Lista órdenes activas con conteo de ítems |
| | `/php_action/fetchOrderData.php` | `GET`/`POST` | Obtiene orden y sus `order_item` |
| | `/php_action/printOrder.php` | `GET`/`POST` | Obtiene datos completos para comprobante |
| | `/php_action/createOrder.php` | `POST` | Crea orden transaccional descontando inventario |
| | `/php_action/editOrder.php` | `POST`/`PUT` | Actualiza orden reponiendo y re-descontando stock |
| | `/php_action/editPayment.php` | `POST`/`PUT` | Actualiza monto pagado, tipo y estado de pago |
| | `/php_action/removeOrder.php` | `POST`/`DELETE` | Anula orden (`order_status = 2`) y restaura stock |
| **Préstamos** | `/php_action/fetchLoan.php` | `GET` | Lista préstamos activos y resumen KPI de cartera |
| | `/php_action/fetchLoanData.php` | `GET`/`POST` | Detalle de un préstamo e historial de abonos (`loan_payments`) |
| | `/php_action/createLoan.php` | `POST` | Emite préstamo (con o sin interés) y abono inicial opcional |
| | `/php_action/editLoan.php` | `POST`/`PUT` | Modifica condiciones/tasa/monto recalculando saldo pendiente |
| | `/php_action/addLoanPayment.php` | `POST` | Registra abono transaccional y liquida al llegar saldo a `0` |
| | `/php_action/removeLoan.php` | `POST`/`DELETE` | Borra lógicamente préstamo (`record_status = 2`) |
| **Reportes y Config.** | `/php_action/getOrderReport.php` | `GET`/`POST` | Reporte filtrado por rango de fechas (`startDate`, `endDate`) |
| | `/php_action/changeUsername.php` | `POST`/`PUT` | Cambia el nombre de usuario de la cuenta activa |
| | `/php_action/changePassword.php` | `POST`/`PUT` | Cambia contraseña (`password_hash`) y rota sesión/CSRF |
| **Errores HTTP** | `/php_action/error.php` | `GET` | Renderiza respuesta JSON o HTML según cabecera `Accept` y código `?code=` |

---

## Medidas de Seguridad Implementadas

Un desglose detallado de amenazas y controles se encuentra en [PDR.md](PDR.md). En resumen, el sistema implementa:

- **Protección XSS (Cross-Site Scripting)**: Escape declarativo en plantillas Vue, utilitario `escapeHtml()` en ventanas de impresión (`window.open`), limpieza `strip_tags()` en backend, bloqueo de patrones peligrosos y Content Security Policy (`CSP`).
- **Protección CSRF (Cross-Site Request Forgery)**: Patrón Synchronizer Token + Cookie `XSRF-TOKEN` (`SameSite=Lax`) verificado con `hash_equals()` en todas las peticiones mutantes y rotado tras login y cambio de contraseña.
- **Rate Limiting en Login (Fuerza Bruta)**: Registro persistente en tabla `login_attempts`; tras **5 intentos fallidos** por cuenta/IP (o 15 por IP en 15 minutos), bloquea el acceso devolviendo **HTTP 429** con cabecera `Retry-After`.
- **Bloqueo de Inserción de Código Malicioso**: Filtro recursivo tanto en cliente ([security.js](src/utils/security.js)) como en servidor (`inspect_request_payload()` en [security.php](php_action/security.php)) contra etiquetas `<script>`, `<?php`, `<iframe>`/`<svg on*=...>`, URI `javascript:`/`vbscript:`/`data:text/html`, event handlers inline, bytes nulos y patrones `UNION SELECT`, `INTO OUTFILE` y `SLEEP()`.
- **Prevención de SQL Injection**: Todas las consultas con entrada externa utilizan sentencias preparadas (`mysqli::prepare` + `bind_param`).
- **Endurecimiento de Subidas y Sesiones**: Validación MIME real con `finfo`, comprobación dimensional `getimagesize()`, nombres aleatorios `bin2hex(random_bytes(16))`, desactivación de ejecución PHP en `assests/images/.htaccess`, cookies `HttpOnly`, timeout de inactividad (`7200` s) y actualización automática de hashes MD5 heredados hacia `password_hash(PASSWORD_DEFAULT)`.

---

## Credenciales Iniciales

Tras importar [pos_stock.sql](pos_stock.sql), la cuenta administrativa por defecto es:

- **Usuario:** `admin`
- **Contraseña:** `admin`

> **Recomendación:** Cambia la contraseña desde **Configuración (`/settings`)** inmediatamente después del primer inicio de sesión. En el primer ingreso válido, el sistema actualiza automáticamente el hash heredado en base de datos hacia `bcrypt` / `PASSWORD_DEFAULT`.

