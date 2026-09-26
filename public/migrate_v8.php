<?php

/**
 * Migration V8 — Suivora360
 * ---------------------------------------------------------------
 * Ajoute la table parametres (taux de change EUR→FCFA, majoration et TVA
 * par défaut, frais par défaut), utilisée par le Simulateur de prix.
 * 100% additive, idempotente (CREATE TABLE IF NOT EXISTS). À exécuter
 * une seule fois sur l'hébergement, puis à supprimer.
 */

use App\Core\Database;
use App\Core\Env;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::connection();
$driver = Database::driver();
$id = Database::idColumnType();
$engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS parametres (
        id $id,
        filiale_id INT NOT NULL UNIQUE,
        taux_eur_fcfa DECIMAL(10,3) NOT NULL DEFAULT 655.957,
        marge_defaut_pourcentage DECIMAL(6,2) NOT NULL DEFAULT 20,
        tva_defaut_pourcentage DECIMAL(5,2) NOT NULL DEFAULT 20,
        assurance_defaut DECIMAL(10,2) NOT NULL DEFAULT 0,
        dedouanement_defaut DECIMAL(10,2) NOT NULL DEFAULT 0,
        taux_date_maj DATE,
        taux_source VARCHAR(100),
        updated_at DATETIME NOT NULL
    )$engine");
    echo "OK - table parametres creee (ou deja existante).\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'ERREUR : ' . $e->getMessage() . "\n";
}
