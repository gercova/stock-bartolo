# Stock Bartolo — Sistema Multi-Sucursal de Inventario, POS, Caja y Préstamos

Aplicación web moderna de **página única (SPA)** para la administración integral multi-sucursal (*multi-tenant*) de inventario, punto de venta (órdenes), **Caja General independiente por sucursal**, **control de Caja Registradora (apertura, movimientos, arqueo y cierre Corte Z)**, reportes y **gestión de préstamos a clientes con interés opcional**. Construida sobre **Vue 3 + Pinia + Vue Router 5 + Tailwind CSS v4**, respaldada por una API REST JSON en **PHP 8** (`65` endpoints/módulos) y base de datos **MySQL / MariaDB** (`17` tablas) con endurecimiento de seguridad contra XSS, CSRF, fuerza bruta, colisiones de concurrencia e inyección SQL.

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

### 1. Arquitectura Multi-Sucursal (Multi-Tenant) e Interface Responsiva (`MainLayout`)
- Layout adaptable *mobile-first* implementado en [MainLayout.vue](src/layouts/MainLayout.vue) y estado global en [ui.js](src/stores/ui.js).
- **Selector rápido de sucursal activa** en la barra superior (`switchBranch.php`) con cambio en vivo y remonte reactivo de rutas por sucursal (`branch_id`).
- Aislamiento estricto por `branch_id` en inventario (`brands`, `categories`, `product`), ventas (`orders`), préstamos (`loans`, `loan_payments`), bóveda de `Caja General` (`general_cash_accounts`, `general_cash_movements`), turnos de `Caja` (`cash_registers`, `cash_sessions`, `cash_movements`, `cash_reconciliations`) y tablero KPI (`fetchDashboard.php`).
- Cada sucursal mantiene parámetros independientes de tasa fiscal (`tax_rate`), moneda (`currency_code`, `currency_symbol`), datos de contacto y capital operativo.
- Barra lateral colapsable en escritorio (`lg:w-64` ↔ `lg:w-20`), cajón lateral deslizable (*drawer*) accesible en móviles (`invisible lg:visible`) y doble presentación tabla/tarjetas.

### 2. Configuración del Sistema y Caja General Multi-Sucursal (`/system-config`)
- Módulo centralizado de 3 pestañas en [SystemConfigView.vue](src/views/SystemConfigView.vue) y [systemConfig.js](src/stores/systemConfig.js):
  1. **Caja General Independiente por Sucursal**:
     - Panel selector de bóvedas que muestra saldo disponible, alertas de liquidez mínima y número de cajas abiertas por sucursal.
     - CRUD completo sobre la cuenta (`general_cash_accounts`) y su libro mayor (`general_cash_movements`): **capital inicial / aportes**, **retiros**, **ajustes positivos/negativos**, edición de concepto/referencia de movimientos manuales, anulación con recálculo atómico de saldo y configuración de saldo mínimo de alerta / estado.
     - Registro automático e inmutable de salidas por **fondeo de apertura de caja** (`till_open_allocation`) y entradas por **entrega al cerrar turno** (`till_close_return`) o **retiros parciales desde gaveta**.
  2. **Sucursales (CRUD Multi-Tenant)**:
     - Creación, edición, conmutación activa y borrado lógico protegido (impide eliminar la sucursal principal o sucursales con cajas abiertas).
     - Al crear una sucursal, el backend provisiona atómicamente su **Caja General independiente** (con capital inicial opcional) y su caja registradora inicial (`CAJA-01`).
  3. **Parámetros y Políticas Operativas**:
     - Identidad corporativa, pie de comprobante, valores predeterminados fiscales/monetarios y reglas operativas: exigir turno de caja abierto para cobros en efectivo (`enforce_open_till_for_cash`), fondeo automático en apertura, entrega sugerida al cerrar caja y visibilidad del conmutador multi-sucursal.

