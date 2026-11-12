<?php

require_once __DIR__ . '/security.php';

function sb_get_security_modules(): array
{
    return [
        [
            'key' => 'dashboard',
            'name' => 'Panel de Inicio',
            'category' => 'General',
            'description' => 'Visualización de indicadores clave, métricas de inventario, ventas y cartera.',
        ],
        [
            'key' => 'brands',
            'name' => 'Fabricantes',
            'category' => 'Inventario',
            'description' => 'Administración y catálogo de marcas y fabricantes de productos.',
        ],
        [
            'key' => 'categories',
            'name' => 'Categorías',
            'category' => 'Inventario',
            'description' => 'Clasificación y agrupación de productos del inventario.',
        ],
        [
            'key' => 'products',
            'name' => 'Productos',
            'category' => 'Inventario',
            'description' => 'Gestión de artículos, precios, existencias, alertas de stock e imágenes.',
        ],
        [
            'key' => 'orders',
            'name' => 'Órdenes y Ventas (POS)',
            'category' => 'Ventas y Finanzas',
            'description' => 'Emisión de órdenes, punto de venta, cobros, comprobantes y anulaciones.',
        ],
        [
            'key' => 'cash_register',
            'name' => 'Caja Registradora',
            'category' => 'Ventas y Finanzas',
            'description' => 'Apertura de turno, movimientos de gaveta, arqueos de efectivo y cierre Corte Z.',
        ],
        [
            'key' => 'loans',
            'name' => 'Préstamos y Cartera',
            'category' => 'Ventas y Finanzas',
            'description' => 'Emisión de préstamos con/sin interés, control de cuotas y abonos a capital.',
        ],
        [
            'key' => 'reports',
            'name' => 'Reportes',
            'category' => 'Ventas y Finanzas',
            'description' => 'Consultas consolidadas e impresión de reportes de ventas y préstamos.',
        ],
        [
            'key' => 'general_cash',
            'name' => 'Caja General (Bóveda)',
            'category' => 'Sistema',
            'description' => 'Bóveda de capital operativo por sucursal, aportes, retiros y fondeos.',
        ],
        [
            'key' => 'branches',
            'name' => 'Sucursales (Multi-Tenant)',
            'category' => 'Sistema',
            'description' => 'Creación y administración de sucursales independientes y parámetros fiscales.',
        ],
        [
            'key' => 'system_config',
            'name' => 'Configuración del Sistema',
            'category' => 'Sistema',
            'description' => 'Parámetros corporativos, tickets y políticas operativas de caja y seguridad.',
        ],
        [
            'key' => 'users',
            'name' => 'Gestión de Usuarios',
            'category' => 'Seguridad',
            'description' => 'Creación, edición, activación y asignación de roles y sucursales a usuarios.',
        ],
        [
            'key' => 'roles',
            'name' => 'Roles y Permisos',
            'category' => 'Seguridad',
            'description' => 'Definición de roles y configuración de la matriz de permisos granular.',
        ],
        [
            'key' => 'audit_logs',
            'name' => 'Registro de Auditoría',
            'category' => 'Seguridad',
            'description' => 'Consulta inmutable del historial de eventos, accesos y operaciones críticas.',
        ],
    ];
}

function sb_normalize_permission_action(string $action): string
{
    $map = [
        'access' => 'access',
        'view' => 'access',
        'read' => 'access',
        'create' => 'create',
        'add' => 'create',
        'insert' => 'create',
        'update' => 'update',
        'edit' => 'update',
        'modify' => 'update',
        'delete' => 'delete',
        'remove' => 'delete',
        'destroy' => 'delete',
    ];
    $actionLower = strtolower(trim($action));
    return $map[$actionLower] ?? 'access';
}

