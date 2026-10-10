<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Notification;

/**
 * [08/10] Rappels automatiques sans tâche planifiée : à la connexion / au chargement des pages
 * (au plus une fois par heure et par session), on notifie le responsable d'un dossier actif
 * dont l'échéance est dépassée (rappel au plus tous les 7 jours) ou tombe aujourd'hui / demain
 * (une seule fois). Ne lève jamais d'exception.
 */
class Rappels
{
    public static function pourUtilisateur(array $user): void
    {
        try {
            if (($_SESSION['rappels_ts'] ?? 0) > time() - 3600) {
                return;
            }
            $_SESSION['rappels_ts'] = time();

            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                "SELECT id, filiale_id, reference, objet, echeance FROM dossiers
                 WHERE statut = 'actif' AND responsable_id = ? AND echeance IS NOT NULL AND echeance <= ? LIMIT 50"
            );
            $stmt->execute([(int) $user['id'], date('Y-m-d', strtotime('+1 day'))]);
            $aujourdhui = date('Y-m-d');
            foreach ($stmt->fetchAll() as $d) {
                $lien = '/index.php?r=dossiers/' . $d['id'];
                $libelle = $d['reference'] . ' — ' . $d['objet'];
                if ($d['echeance'] < $aujourdhui) {
                    if (Notification::existeRecente((int) $user['id'], 'dossier_en_retard', 'dossier', (int) $d['id'], 7)) {
                        continue;
                    }
                    $jours = (int) floor((strtotime($aujourdhui) - strtotime($d['echeance'])) / 86400);
                    Notification::notifier($user, (int) $d['filiale_id'], 'dossier_en_retard', 'Dossier en retard',
                        $libelle . ' : échéance dépassée de ' . $jours . ' jour' . ($jours > 1 ? 's' : '') . '.', $lien, 'dossier', (int) $d['id']);
                } else {
                    if (Notification::existeRecente((int) $user['id'], 'dossier_echeance_proche', 'dossier', (int) $d['id'], 3)) {
                        continue;
                    }
                    $quand = $d['echeance'] === $aujourdhui ? "aujourd'hui" : 'demain';
                    Notification::notifier($user, (int) $d['filiale_id'], 'dossier_echeance_proche', 'Échéance proche',
                        $libelle . ' : échéance ' . $quand . ' (' . date('d/m/Y', strtotime($d['echeance'])) . ').', $lien, 'dossier', (int) $d['id']);
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