### 3. Módulo de Caja / Turno de Caja (`/cash-register`)
- Ciclo operativo completo en [CashRegisterView.vue](src/views/CashRegisterView.vue) y [cashRegister.js](src/stores/cashRegister.js):
  - **CRUD de Cajas Registradoras** (`cash_registers`): gestión de múltiples terminales (`CAJA-01`, `CAJA-02`, etc.) por sucursal.
  - **Apertura de Caja ([OpenCashModal.vue](src/components/cash/OpenCashModal.vue))**: asigna fondo inicial con deducción atómica opcional desde la `Caja General` de la sucursal activa (`FOR UPDATE`). Impide doble apertura simultánea de la misma caja.
  - **Operación en Vivo y Movimientos**:
    - Descuento/abono automático del efectivo esperado en gaveta al emitir o abonar **órdenes en efectivo**, al **desembolsar préstamos en efectivo** (`loan_disbursement`) o cobrar **cuotas de préstamo en efectivo** (`loan_payment_cash`), además de seguimiento informativo de cobros con tarjeta/transferencia/cheque.
    - Movimientos manuales en [CashMovementModal.vue](src/components/cash/CashMovementModal.vue): ingresos manuales, gastos menores, retiros manuales y traspasos parciales a Caja General (`sweep_to_general`).
  - **Arqueo de Caja ([ReconcileCashModal.vue](src/components/cash/ReconcileCashModal.vue))**:
    - Contador interactivo de **12 denominaciones** (billetes y monedas USD desde `$100` hasta `$0.01`), ingreso de montos declarados en tarjeta/transferencia/cheque, cálculo en tiempo real de sobrante/faltante/cuadre exacto e historial persistente en `cash_reconciliations`.
  - **Cierre de Caja / Corte Z ([CloseCashModal.vue](src/components/cash/CloseCashModal.vue)) y Detalle / Corte X ([CashSessionDetailModal.vue](src/components/cash/CashSessionDetailModal.vue))**:
    - Cierre transaccional con arqueo final, transferencia configurable de efectivo hacia la `Caja General` de la sucursal (con cálculo automático de fondo retenido en gaveta) y **reporte imprimible Corte X / Corte Z** sanitizado contra XSS.

### 4. Módulo de Préstamos (`/loans`)
- Permite a usuarios autenticados (`$_SESSION['userId']`) emitir préstamos dentro de la sucursal activa con código único (`PREST-0001`, `PREST-0002`, etc.).
- **Interés opcional configurable por préstamo** mediante interruptor dedicado (`hasInterest`):
  - **Sin interés (`0%`)**: El cliente paga exactamente el capital prestado.
  - **Con interés**: Permite especificar tasa porcentual (`interestRate`), modalidad (**porcentaje único** `flat` o **mensual simple** `monthly`) y plazo en meses/cuotas (`termMonths`) con resumen financiero en vivo.
- Asociación opcional a una orden activa de la misma sucursal, abono inicial opcional, historial de pagos (`loan_payments`), integración automática con `Caja`, comprobante imprimible en [LoanDetailModal.vue](src/components/loans/LoanDetailModal.vue) y detección automática de mora (`loan_status = 4`).

### 5. Gestión de Inventario, Órdenes y Reportes
- **Fabricantes (`/brands`)** y **Categorías (`/categories`)**: CRUD por sucursal con búsqueda, filtros y borrado lógico.
- **Productos (`/products`)**: CRUD por sucursal con carga segura de imágenes (`editProductImage.php`) y alertas de stock bajo (`<= 3` unidades).
- **Órdenes / POS (`/orders`, `/orders/create`, `/orders/:id/edit`)**: cálculo con tasa de IVA y símbolo de moneda de la sucursal activa, control transaccional de stock (`FOR UPDATE`), verificación de caja abierta cuando `enforce_open_till_for_cash` está activo y ticket imprimible con cabecera de sucursal.
- **Reportes (`/reports`)**: Consolidado imprimible por rango de fechas dentro de la sucursal activa cruzando órdenes y préstamos.

### 6. Páginas de Error HTTP (`400`–`504`) y SPA Pura
- Manejador centralizado en SPA ([ErrorView.vue](src/views/ErrorView.vue), [httpErrors.js](src/constants/httpErrors.js)) y Apache ([http_error.php](php_action/http_error.php), [error.php](php_action/error.php), [.htaccess](.htaccess)) cubriendo **`400`, `401`, `403`, `404`, `405`, `408`, `422`, `429`, `500`, `501`, `502`, `503`, `504`**.

---

## Stack Tecnológico

