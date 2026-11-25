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
        if (Schema::colonne('dossiers', 'colis_mode')) {
            $m = (string) ($p['colis_mode'] ?? '');
            Database::connection()->prepare('UPDATE dossiers SET colis_mode = ? WHERE id = ?')->execute([array_key_exists($m, Expedition::MODES) ? $m : null, (int) $dossier['id']]);
        }
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

    /**
     * Activité d'un transitaire : tous les dossiers où il intervient (dossier de transport, ou partie transport d'un dossier d'achat),
     * avec ce qu'il coûte, le CA des dossiers de transport (cotation client) et ses délais. Montants toujours séparés par devise.
     *
     * @return array{lignes:array,couts:array,ca:array,marges:array,delai_reception:?float,delai_livraison:?float}
     */
    public static function activiteTransitaire(int $fournisseurId): array
    {
        $pdo = Database::connection();
        $ids = [];
        $q = function (string $sql, array $args = []) use ($pdo, &$ids) {
            try {
                $st = $pdo->prepare($sql);
                $st->execute($args);
                foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $v) { $ids[(int) $v] = true; }
            } catch (\Throwable $e) {
            }
        };
        if (Schema::colonne('dossiers', 'colis_transitaire_id')) { $q('SELECT id FROM dossiers WHERE colis_transitaire_id = ?', [$fournisseurId]); }
        if (Schema::colonne('dossiers', 'tr_transitaire_id')) { $q('SELECT id FROM dossiers WHERE tr_transitaire_id = ?', [$fournisseurId]); }
        $q("SELECT dossier_id FROM offres WHERE fournisseur_id = ? AND statut = 'retenue' AND dossier_id IS NOT NULL", [$fournisseurId]);
        if (Schema::colonne('offres', 'transitaire_id')) { $q("SELECT dossier_id FROM offres WHERE transitaire_id = ? AND statut = 'retenue' AND dossier_id IS NOT NULL", [$fournisseurId]); }
        if (Schema::colonne('commande_steps', 'transitaire_id')) { $q('SELECT c.dossier_id FROM commande_steps s INNER JOIN commandes c ON c.id = s.commande_id WHERE s.transitaire_id = ?', [$fournisseurId]); }
        try { $q('SELECT dossier_id FROM expedition_reservations r INNER JOIN expeditions e ON e.id = r.expedition_id WHERE e.fournisseur_id = ? AND r.dossier_id IS NOT NULL', [$fournisseurId]); } catch (\Throwable $e) { }

        $out = ['lignes' => [], 'couts' => [], 'ca' => [], 'marges' => [], 'delai_reception' => null, 'delai_livraison' => null];
        $d1 = $d2 = [];
        foreach (array_keys($ids) as $did) {
            $d = Dossier::find($did);
            if (!$d) { continue; }
            $transport = ($d['type_dossier'] ?? '') === 'transport_logistique';
            $cout = null; $devCout = '';
            $offre = Offre::retenueForDossier($did);
            if (Schema::colonne('dossiers', 'tr_montant') && (int) ($d['tr_transitaire_id'] ?? 0) === $fournisseurId && !empty($d['tr_montant'])) {
                $cout = (float) $d['tr_montant']; $devCout = (string) ($d['tr_devise'] ?? '');
            } elseif ($offre && (int) $offre['fournisseur_id'] === $fournisseurId && $transport) {
                $cout = (float) $offre['montant_total']; $devCout = (string) $offre['devise'];
            } elseif ($offre && (int) ($offre['transitaire_id'] ?? 0) === $fournisseurId && (float) ($offre['transport_montant'] ?? 0) > 0) {
                $cout = (float) $offre['transport_montant']; $devCout = (string) $offre['devise'];
            }
            $ca = null; $devCa = ''; $marge = null;
            if ($transport) {
                $cot = Cotation::latestForDossier($did);
                if ($cot && in_array($cot['statut'], ['envoyee', 'acceptee'], true)) {
                    $ca = (float) ($cot['montant_ht'] ?? $cot['montant_total']); $devCa = (string) $cot['devise'];
                    $marge = $cot['marge_montant'] !== null ? (float) $cot['marge_montant'] : null;
                }
            }
            $recu = $d['colis_recu_le'] ?? null; $parti = $d['colis_parti_le'] ?? null; $livre = $d['colis_livre_le'] ?? null;
            if ($recu && $parti) { $d1[] = (strtotime($parti) - strtotime($recu)) / 86400; }
            if ($parti && $livre) { $d2[] = (strtotime($livre) - strtotime($parti)) / 86400; }
            $etat = $livre ? 'Livré' : ($parti ? 'En route' : ($recu ? 'Chez le transitaire' : ($transport ? 'En attente' : 'Partie transport')));
            $out['lignes'][] = ['id' => $did, 'reference' => $d['reference'], 'objet' => $d['objet'] ?? '', 'transport' => $transport, 'etat' => $etat, 'cout' => $cout, 'dev_cout' => $devCout, 'ca' => $ca, 'dev_ca' => $devCa, 'marge' => $marge];
            if ($cout !== null) { $out['couts'][$devCout ?: '—'] = ($out['couts'][$devCout ?: '—'] ?? 0) + $cout; }
            if ($ca !== null) { $out['ca'][$devCa ?: '—'] = ($out['ca'][$devCa ?: '—'] ?? 0) + $ca; }
            if ($marge !== null) { $out['marges'][$devCa ?: '—'] = ($out['marges'][$devCa ?: '—'] ?? 0) + $marge; }
        }
        usort($out['lignes'], fn($a, $b) => $b['id'] <=> $a['id']);
        $out['delai_reception'] = $d1 ? round(array_sum($d1) / count($d1), 1) : null;
        $out['delai_livraison'] = $d2 ? round(array_sum($d2) / count($d2), 1) : null;
        return $out;
    }
}
