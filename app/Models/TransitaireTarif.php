<?php

namespace App\Models;

use App\Core\Database;

/**
 * Tarifs d'achat d'un transitaire (fiche fournisseur) : un tarif par mode de transport, par destination
 * et par famille (alimentaire / non alimentaire), avec unité et date de début. Une destination vide
 * veut dire « toutes destinations » : elle sert quand aucun tarif ne vise la destination de l'expédition.
 * Un nouveau tarif remplace l'ancien à partir de sa date ; les expéditions déjà créées gardent leurs valeurs.
 */
class TransitaireTarif
{
    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM transitaire_tarifs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function cle(string $destination): string
    {
        return mb_strtolower(trim($destination));
    }

    /** Tarifs en vigueur aujourd'hui (le plus récent par mode, destination et famille). */
    public static function courants(int $fournisseurId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $st = Database::connection()->prepare('SELECT * FROM transitaire_tarifs WHERE fournisseur_id = ? AND date_debut <= ? ORDER BY date_debut ASC, id ASC');
        $st->execute([$fournisseurId, date('Y-m-d')]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[$r['mode'] . '|' . self::cle((string) ($r['destination'] ?? '')) . '|' . $r['famille']] = $r;
        }
        usort($out, fn($a, $b) => [$a['mode'], (string) $a['destination'], $a['famille']] <=> [$b['mode'], (string) $b['destination'], $b['famille']]);
        return array_values($out);
    }

    /** Tarif applicable : destination exacte d'abord, puis « toutes destinations ». */
    public static function trouver(int $fournisseurId, string $mode, string $famille, string $destination): ?array
    {
        $exact = $tous = null;
        foreach (self::courants($fournisseurId) as $r) {
            if ($r['mode'] !== $mode || $r['famille'] !== $famille) {
                continue;
            }
            $c = self::cle((string) ($r['destination'] ?? ''));
            if ($c === '') {
                $tous = $r;
            } elseif ($c === self::cle($destination)) {
                $exact = $r;
            }
        }
        return $exact ?? $tous;
    }

    /** Tarifs de plusieurs transitaires pour le formulaire d'expédition : [id] => liste de {mo, f, d, m, u}. */
    public static function pourFormulaire(array $fournisseurIds): array
    {
        $out = [];
        foreach ($fournisseurIds as $fid) {
            foreach (self::courants((int) $fid) as $r) {
                $out[(int) $fid][] = ['mo' => $r['mode'], 'f' => $r['famille'], 'd' => self::cle((string) ($r['destination'] ?? '')), 'm' => (float) $r['montant'], 'u' => $r['unite']];
            }
        }
        return $out;
    }

    public static function enregistrer(int $fournisseurId, string $mode, string $famille, string $destination, string $unite, float $montant, string $dateDebut, ?int $userId): bool
    {
        $destination = mb_substr(trim($destination), 0, 120);
        if (!self::disponible() || !isset(Expedition::MODES[$mode]) || !in_array($famille, ['alimentaire', 'non_alimentaire'], true)
            || !isset(Expedition::TARIFS_ACHAT[$unite]) || $montant < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
            return false;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM transitaire_tarifs WHERE fournisseur_id = ? AND mode = ? AND famille = ? AND LOWER(destination) = ? AND date_debut = ?');
        $st->execute([$fournisseurId, $mode, $famille, self::cle($destination), $dateDebut]);
        if ($id = $st->fetchColumn()) {
            $pdo->prepare('UPDATE transitaire_tarifs SET unite = ?, montant = ?, destination = ? WHERE id = ?')->execute([$unite, $montant, $destination, $id]);
        } else {
            $pdo->prepare('INSERT INTO transitaire_tarifs (fournisseur_id, mode, famille, destination, unite, montant, date_debut, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$fournisseurId, $mode, $famille, $destination, $unite, $montant, $dateDebut, $userId, date('Y-m-d H:i:s')]);
        }
        return true;
    }

    /** Transitaires (fournisseurs de type Transitaire, actifs) visibles par l'utilisateur. */
    public static function transitaires(array $user): array
    {
        $out = [];
        try {
            foreach (Fournisseur::visibleFor($user) as $f) {
                if (in_array('transitaire', Fournisseur::typesDe($f), true) && (!isset($f['is_active']) || (int) $f['is_active'] === 1)) {
                    $out[] = $f;
                }
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
