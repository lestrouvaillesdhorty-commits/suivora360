<?php

/**
 * Migration V10 — Suivora360
 * ---------------------------------------------------------------
 * Rôles fins (Phase 4) : remplace le modèle binaire dirigeant/employé
 * par 6 rôles (Propriétaire, Admin d'organisation, Achats, Commercial,
 * Finance, Lecture seule). La colonne utilisateurs.role reste VARCHAR(20),
 * aucun changement de schéma nécessaire — uniquement une bascule de valeurs :
 *   - 'dirigeant' -> 'proprietaire' (accès identique, superset)
 *   - 'employe'   -> 'commercial'   (le plus proche de l'usage quotidien
 *                                    actuel ; à réattribuer individuellement
 *                                    depuis la page Utilisateurs ensuite)
 * 100% additif côté schéma, idempotent (ne touche que les lignes qui ont
 * encore l'ancienne valeur) — peut être relancée sans risque. À exécuter
 * une seule fois sur l'hébergement, puis à supprimer.
 */

use App\Core\Database;
use App\Core\Env;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::connection();

try {
    $stmt1 = $pdo->prepare("UPDATE utilisateurs SET role = 'proprietaire' WHERE role = 'dirigeant'");
    $stmt1->execute();
    $n1 = $stmt1->rowCount();

    $stmt2 = $pdo->prepare("UPDATE utilisateurs SET role = 'commercial' WHERE role = 'employe'");
    $stmt2->execute();
    $n2 = $stmt2->rowCount();

    echo "OK - $n1 compte(s) dirigeant -> proprietaire, $n2 compte(s) employe -> commercial.\n";
    echo "Pensez a repartir les comptes 'commercial' vers leur role definitif (Achats/Finance/Lecture seule) depuis la page Utilisateurs.\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'ERREUR : ' . $e->getMessage() . "\n";
}