function sb_get_user_permissions(mysqli $connect, int $userId): array
{
    if ($userId <= 0) {
        return [
            'is_superadmin' => false,
            'role_id' => null,
            'role_name' => 'Invitado',
            'role_slug' => 'guest',
            'modules' => [],
        ];
    }

    $stmt = $connect->prepare('SELECT u.user_id, u.username, u.role, u.role_id, u.active, r.role_name, r.role_slug, r.is_system FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? LIMIT 1');
    if (!$stmt) {
        return [
            'is_superadmin' => false,
            'role_id' => null,
            'role_name' => 'Desconocido',
            'role_slug' => 'unknown',
            'modules' => [],
        ];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$userRow) {
        return [
            'is_superadmin' => false,
            'role_id' => null,
            'role_name' => 'Invalido',
            'role_slug' => 'invalid',
            'modules' => [],
        ];
    }

    $roleSlug = (string)($userRow['role_slug'] ?? $userRow['role'] ?? 'cashier');
    $roleName = (string)($userRow['role_name'] ?? ucfirst($roleSlug));
    $roleId = $userRow['role_id'] ? (int)$userRow['role_id'] : null;
    $isSuperadmin = ($roleSlug === 'superadmin' || (string)($userRow['role'] ?? '') === 'superadmin');

    $allModules = sb_get_security_modules();
    $modulesMap = [];

    if ($isSuperadmin) {
        foreach ($allModules as $mod) {
            $modulesMap[$mod['key']] = [
                'can_access' => true,
                'can_create' => true,
                'can_update' => true,
                'can_delete' => true,
            ];
        }
        return [
            'is_superadmin' => true,
            'role_id' => $roleId,
            'role_name' => $roleName ?: 'Superadministrador',
            'role_slug' => 'superadmin',
            'modules' => $modulesMap,
        ];
    }

    // Default false for all modules
    foreach ($allModules as $mod) {
        $modulesMap[$mod['key']] = [
            'can_access' => false,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
        ];
    }

    if ($roleId) {
        $permStmt = $connect->prepare('SELECT module, can_access, can_create, can_update, can_delete FROM role_permissions WHERE role_id = ?');
        if ($permStmt) {
            $permStmt->bind_param('i', $roleId);
            $permStmt->execute();
            $permRes = $permStmt->get_result();
            while ($p = $permRes->fetch_assoc()) {
                $modKey = (string)$p['module'];
                $modulesMap[$modKey] = [
                    'can_access' => ((int)$p['can_access']) === 1,
                    'can_create' => ((int)$p['can_create']) === 1,
                    'can_update' => ((int)$p['can_update']) === 1,
                    'can_delete' => ((int)$p['can_delete']) === 1,
                ];
            }
            $permStmt->close();
        }
    }

    return [
        'is_superadmin' => false,
        'role_id' => $roleId,
        'role_name' => $roleName,
        'role_slug' => $roleSlug,
        'modules' => $modulesMap,
    ];
}

function sb_has_permission(mysqli $connect, int $userId, string $module, string $action): bool
{
    if ($userId <= 0) {
        return false;
    }

    $perms = sb_get_user_permissions($connect, $userId);
    if (!empty($perms['is_superadmin'])) {
        return true;
    }

    $cleanAction = sb_normalize_permission_action($action);
    $prop = 'can_' . $cleanAction;

    return !empty($perms['modules'][$module][$prop]);
}

function sb_assert_permission(mysqli $connect, string $module, string $action): void
{
    if (empty($_SESSION['userId'])) {
        send_http_error(401, 'Debes iniciar sesión para realizar esta acción.');
    }

    $userId = (int)$_SESSION['userId'];
    if (!sb_has_permission($connect, $userId, $module, $action)) {
        $actLabel = match ($action) {
            'create' => 'crear registros',
            'update' => 'modificar registros',
            'delete' => 'eliminar registros',
            default => 'acceder a la información',
        };

        sb_log_audit(
            $connect,
            'security',
            'ACCESS_DENIED',
            "Intento de acceso denegado: acción '{$action}' en módulo '{$module}'",
            ['module' => $module, 'action' => $action, 'user_id' => $userId]
        );

        send_http_error(
            403,
            "Acceso denegado: no dispones de permisos para {$actLabel} en el módulo de {$module}."
        );
    }
}

function sb_log_audit(
    mysqli $connect,
    string $module,
    string $action,
    string $description,
    ?array $details = null,
    ?int $branchId = null,
    ?int $userId = null,
    ?string $username = null
): bool {
    try {
        $finalUserId = $userId;
        if ($finalUserId === null && !empty($_SESSION['userId'])) {
            $finalUserId = (int)$_SESSION['userId'];
        }

        $finalUsername = $username;
        if ($finalUsername === null) {
            $finalUsername = !empty($_SESSION['username']) ? (string)$_SESSION['username'] : ($finalUserId ? "user#{$finalUserId}" : 'sistema');
        }

        $finalBranchId = $branchId;
        if ($finalBranchId === null && !empty($_SESSION['active_branch_id'])) {
            $finalBranchId = (int)$_SESSION['active_branch_id'];
        }

        $ip = get_client_ip();
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 255);
        $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        $cleanDesc = substr(trim($description), 0, 255);
        $cleanModule = substr(trim($module), 0, 50);
        $cleanAction = strtoupper(substr(trim($action), 0, 50));

        $stmt = $connect->prepare('INSERT INTO audit_logs (user_id, username, branch_id, module, action, description, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isissssss', $finalUserId, $finalUsername, $finalBranchId, $cleanModule, $cleanAction, $cleanDesc, $ip, $ua, $detailsJson);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    } catch (Throwable $e) {
        error_log('[stock-bartolo] sb_log_audit error: ' . $e->getMessage());
        return false;
    }
}

