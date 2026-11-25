<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Transport confié à un transitaire, saisi une seule fois sur le dossier (onglet Exécution) :
 * transitaire + mode + marchandise + destination → tarif de sa fiche × quantité de l'offre, moins sa remise éventuelle.
 * Le montant final alimente le transport de l'offre retenue, donc la cotation.
 */
class DossierTransport
{
    public static function disponible(): bool
    {
        return Schema::colonne('dossiers', 'tr_montant') && TransitaireTarif::disponible();
    }

    /** Quantité de l'offre correspondant à l'unité du tarif (null si elle n'est pas connue). */
    public static function quantiteOffre(?array $offre, string $unite): ?float
    {
        if (!$offre) {
            return null;
        }
        $v = ['m3' => $offre['volume_m3'] ?? null, 'kg' => $offre['poids_kg'] ?? null, 'colis' => $offre['nombre_colis'] ?? null][$unite] ?? null;
        return ($v !== null && $v !== '' && (float) $v > 0) ? (float) $v : null;
    }

    /** @return array{unite:string,tarif:float,brut:float,remise:float,final:float}|null */
    public static function calculer(int $transitaireId, string $mode, string $famille, string $destination, float $quantite, float $remisePct): ?array
    {
        $t = TransitaireTarif::trouver($transitaireId, $mode, $famille, $destination);
        if (!$t || $quantite <= 0) {
            return null;
        }
        $remisePct = max(0.0, min(100.0, $remisePct));
        $brut = round((float) $t['montant'] * $quantite, 2);
        $remise = round($brut * $remisePct / 100, 2);
        return ['unite' => (string) $t['unite'], 'tarif' => (float) $t['montant'], 'brut' => $brut, 'remise' => $remise, 'final' => round($brut - $remise, 2)];
    }

    /**
     * Enregistre le transport sur le dossier et le reporte sur le transport de l'offre retenue
     * (seulement si la devise du transitaire est celle de l'offre). Renvoie un message d'erreur ou null.
     */
    public static function enregistrer(array $dossier, array $p, ?array $offreRetenue): ?string
    {
        $fid = (int) ($p['tr_transitaire_id'] ?? 0);
        $mode = (string) ($p['tr_mode'] ?? '');
        $famille = (string) ($p['tr_famille'] ?? 'alimentaire');
        $dest = mb_substr(trim((string) ($p['tr_destination'] ?? '')), 0, 120);
        $qte = (float) str_replace(',', '.', (string) ($p['tr_quantite'] ?? 0));
        $remisePct = (float) str_replace(',', '.', (string) ($p['tr_remise_pct'] ?? 0));
        $f = $fid > 0 ? Fournisseur::find($fid) : null;
        if (!$f || (int) $f['filiale_id'] !== (int) $dossier['filiale_id'] || !isset(Expedition::MODES[$mode]) || !isset(Expedition::MARCHANDISES[$famille])) {
            return 'Choisissez le transitaire, le mode et la marchandise.';
        }
        $c = self::calculer($fid, $mode, $famille, $dest, $qte, $remisePct);
        if (!$c) {
            return 'Aucun tarif en vigueur sur la fiche de ce transitaire pour ce mode, cette marchandise et cette destination, ou la quantité est vide.';
        }
        $devise = trim((string) ($f['devise'] ?? '')) ?: (string) ($offreRetenue['devise'] ?? '');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE dossiers SET tr_transitaire_id = ?, tr_mode = ?, tr_famille = ?, tr_destination = ?, tr_quantite = ?, tr_unite = ?, tr_tarif = ?, tr_remise_pct = ?, tr_montant = ?, tr_devise = ?, updated_at = ? WHERE id = ?')
            ->execute([$fid, $mode, $famille, $dest, $qte, $c['unite'], $c['tarif'], max(0, min(100, $remisePct)), $c['final'], $devise, date('Y-m-d H:i:s'), (int) $dossier['id']]);
        if ($offreRetenue && ($devise === '' || $devise === (string) $offreRetenue['devise'])) {
            $pdo->prepare('UPDATE offres SET transport_montant = ?, updated_at = ? WHERE id = ?')->execute([$c['final'], date('Y-m-d H:i:s'), (int) $offreRetenue['id']]);
        }
        return null;
    }
}
