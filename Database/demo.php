<?php
/**
 * Datos de demostración (OPCIONAL): ventas de los últimos 21 días, asistencia
 * con algunas tardanzas/faltas y un servicio en curso (mesas ocupadas,
 * llamados y comandas en cocina).
 *
 *   C:\xampp\php\php.exe Database\demo.php
 *
 * Correr después de schema.sql + seed.sql. No usar en producción.
 */
require __DIR__ . '/../Backend/autoload.php';

use App\Utils\Database;

date_default_timezone_set('America/Guatemala');
mt_srand(2026);
$pdo = Database::getInstance()->getConnection();

$productos = $pdo->query("SELECT p.*, c.nombre AS categoria FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id WHERE p.activo = 1")->fetchAll();
$mesas = $pdo->query("SELECT id FROM mesas")->fetchAll(PDO::FETCH_COLUMN);
$meseros = [2, 5, null];
$hoy = date('Y-m-d');

$insCuenta = $pdo->prepare("INSERT INTO cuentas (mesa_id, mesero_id, turno_caja_id, personas, estado, subtotal, descuento, pagado, propina, abierta_en, cerrada_en) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$insComanda = $pdo->prepare("INSERT INTO comandas (cuenta_id, origen, creado_por, estado, creado_en, iniciado_en, listo_en, entregado_en) VALUES (?,?,?,?,?,?,?,?)");
$insItem = $pdo->prepare("INSERT INTO comanda_items (comanda_id, cuenta_id, producto_id, nombre, categoria, precio, costo, cantidad, notas) VALUES (?,?,?,?,?,?,?,?,?)");
$insPago = $pdo->prepare("INSERT INTO pagos (cuenta_id, turno_caja_id, cobrado_por, monto, propina, metodo, etiqueta, creado_en) VALUES (?,?,?,?,?,?,?,?)");
$insTurnoCaja = $pdo->prepare("INSERT INTO turnos_caja (abierto_por, cerrado_por, monto_inicial, efectivo_esperado, efectivo_contado, diferencia, estado, abierto_en, cerrado_en) VALUES (1,1,500,?,?,0,'cerrado',?,?)");
$insMarca = $pdo->prepare("INSERT INTO marcaciones (empleado_id, tipo, marcado_en, origen) VALUES (?,?,?,'kiosco')");

$pdo->beginTransaction();

// ── Ventas históricas ────────────────────────────────────────────────────
for ($d = 21; $d >= 1; $d--) {
    $fecha = date('Y-m-d', strtotime("-$d days"));
    $finde = in_array((int)date('N', strtotime($fecha)), [5, 6, 7], true);
    $insTurnoCaja->execute([0, 0, "$fecha 06:45:00", "$fecha 23:10:00"]);
    $turno = (int)$pdo->lastInsertId();
    $efectivo = 500;
    $n = mt_rand($finde ? 22 : 12, $finde ? 34 : 22);
    for ($i = 0; $i < $n; $i++) {
        $h = [7, 8, 8, 9, 9, 10, 12, 13, 13, 14, 15, 18, 19, 19, 20, 21][mt_rand(0, 15)];
        $abre = sprintf('%s %02d:%02d:00', $fecha, $h, mt_rand(0, 59));
        $cierra = date('Y-m-d H:i:s', strtotime($abre) + mt_rand(35, 95) * 60);
        $mesero = $meseros[mt_rand(0, 2)];
        $personas = mt_rand(1, 5);
        $insCuenta->execute([$mesas[array_rand($mesas)], $mesero, $turno, $personas, 'pagada', 0, 0, 0, 0, $abre, $cierra]);
        $cid = (int)$pdo->lastInsertId();
        $insComanda->execute([$cid, $mesero ? 'mesero' : 'cliente', $mesero, 'entregado', $abre, $abre, $abre, $abre]);
        $com = (int)$pdo->lastInsertId();
        $sub = 0;
        $lineas = mt_rand(1, min(5, $personas + 2));
        for ($k = 0; $k < $lineas; $k++) {
            // Desayunos y café en la mañana, platos fuertes en la tarde
            $pool = array_values(array_filter($productos, fn($p) => $h < 11 ? in_array($p['categoria'], ['Desayunos', 'Cafetería', 'Bebidas'], true) : $p['categoria'] !== 'Desayunos'));
            $p = $pool[array_rand($pool)];
            $c = mt_rand(1, 2);
            $insItem->execute([$com, $cid, $p['id'], $p['nombre'], $p['categoria'], $p['precio'], $p['costo'], $c, null]);
            $sub += $p['precio'] * $c;
        }
        $desc = mt_rand(1, 18) === 1 ? round($sub * .1, 2) : 0;
        $neto = round($sub - $desc, 2);
        $prop = mt_rand(1, 3) === 1 ? 0 : round($neto * [0.05, 0.1, 0.1][mt_rand(0, 2)], 2);
        $metodo = ['Efectivo', 'Efectivo', 'Tarjeta', 'Tarjeta', 'Transferencia'][mt_rand(0, 4)];
        if ($personas > 2 && mt_rand(1, 4) === 1) {
            $mitad = round($neto / 2, 2);
            $insPago->execute([$cid, $turno, $mesero ?? 1, $mitad, round($prop / 2, 2), $metodo, 'Persona 1', $cierra]);
            $insPago->execute([$cid, $turno, $mesero ?? 1, $neto - $mitad, $prop - round($prop / 2, 2), 'Tarjeta', 'Persona 2', $cierra]);
        } else {
            $insPago->execute([$cid, $turno, $mesero ?? 1, $neto, $prop, $metodo, null, $cierra]);
        }
        if ($metodo === 'Efectivo') $efectivo += $neto + $prop;
        $pdo->prepare("UPDATE cuentas SET subtotal = ?, descuento = ?, descuento_motivo = ?, pagado = ?, propina = ? WHERE id = ?")
            ->execute([$sub, $desc, $desc ? 'Cliente frecuente' : null, $neto, $prop, $cid]);
    }
    $pdo->prepare("UPDATE turnos_caja SET efectivo_esperado = ?, efectivo_contado = ? WHERE id = ?")->execute([$efectivo, $efectivo, $turno]);
}

// ── Turnos publicados de semanas anteriores + asistencia ────────────────
$plan = [1 => ['07:00', '15:00', 6], 2 => ['07:00', '15:00', 2], 3 => ['07:00', '15:00', 3], 4 => ['15:00', '23:00', 0], 5 => ['15:00', '23:00', 1], 7 => ['15:00', '23:00', 4]];
for ($d = 21; $d >= 0; $d--) {
    $fecha = date('Y-m-d', strtotime("-$d days"));
    $dow = (int)date('N', strtotime($fecha)) - 1;
    foreach ($plan as $emp => [$ini, $fin, $libre]) {
        if ($dow === $libre) continue;
        if ($d > 0 && $fecha < date('Y-m-d', strtotime('monday this week'))) {
            $pdo->prepare("INSERT INTO turnos (empleado_id, fecha, hora_inicio, hora_fin, tipo, publicado) VALUES (?,?,?,?,?,1)")
                ->execute([$emp, $fecha, $ini, $fin, $ini === '07:00' ? 'manana' : 'tarde']);
        }
        if ($d === 0) continue; // hoy se marca en vivo (kiosco)
        $r = mt_rand(1, 100);
        if ($r <= 4) continue; // falta
        $tarde = $r <= 18 ? mt_rand(7, 25) : mt_rand(-8, 3);
        $entrada = strtotime("$fecha $ini") + $tarde * 60;
        $salida = strtotime("$fecha $fin") + ($fin === '23:00' ? 0 : 0) + mt_rand(-5, $r > 85 ? 75 : 10) * 60;
        $insMarca->execute([$emp, 'entrada', date('Y-m-d H:i:s', $entrada)]);
        $insMarca->execute([$emp, 'inicio_descanso', date('Y-m-d H:i:s', $entrada + 4 * 3600)]);
        $insMarca->execute([$emp, 'fin_descanso', date('Y-m-d H:i:s', $entrada + 4 * 3600 + 30 * 60)]);
        $insMarca->execute([$emp, 'salida', date('Y-m-d H:i:s', $salida)]);
    }
}

// ── Servicio en curso ────────────────────────────────────────────────────
$pdo->exec("INSERT INTO turnos_caja (abierto_por, monto_inicial, notas_apertura, abierto_en) VALUES (1, 500, 'Turno de demostración', DATE_SUB(NOW(), INTERVAL 3 HOUR))");
$turnoHoy = (int)$pdo->lastInsertId();
$byName = [];
foreach ($productos as $p) $byName[$p['nombre']] = $p;
$vivo = [
    // mesa, mesero, personas, minutos, estado comanda, items
    [3, 2, 4, 42, 'entregado', [['Pepián de pollo', 2, null], ['Limonada con hierbabuena', 2, 'poco hielo'], ['Nachos de la casa', 1, null]]],
    [5, 5, 2, 18, 'preparacion', [['Hamburguesa El Cuache', 1, 'sin cebolla, término medio'], ['Pasta Alfredo con pollo', 1, null]]],
    [6, 2, 6, 7, 'pendiente', [['Desayuno chapín', 3, 'huevos estrellados'], ['Capuchino', 4, null], ['Pancakes de la abuela', 2, null]]],
    [8, null, 2, 24, 'listo', [['Cheesecake de frutos rojos', 2, null], ['Espresso', 2, null]]],
    [10, 5, 3, 55, 'entregado', [['Lomito a la parrilla', 1, 'tres cuartos'], ['Alitas BBQ', 1, null], ['Licuado de fresa', 3, null]]],
];
foreach ($vivo as [$numero, $mesero, $personas, $min, $estado, $items]) {
    $mesaId = (int)$pdo->query("SELECT id FROM mesas WHERE numero = $numero")->fetchColumn();
    $abre = date('Y-m-d H:i:s', time() - $min * 60);
    $insCuenta->execute([$mesaId, $mesero, $turnoHoy, $personas, 'abierta', 0, 0, 0, 0, $abre, null]);
    $cid = (int)$pdo->lastInsertId();
    $ini = in_array($estado, ['preparacion', 'listo', 'entregado'], true) ? date('Y-m-d H:i:s', time() - ($min - 2) * 60) : null;
    $listo = in_array($estado, ['listo', 'entregado'], true) ? date('Y-m-d H:i:s', time() - max(1, $min - 15) * 60) : null;
    $insComanda->execute([$cid, $mesero ? 'mesero' : 'cliente', $mesero, $estado, $abre, $ini, $listo, $estado === 'entregado' ? $listo : null]);
    $com = (int)$pdo->lastInsertId();
    $sub = 0;
    foreach ($items as [$nombre, $cant, $nota]) {
        $p = $byName[$nombre];
        $insItem->execute([$com, $cid, $p['id'], $p['nombre'], $p['categoria'], $p['precio'], $p['costo'], $cant, $nota]);
        $sub += $p['precio'] * $cant;
    }
    $pdo->prepare("UPDATE cuentas SET subtotal = ? WHERE id = ?")->execute([$sub, $cid]);
}
$m3 = (int)$pdo->query("SELECT id FROM mesas WHERE numero = 3")->fetchColumn();
$m10 = (int)$pdo->query("SELECT id FROM mesas WHERE numero = 10")->fetchColumn();
$pdo->exec("INSERT INTO llamados (mesa_id, tipo, creado_en) VALUES ($m3, 'mesero', DATE_SUB(NOW(), INTERVAL 4 MINUTE))");
$pdo->exec("INSERT INTO llamados (mesa_id, tipo, metodo_pago, creado_en) VALUES ($m10, 'cuenta', 'tarjeta', DATE_SUB(NOW(), INTERVAL 40 SECOND))");

// Marcas de hoy: el turno de la mañana ya entró (Carla llegó tarde)
foreach ([1 => -4, 2 => 12, 3 => -2] as $emp => $min) {
    if ((int)date('N') - 1 === $plan[$emp][2]) continue;
    $insMarca->execute([$emp, 'entrada', date('Y-m-d') . ' ' . date('H:i:s', strtotime('07:00') + $min * 60)]);
}

$pdo->commit();
echo "Datos de demostración cargados.\n";
