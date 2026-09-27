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
