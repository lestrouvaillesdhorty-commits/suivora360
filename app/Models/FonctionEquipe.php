<?php

namespace App\Models;

use App\Core\Database;

/**
 * Fonctions de l'équipe (commercial, acheteur, vendeur… — libellés propres à
 * chaque entreprise, modifiables). Chaque fonction porte des valeurs par
 * défaut (indicateur d'objectif, attribution, taux de commission) reprises
 * quand on rattache un collaborateur. Distinct du rôle d'accès à l'application.
 */
class FonctionEquipe
{
    private const COULEURS = ['#0B9AAE', '#7C5CE0', '#1FA463', '#F57C2B', '#E5484D', '#2F6FED'];

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM fonctions_equipe LIMIT 1');
            Database::connection()->query('SELECT fonction_id FROM reglages_collaborateurs LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function couleur(int $id): string
    {
        return self::COULEURS[$id % count(self::COULEURS)];
    }

    /** Liste ordonnée ; crée deux fonctions d'exemple (modifiables) si l'entreprise n'en a aucune. */
    public static function toutes(int $organisationId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM fonctions_equipe WHERE organisation_id = ? ORDER BY ordre, id');
        $stmt->execute([$organisationId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            self::creer($organisationId, 'Commercial', 'marge', 'responsable', 0);
            self::creer($organisationId, 'Acheteur', 'marge', 'offres', 0);
            $stmt->execute([$organisationId]);
            $rows = $stmt->fetchAll();
        }
        return $rows;
    }

    public static function creer(int $organisationId, string $libelle, string $indicateur, string $base, float $taux): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordre),0)+1 FROM fonctions_equipe WHERE organisation_id = ?');
        $stmt->execute([$organisationId]);
        $ordre = (int) $stmt->fetchColumn();
        $pdo->prepare('INSERT INTO fonctions_equipe (organisation_id, libelle, indicateur, base, taux_commission, ordre, updated_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$organisationId, self::nettoyer($libelle), self::indicateur($indicateur), self::base($base), max(0, min(100, $taux)), $ordre, date('Y-m-d H:i:s')]);
    }

    public static function modifier(int $organisationId, int $id, string $libelle, string $indicateur, string $base, float $taux): void
    {
        Database::connection()->prepare('UPDATE fonctions_equipe SET libelle = ?, indicateur = ?, base = ?, taux_commission = ?, updated_at = ? WHERE id = ? AND organisation_id = ?')
            ->execute([self::nettoyer($libelle), self::indicateur($indicateur), self::base($base), max(0, min(100, $taux)), date('Y-m-d H:i:s'), $id, $organisationId]);
    }

    public static function supprimer(int $organisationId, int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM fonctions_equipe WHERE id = ? AND organisation_id = ?')->execute([$id, $organisationId]);
        $pdo->prepare('UPDATE reglages_collaborateurs SET fonction_id = NULL WHERE fonction_id = ? AND organisation_id = ?')->execute([$id, $organisationId]);
    }

    /** utilisateur_id => ['id','libelle','couleur'] pour les collaborateurs rattachés. */
    public static function parUtilisateur(int $organisationId): array
    {
        if (!self::disponible()) {
            return [];
        }
        $fonctions = [];
        foreach (self::toutes($organisationId) as $f) {
            $fonctions[(int) $f['id']] = $f;
        }
        $stmt = Database::connection()->prepare('SELECT utilisateur_id, fonction_id FROM reglages_collaborateurs WHERE organisation_id = ? AND fonction_id IS NOT NULL');
        $stmt->execute([$organisationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $f = $fonctions[(int) $r['fonction_id']] ?? null;
            if ($f) {
                $out[(int) $r['utilisateur_id']] = ['id' => (int) $f['id'], 'libelle' => $f['libelle'], 'couleur' => self::couleur((int) $f['id'])];
            }
        }
        return $out;
    }

    /** Pastille HTML de la fonction (chaîne vide si aucune). */
    public static function badge(?array $f): string
    {
        if (!$f) {
            return '';
        }
        return '<span class="badge" style="background:' . htmlspecialchars($f['couleur'], ENT_QUOTES) . ';color:#fff;margin-left:6px">' . htmlspecialchars($f['libelle'], ENT_QUOTES) . '</span>';
    }

    private static function nettoyer(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_substr($s !== '' ? $s : 'Fonction', 0, 60);
    }

    private static function indicateur(string $i): string
    {
        return isset(ObjectifCollaborateur::INDICATEURS[$i]) ? $i : 'marge';
    }

    private static function base(string $b): string
    {
        return isset(ObjectifCollaborateur::BASES[$b]) ? $b : 'responsable';
    }
}
