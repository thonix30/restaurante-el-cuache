<?php
$config = [
    'host' => 'localhost',
    'dbname' => 'db_elcuache',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Credenciales de producción en config/database.local.php (ignorado por git)
$local = __DIR__ . '/database.local.php';
if (is_file($local)) {
    $config = array_merge($config, require $local);
}
return $config;
