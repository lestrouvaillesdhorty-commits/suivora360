<?php

namespace App\Models;

use App\Core\Database;

class Organisation
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM organisations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM organisations ORDER BY nom')->fetchAll();
    }
}
