<?php
namespace App\Repositories;

use App\Utils\Database;
use PDO;

/** Base de los repositorios: helpers finos sobre PDO con sentencias preparadas. */
abstract class Repository
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    protected function all(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    protected function one(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: null;
    }

    protected function value(string $sql, array $params = [])
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    protected function exec(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** INSERT a partir de un array columna => valor. Devuelve el id. */
    protected function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
        $this->exec($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    /** UPDATE por id a partir de un array columna => valor. */
    protected function update(string $table, int $id, array $data): int
    {
        if (!$data) return 0;
        $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
        return $this->exec("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
    }

    /** Placeholders "?,?,?" para IN (...). */
    protected function in(array $values): string
    {
        return implode(',', array_fill(0, max(1, count($values)), '?'));
    }
}