| Capa | Tecnología | Versión / Detalle |
| :--- | :--- | :--- |
| **Frontend Framework** | Vue 3 (`<script setup>`) | `^3.5.45` |
| **Estado Global** | Pinia | `^4.1.0` |
| **Enrutamiento SPA** | Vue Router | `^5.5.0` |
| **Estilos UI** | Tailwind CSS + `@tailwindcss/vite` | `^4.3.5` |
| **Cliente HTTP** | Axios (CSRF + manejo HTTP Errors) | `^1.21.1` |
| **Iconografía** | `@lucide/vue` | `^1.0.0` |
| **Bundler** | Vite + `@vitejs/plugin-vue` | `^8.3.7` / `^6.0.10` |
| **Backend API** | PHP (API JSON + Sesiones + `mysqli`) | PHP 8.1+ (probado en 8.3) |
| **Base de Datos** | MySQL / MariaDB (`utf8mb4` en tablas nuevas/tenant) | 5.7+ / MariaDB 10.4+ (probado en 10.11.14) |
| **Servidor Web** | Apache (`mod_rewrite`, `mod_headers`) o PHP embebido | `.htaccess` incluido |

---

## Estructura del Proyecto

```text
stock-bartolo/
├── index.html                   # Entrada HTML SPA (con favicon SVG integrado)
├── package.json                 # Scripts y dependencias NPM
├── vite.config.js               # Alias '@', proxy /php_action y auto-inicio PHP en dev/preview
├── .htaccess                    # Reescritura SPA, CSP, cabeceras de seguridad y ErrorDocument
├── pos_stock.sql                # Esquema completo MySQL/MariaDB (17 tablas + datos iniciales)
├── README.md                    # Documentación general del proyecto
├── PDR.md                       # Documento de Diseño Preliminar, Auditoría y Seguridad
├── assests/
│   └── images/
│       ├── .htaccess            # Bloqueo de listado y ejecución PHP/CGI en subidas
│       ├── photo_default.png    # Imagen por defecto de productos
│       └── stock/               # Imágenes de productos
├── php_action/                  # Backend API JSON (65 archivos PHP)
│   ├── core.php                 # Sesión, auth, contexto multi-sucursal, CSRF y JSON helpers
│   ├── tenant.php               # Helpers multi-tenant, bóveda Caja General y saldo vivo de Caja
│   ├── security.php             # CSP, sanitización XSS/SQLi/código malicioso, CSRF, rate limit, uploads
│   ├── http_error.php           # Respuestas estandarizadas JSON/HTML (400-504)
│   ├── error.php                # Controlador ErrorDocument para Apache
│   ├── db_connect.php           # Conexión MySQLi vía variables de entorno o defaults locales
│   ├── ensure_schema.php        # Migración idempotente de las 17 tablas y seeds multi-sucursal
│   ├── csrfToken.php            # Emisor público GET de token/cookie CSRF
│   ├── login.php / logout.php / checkAuth.php
│   ├── changeUsername.php / changePassword.php
│   ├── fetchDashboard.php / getOrderReport.php
│   ├── fetchBranches.php / createBranch.php / editBranch.php / removeBranch.php / switchBranch.php
│   ├── fetchSystemConfig.php / saveSystemConfig.php
│   ├── fetchGeneralCash.php / manageGeneralCash.php
│   ├── fetchCashRegisters.php / createCashRegister.php / editCashRegister.php / removeCashRegister.php
│   ├── fetchCashSessions.php / fetchCashSessionDetail.php / openCashSession.php / addCashMovement.php / reconcileCashSession.php / closeCashSession.php
│   ├── *Brand.php (5) / *Categories.php (5) / *Product*.php (8) / *Order*.php / editPayment.php (7) / *Loan*.php (6)
└── src/
    ├── main.js                  # Inicialización Vue, Pinia, Router e interceptores Axios
    ├── App.vue                  # Raíz con RouterView y ToastContainer
    ├── style.css                # Tailwind CSS v4 + estilos de impresión
    ├── api/client.js            # Axios con CSRF automático, recarga en 401/403 y mapeo de errores HTTP
    ├── constants/httpErrors.js  # Catálogo descriptivo de errores HTTP 400-504
    ├── layouts/MainLayout.vue   # Shell responsivo multi-sucursal (sidebar + switcher + drawer móvil)
    ├── router/index.js          # Rutas protegidas SPA, alias de errores HTTP y catch-all 404
    ├── stores/                  # 12 stores Pinia (auth, ui, error, dashboard, brands, categories, products, orders, loans, reports, systemConfig, cashRegister)
    ├── utils/
    │   ├── formatters.js        # Moneda dinámica por sucursal, fechas y badges de movimientos/arqueos
    │   └── security.js          # escapeHtml(), detección de patrones peligrosos y lectura de cookies
    ├── components/
    │   ├── common/              # BaseModal, ConfirmModal, StatusBadge, ToastContainer
    │   ├── loans/               # LoanFormModal, LoanPaymentModal, LoanDetailModal
    │   ├── system/              # BranchFormModal, GeneralCashMovementModal, GeneralCashConfigModal
    │   └── cash/                # CashRegisterModal, OpenCashModal, CashMovementModal, ReconcileCashModal, CloseCashModal, CashSessionDetailModal
    └── views/                   # 13 vistas SPA (Login, Dashboard, Brands, Categories, Products, OrdersList, OrderForm, CashRegister, Loans, Reports, SystemConfig, Settings, ErrorView)
```

