<?php
/**
 * Configuración general de la API.
 * En producción, sobrescribe estos valores con un config/app.local.php
 * (ignorado por git) que retorne un array con las claves a reemplazar.
 */
$config = [
    'name'       => 'El Cuache API',
    'timezone'   => 'America/Guatemala',
    // Firma de los JWT. Va SOLO en app.local.php; si no existe, la API la genera
    // al primer uso (ver Utils\Config). Cambiarla solo cierra las sesiones abiertas.
    'jwt_secret' => '',
    'jwt_ttl'    => 60 * 60 * 12,           // 12 horas: un turno largo sin volver a iniciar sesión
    // Clave para el HMAC de los PIN de asistencia. Este valor es el de DEMOSTRACIÓN
    // (los PIN de Database/seed.sql se calcularon con él). En producción ponla en
    // app.local.php ANTES de asignar los PIN reales: cambiarla después invalida los PIN guardados.
    'pin_secret' => '345836036eabb78c3146fa3f36b2622d736be35db37c2e71f5c2cad6ba148ff2',
    'cors_origin' => '*',
    // Inicio de sesión: intentos fallidos antes del bloqueo y duración base del bloqueo (seg).
    'login_max_intentos' => 5,
    'login_bloqueo_seg'  => 60,
];

$local = __DIR__ . '/app.local.php';
if (is_file($local)) {
    $config = array_merge($config, require $local);
}
return $config;
