<?php
namespace App\Utils;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            // Guatemala no usa horario de verano: offset fijo. Así NOW() de MySQL
            // coincide con date() de PHP aunque el servidor esté en UTC.
            $this->pdo->exec("SET time_zone = '-06:00'");
        } catch (PDOException $e) {
            error_log($e->getMessage());
            Response::error('No se pudo conectar con la base de datos.', 503);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /** Ejecuta $fn dentro de una transacción (anidable: solo la externa hace commit). */
    public static function transaction(callable $fn)
    {
        $pdo = self::getInstance()->getConnection();
        if ($pdo->inTransaction()) return $fn($pdo);
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
