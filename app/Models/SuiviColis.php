<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Suivi d'un colis confié à un transitaire qui l'expédie dès qu'il peut (pas de départ programmé, pas de réservation).
 * Les dates se saisissent au fil de l'eau et mettent à jour toutes seules les étapes de la commande.
 */
class SuiviColis
{
    public static function disponible(): bool
    {
        return Schema::colonne('dossiers', 'colis_recu_le') && Schema::colonne('dossiers', 'colis_livre_le');
    }

    private static function date($v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    /** Enregistre le suivi et aligne les étapes de la commande. Renvoie un message d'erreur ou null. */
    public static function enregistrer(array $dossier, array $p): ?string
    {
        $recu = self::date($p['colis_recu_le'] ?? null);
        $parti = self::date($p['colis_parti_le'] ?? null);
        $arrivee = self::date($p['colis_arrivee_le'] ?? null);
        $livre = self::date($p['colis_livre_le'] ?? null);
        if ($recu && $parti && $parti < $recu) {
            return 'La date de départ ne peut pas précéder la réception chez le transitaire.';
        }
        if ($parti && $livre && $livre < $parti) {
            return 'La date de livraison ne peut pas précéder le départ.';
        }
        if ($livre && !$parti) {
            return 'Indiquez aussi la date de départ du transitaire avant de saisir la livraison.';
        }
        if ($parti && !$recu) {
            return 'Indiquez aussi la date de réception chez le transitaire avant le départ.';
        }
        $t = trim((string) ($p['colis_transitaire_id'] ?? ''));
        $ty = trim((string) ($p['colis_type_produit_id'] ?? ''));
        Database::connection()->prepare(
            'UPDATE dossiers SET colis_transitaire_id = ?, colis_type_produit_id = ?, colis_pays = ?, colis_ville = ?, colis_description = ?, colis_suivi = ?,
             colis_recu_le = ?, colis_parti_le = ?, colis_arrivee_le = ?, colis_livre_le = ?, updated_at = ? WHERE id = ?'
        )->execute([
            $t !== '' ? (int) $t : null, $ty !== '' ? (int) $ty : null,
            mb_substr(trim((string) ($p['colis_pays'] ?? '')), 0, 80) ?: null, mb_substr(trim((string) ($p['colis_ville'] ?? '')), 0, 120) ?: null,
            mb_substr(trim((string) ($p['colis_description'] ?? '')), 0, 190) ?: null, mb_substr(trim((string) ($p['colis_suivi'] ?? '')), 0, 120) ?: null,
            $recu, $parti, $arrivee, $livre, date('Y-m-d H:i:s'), (int) $dossier['id'],
        ]);
        self::alignerCommande((int) $dossier['id'], (int) ($t !== '' ? $t : 0), $recu, $parti, $livre);
        return null;
    }

    /** Reçu → Expédition terminée + transitaire « reçue » ; parti → transitaire terminé + livraison « en route » ; livré → livraison terminée. */
    private static function alignerCommande(int $dossierId, int $transitaireId, ?string $recu, ?string $parti, ?string $livre): void
    {
        try {
            $cmd = Commande::findByDossier($dossierId);
            if (!$cmd) {
                return;
            }
            $cid = (int) $cmd['id'];
            $cibles = [
                'expedition' => [$recu ? 'termine' : 'a_faire', $recu],
                'transitaire' => [$parti ? 'termine' : ($recu ? 'en_cours' : 'a_faire'), $parti],
                'livraison' => [$livre ? 'termine' : ($parti ? 'en_cours' : 'a_faire'), $livre],
            ];
            foreach (Commande::steps($cid) as $st) {
                if (!isset($cibles[$st['libelle']]) || $st['statut'] === Commande::STATUT_INCLUS) {
                    continue;
                }
                [$statut, $date] = $cibles[$st['libelle']];
                $transit = [];
                if ($st['libelle'] === 'transitaire') {
                    $transit = ['transitaire_id' => $transitaireId ?: ($st['transitaire_id'] ?? 0), 'transitaire_nom' => $st['transitaire_nom'] ?? '', 'date_arrivee' => $recu, 'date_depart' => $parti];
                }
                Commande::updateStep((int) $st['id'], $cid, $statut, $date, (string) ($st['notes'] ?? ''), $transit);
            }
            Dossier::aligneSurCommande($dossierId);
        } catch (\Throwable $e) {
        }
    }
}
