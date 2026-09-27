<?php

namespace App\Models;

use App\Core\Database;

class Utilisateur
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForOrganisation(int $organisationId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM utilisateurs WHERE organisation_id = ? ORDER BY nom');
        $stmt->execute([$organisationId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO utilisateurs (organisation_id, nom, email, mot_de_passe_hash, role, actif, created_at)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $data['organisation_id'],
            $data['nom'],
            $data['email'],
            password_hash($data['mot_de_passe'], PASSWORD_DEFAULT),
            $data['role'],
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateRole(int $id, string $role): void
    {
        $stmt = Database::connection()->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?');
        $stmt->execute([$role, $id]);
    }

    public static function updateInfo(int $id, string $nom, string $email): void
    {
        $stmt = Database::connection()->prepare('UPDATE utilisateurs SET nom = ?, email = ? WHERE id = ?');
        $stmt->execute([$nom, $email, $id]);
    }

    public static function setActive(int $id, bool $actif): void
    {
        $stmt = Database::connection()->prepare('UPDATE utilisateurs SET actif = ? WHERE id = ?');
        $stmt->execute([$actif ? 1 : 0, $id]);
    }

    public static function updatePassword(int $id, string $motDePasse): void
    {
        $stmt = Database::connection()->prepare('UPDATE utilisateurs SET mot_de_passe_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($motDePasse, PASSWORD_DEFAULT), $id]);
    }

    /**
     * Nombre de comptes Propriétaire actifs dans l'organisation — sert à
     * empêcher de désactiver le dernier Propriétaire restant (on se
     * retrouverait sans personne pour ré-administrer les accès).
     */
    public static function countProprietairesActifs(int $organisationId, int $excludeId = 0): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM utilisateurs WHERE organisation_id = ? AND role = 'proprietaire' AND actif = 1 AND id != ?"
        );
        $stmt->execute([$organisationId, $excludeId]);
        return (int) $stmt->fetchColumn();
    }

    public static function setFiliales(int $utilisateurId, array $filialeIds): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM utilisateur_filiales WHERE utilisateur_id = ?');
        $stmt->execute([$utilisateurId]);

        $stmt = $pdo->prepare('INSERT INTO utilisateur_filiales (utilisateur_id, filiale_id) VALUES (?, ?)');
        foreach ($filialeIds as $filialeId) {
            $stmt->execute([$utilisateurId, (int) $filialeId]);
        }
    }

    public static function filialeIds(int $utilisateurId): array
    {
        $stmt = Database::connection()->prepare('SELECT filiale_id FROM utilisateur_filiales WHERE utilisateur_id = ?');
        $stmt->execute([$utilisateurId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $user = self::find($id);
        return $user ? $user['nom'] : '—';
    }
}
