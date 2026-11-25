<?php

namespace App\Models;

use App\Core\Database;

class DemandeArticle
{
    /** Articles de la demande (les lignes de note libres sont exclues : elles ne sont ni des articles ni comptées). */
    public static function forDemande(int $demandeId): array
    {
        $filtre = \App\Core\Schema::colonne('demande_articles', 'est_note') ? ' AND est_note = 0' : '';
        $stmt = Database::connection()->prepare('SELECT * FROM demande_articles WHERE demande_id = ?' . $filtre . ' ORDER BY id');
        $stmt->execute([$demandeId]);
        return $stmt->fetchAll();
    }

    /** Notes libres de la demande, dans l'ordre de saisie. */
    public static function notes(int $demandeId): array
    {
        if (!\App\Core\Schema::colonne('demande_articles', 'est_note')) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM demande_articles WHERE demande_id = ? AND est_note = 1 ORDER BY id');
        $stmt->execute([$demandeId]);
        return $stmt->fetchAll();
    }

    /** Articles et notes dans l'ordre de saisie. */
    public static function forDemandeAvecNotes(int $demandeId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM demande_articles WHERE demande_id = ? ORDER BY id');
        $stmt->execute([$demandeId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM demande_articles WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Ajoute une ligne de note libre (texte seul). Ignorée sans erreur si la migration v76 n'est pas encore passée. */
    public static function creerNote(int $demandeId, string $texte): void
    {
        if (!\App\Core\Schema::colonne('demande_articles', 'est_note') || trim($texte) === '') {
            return;
        }
        $id = self::create($demandeId, ['designation' => mb_substr(trim($texte), 0, 500), 'quantite' => null]);
        Database::connection()->prepare('UPDATE demande_articles SET est_note = 1 WHERE id = ?')->execute([$id]);
    }

    public static function create(int $demandeId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO demande_articles (demande_id, designation, quantite, unite, conditionnement, reference, marque, caracteristiques, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $demandeId,
            $data['designation'],
            $data['quantite'] ?: null,
            $data['unite'] ?? '',
            $data['conditionnement'] ?? '',
            $data['reference'] ?? '',
            $data['marque'] ?? '',
            $data['caracteristiques'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * [ajouté 04/10] Modification d'une ligne d'article déjà enregistrée —
     * jusqu'ici seule la création existait (via la saisie manuelle sur la
     * fiche, ou l'extraction IA) : aucun moyen de corriger une ligne sans la
     * supprimer et la recréer.
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE demande_articles SET designation = ?, quantite = ?, unite = ?, conditionnement = ?, reference = ?, marque = ?, caracteristiques = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['designation'],
            $data['quantite'] ?: null,
            $data['unite'] ?? '',
            $data['conditionnement'] ?? '',
            $data['reference'] ?? '',
            $data['marque'] ?? '',
            $data['caracteristiques'] ?? '',
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM demande_articles WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function colonnesDetails(): bool
    {
        try {
            Database::connection()->query('SELECT dlc_min, certificats, echantillon, notes FROM demande_articles LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Détails facultatifs (migrate_v38.php) ; ignoré tant que la migration n'est pas lancée. */
    public static function enregistrerDetails(int $id, array $data): void
    {
        if (!self::colonnesDetails()) {
            return;
        }
        Database::connection()->prepare('UPDATE demande_articles SET dlc_min = ?, certificats = ?, echantillon = ?, notes = ? WHERE id = ?')->execute([
            mb_substr(trim((string) ($data['dlc_min'] ?? '')), 0, 60),
            mb_substr(trim((string) ($data['certificats'] ?? '')), 0, 255),
            !empty($data['echantillon']) ? 1 : 0,
            trim((string) ($data['notes'] ?? '')),
            $id,
        ]);
    }
}