---

## Requisitos Previos

- **Node.js** `>= 20.x` y **npm** `>= 10.x`
- **PHP** `>= 8.1` con extensiones habilitadas: `mysqli`, `fileinfo`, `mbstring`, `json`
- **MySQL** `>= 5.7` o **MariaDB** `>= 10.4`

---

## Instalación y Configuración

### 1. Instalar dependencias Node.js

```bash
npm install
```

### 2. Importar la base de datos

Importa [pos_stock.sql](pos_stock.sql) en tu servidor MySQL/MariaDB (crea la base `pos_stock` con las `17` tablas, índices multi-tenant y datos iniciales de `SUC-001`, `CAJA-01` y `general_cash_accounts`):

```bash
mysql -u root -p < pos_stock.sql
```

> **Nota:** Adicionalmente, [ensure_schema.php](php_action/ensure_schema.php) crea y migra de forma automática e idempotente todas las columnas `branch_id` y nuevas tablas (`branches`, `system_settings`, `general_cash_accounts`, `general_cash_movements`, `cash_registers`, `cash_sessions`, `cash_movements`, `cash_reconciliations`, `loans`, `loan_payments`, `login_attempts`) si actualizas una base de datos previa.

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

```bash
chmod -R 775 assests/images/stock
```

---

## Ejecución en Desarrollo y Producción

### Modo Desarrollo (un solo comando)

```bash
npm run dev
# Inicia Vite en http://localhost:5173 y levanta automáticamente PHP en 127.0.0.1:8000
```

Vite verifica al iniciar si ya existe un servidor escuchando en `VITE_API_TARGET` (`http://127.0.0.1:8000` por defecto). Si no hay ninguno activo, inicia automáticamente `php -S 127.0.0.1:8000` (y lo detiene al cerrar Vite), evitando errores `502 Bad Gateway` en login o peticiones `/php_action/*`.

### Compilación y Despliegue en Producción (Apache)

1. Genera el bundle optimizado en `dist/`:
   ```bash
   npm run build
   ```
2. Previsualiza localmente el build si lo deseas con `npm run preview` (`http://localhost:4173`).
3. En **Apache**, apunta el `DocumentRoot` a la raíz del proyecto, habilita `mod_rewrite` + `mod_headers` y `AllowOverride All`. El archivo [.htaccess](.htaccess) sirve `dist/index.html`, reescribe rutas SPA y protege archivos internos.

---

## Rutas de la Aplicación (SPA)

