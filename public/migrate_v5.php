<?php

/**
 * Migration V5 — Suivora360
 * ---------------------------------------------------------------
 * Ajoute la table demande_pieces_jointes (pièces jointes sur les
 * demandes : message original, devis reçus, captures...).
 * 100% additif, idempotent (CREATE TABLE IF NOT EXISTS) — peut être
 * relancée sans risque. À exécuter une seule fois sur l'hébergement,
 * puis à supprimer (comme install.php et les migrations précédentes).
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS demande_pieces_jointes (
        id $id,
        demande_id INT NOT NULL,
        nom_original VARCHAR(255) NOT NULL,
        nom_fichier VARCHAR(255) NOT NULL,
        taille INT NOT NULL DEFAULT 0,
        type_mime VARCHAR(100),
        uploaded_by INT,
        created_at DATETIME NOT NULL
    )$engine");
    echo "OK - table demande_pieces_jointes creee (ou deja existante).\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'ERREUR : ' . $e->getMessage() . "\n";
}
