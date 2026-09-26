<?php

/**
 * Migration V6 — Suivora360
 * ---------------------------------------------------------------
 * Ajoute la table dossier_pieces_jointes (pièces jointes sur les
 * dossiers : factures pro forma, bons de commande, connaissements...).
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS dossier_pieces_jointes (
        id $id,
        dossier_id INT NOT NULL,
        nom_original VARCHAR(255) NOT NULL,
        nom_fichier VARCHAR(255) NOT NULL,
        taille INT NOT NULL DEFAULT 0,
        type_mime VARCHAR(100),
        uploaded_by INT,
        created_at DATETIME NOT NULL
    )$engine");
    echo "OK - table dossier_pieces_jointes creee (ou deja existante).\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'ERREUR : ' . $e->getMessage() . "\n";
}