| Ruta | Vista | Acceso | Descripción |
| :--- | :--- | :--- | :--- |
| `/login` | `LoginView.vue` | Invitados (`guestOnly`) | Inicio de sesión con CSRF y bloqueo por intentos fallidos |
| `/dashboard` | `DashboardView.vue` | Autenticado | KPIs de la sucursal activa, estado de `Caja General` y `Caja Registradora` |
| `/brands` | `BrandsView.vue` | Autenticado | Fabricantes de la sucursal activa |
| `/categories` | `CategoriesView.vue` | Autenticado | Categorías de la sucursal activa |
| `/products` | `ProductsView.vue` | Autenticado | Catálogo de productos e imágenes de la sucursal activa |
| `/orders` | `OrdersListView.vue` | Autenticado | Órdenes, abonos, comprobante de sucursal y anulación |
| `/orders/create` | `OrderFormView.vue` | Autenticado | Nueva orden con IVA y símbolo de moneda de la sucursal |
| `/orders/:id/edit` | `OrderFormView.vue` | Autenticado | Edición de orden existente con ajuste transaccional de stock |
| `/cash-register` | `CashRegisterView.vue` | Autenticado | Cajas registradoras, apertura, movimientos, arqueo (12 denoms) y cierre Corte Z |
| `/loans` | `LoansView.vue` | Autenticado | Emisión de préstamos (con/sin interés), abonos e impacto en caja |
| `/reports` | `ReportsView.vue` | Autenticado | Reportes imprimibles de órdenes y préstamos por sucursal |
| `/system-config` | `SystemConfigView.vue` | Autenticado | **Caja General multi-sucursal (CRUD), Sucursales (CRUD) y Políticas del Sistema** |
| `/settings` | `SettingsView.vue` | Autenticado | Cuenta de usuario, cambio de contraseña y acceso rápido a Configuración del Sistema |
| `/error/:code` y `/:code` | `ErrorView.vue` | Pública | Páginas de error HTTP (`400`, `403`, `404`, `500`, `501`, `503`, etc.) |
| `/:pathMatch(.*)*` | `ErrorView.vue` | Pública | Captura de rutas inexistentes (HTTP 404) |

---

## Referencia de Endpoints API

Todas las rutas mutantes (`POST`, `PUT`, `DELETE`) exigen sesión autenticada (salvo `login.php` y `logout.php`) y cabecera `X-CSRF-Token` válida. Todos los módulos operativos filtran estrictamente por la sucursal activa (`$_SESSION['active_branch_id']`):

