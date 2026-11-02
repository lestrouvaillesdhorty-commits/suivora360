<?php

namespace App\Core;

/** Vérifie (avec cache) si une colonne existe : le code reste compatible avant migration. */
class Schema
{
    private static array $cache = [];

    public static function colonne(string $table, string $colonne): bool
    {
        $k = $table . '.' . $colonne;
        if (isset(self::$cache[$k])) {
            return self::$cache[$k];
        }
        $pdo = Database::connection();
        $ok = false;
        try {
            if (Database::driver() === 'sqlite') {
                foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll() as $c) {
                    if ($c['name'] === $colonne) { $ok = true; break; }
                }
            } else {
                $s = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
                $s->execute([$table, $colonne]);
                $ok = (bool) $s->fetch();
            }
        } catch (\Throwable $e) {
            $ok = false;
        }
        return self::$cache[$k] = $ok;
    }

    /** Insère $base + les colonnes optionnelles de $extra qui existent. */
    public static function insert(string $table, array $base, array $extra = []): int
    {
        foreach ($extra as $c => $v) {
            if (self::colonne($table, $c)) {
                $base[$c] = $v;
            }
        }
        $cols = array_keys($base);
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($base));
        return (int) $pdo->lastInsertId();
    }
}