| Módulo | Endpoint | Método | Función principal |
| :--- | :--- | :--- | :--- |
| **Auth / Sesión** | `/php_action/csrfToken.php` | `GET` | Emite sesión, cookie `XSRF-TOKEN` y `csrfToken` |
| | `/php_action/checkAuth.php` | `GET` | Devuelve usuario, `csrfToken`, `activeBranch`, `branches` y `systemConfig` |
| | `/php_action/login.php` | `POST` | Autentica, inicializa sucursal activa, aplica rate limiting (`429`) y rota CSRF |
| | `/php_action/logout.php` | `POST` | Destruye sesión y cookies |
| **Dashboard / Config.** | `/php_action/fetchDashboard.php` | `GET` | KPIs multi-sucursal + saldos en vivo de `Caja General` y turno abierto |
| | `/php_action/fetchSystemConfig.php` | `GET` | Devuelve parámetros globales, sucursal activa y estadísticas |
| | `/php_action/saveSystemConfig.php` | `POST`/`PUT` | Actualiza identidad corporativa, defaults fiscales y políticas de caja |
| **Sucursales** | `/php_action/fetchBranches.php` | `GET` | Lista sucursales con saldos de Caja General, cajas abiertas y conteos |
| | `/php_action/createBranch.php` | `POST` | Crea sucursal + Caja General independiente + `CAJA-01` transaccionalmente |
| | `/php_action/editBranch.php` | `POST`/`PUT` | Actualiza datos, IVA, moneda y estado de sucursal |
| | `/php_action/removeBranch.php` | `POST`/`DELETE` | Elimina lógicamente sucursal secundaria sin cajas abiertas |
| | `/php_action/switchBranch.php` | `POST` | Cambia `active_branch_id` de la sesión (`role === 'superadmin'` o sucursal asignada) |
| **Caja General** | `/php_action/fetchGeneralCash.php` | `GET` | Lista todas las bóvedas multi-sucursal + detalle/libro mayor de `?branchId=` |
| | `/php_action/manageGeneralCash.php` | `POST`/`PUT`/`DELETE` | CRUD de movimientos (`deposit`, `withdrawal`, `initial_capital`, `adjustment_in/out`), edición, anulación y configuración de bóveda |
| **Caja Registradora** | `/php_action/fetchCashRegisters.php` | `GET` | Lista terminales `cash_registers` de la sucursal activa |
| | `/php_action/createCashRegister.php` | `POST` | Crea terminal en la sucursal activa |
| | `/php_action/editCashRegister.php` | `POST`/`PUT` | Edita código/nombre/ubicación/estado de terminal |
| | `/php_action/removeCashRegister.php` | `POST`/`DELETE` | Desactiva terminal si no posee turno abierto |
| | `/php_action/fetchCashSessions.php` | `GET` | Lista turnos de caja de la sucursal activa + cajas + saldo disponible de Caja General |
| | `/php_action/fetchCashSessionDetail.php` | `GET` | Detalle completo de turno (`Corte X/Z`): movimientos y arqueos |
| | `/php_action/openCashSession.php` | `POST` | Abre turno con fondeo atómico opcional desde Caja General |
| | `/php_action/addCashMovement.php` | `POST` | Registra ingreso, gasto, retiro manual o traslado parcial hacia Caja General |
| | `/php_action/reconcileCashSession.php` | `POST` | Registra arqueo (con conteo por denominaciones) y actualiza discrepancia |
| | `/php_action/closeCashSession.php` | `POST` | Ejecuta cierre `Corte Z`, arqueo final y retorno opcional hacia Caja General |
| **Fabricantes** | `/php_action/*Brand*.php` | `GET`/`POST`/`PUT`/`DELETE` | `fetchBrand`, `fetchSelectedBrand`, `createBrand`, `editBrand`, `removeBrand` (por sucursal) |
| **Categorías** | `/php_action/*Categories*.php` | `GET`/`POST`/`PUT`/`DELETE` | `fetchCategories`, `fetchSelectedCategories`, `createCategories`, `editCategories`, `removeCategories` (por sucursal) |
| **Productos** | `/php_action/*Product*.php` | `GET`/`POST`/`PUT`/`DELETE` | `fetchProduct`, `fetchProductData`, `fetchSelectedProduct`, `fetchProductImageUrl`, `createProduct`, `editProduct`, `editProductImage`, `removeProduct` (por sucursal) |
| **Órdenes / POS** | `/php_action/*Order*.php`, `editPayment.php`, `printOrder.php` | `GET`/`POST`/`PUT`/`DELETE` | Gestión transaccional de ventas, stock e impacto automático en Caja abierta |
| **Préstamos** | `/php_action/*Loan*.php` | `GET`/`POST`/`PUT`/`DELETE` | `fetchLoan`, `fetchLoanData`, `createLoan`, `editLoan`, `addLoanPayment`, `removeLoan` + impacto en Caja abierta |
| **Reportes / Cuenta** | `getOrderReport.php`, `changeUsername.php`, `changePassword.php`, `error.php` | `GET`/`POST`/`PUT` | Reportes multi-sucursal, gestión de credenciales y respuestas HTTP |

---

## Medidas de Seguridad Implementadas

Un desglose detallado se encuentra en [PDR.md](PDR.md). En resumen:
- **Aislamiento Multi-Tenant (`tenant.php`)**: Todas las lecturas y mutaciones aplican filtrado estricto `branch_id = $_SESSION['active_branch_id']`; los accesos cruzados entre sucursales responden `404` / `403`.
- **Integridad Transaccional Financiera**: Todas las mutaciones de saldo en `Caja General`, apertura/arqueo/cierre de `Caja`, descuento de inventario y abonos emplean `begin_transaction()` + bloqueo de fila `SELECT ... FOR UPDATE`.
- **Protección XSS, CSRF, SQLi, Brute-Force y Carga Maliciosa**: Sanitización en [security.js](src/utils/security.js) y [security.php](php_action/security.php), `X-CSRF-Token` verificado con `hash_equals()`, rate limiting en `login_attempts` (`429`), 100% sentencias preparadas y validación MIME real con `finfo` + `getimagesize()`.

---

## Credenciales Iniciales

Tras importar [pos_stock.sql](pos_stock.sql), la cuenta administrativa (`role = 'superadmin'`, acceso a todas las sucursales) por defecto es:

- **Usuario:** `admin`
- **Contraseña:** `admin`

> **Recomendación:** Cambia la contraseña desde **Cuenta y Seguridad (`/settings`)** inmediatamente después del primer inicio de sesión.
