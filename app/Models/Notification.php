<?php

namespace App\Models;

use App\Core\Database;
use App\Services\Mailer;

/**
 * Notifications internes (cloche) — "pour tout mouvement important sur le
 * logiciel" (repris de la V1). Le type reste un identifiant libre
 * (ex: 'collaborateur_assigne', 'collaborateur_retire') afin de pouvoir
 * ajouter d'autres mouvements notifiés plus tard sans migration.
 *
 * notifier() est le point d'entrée unique utilisé par le reste de
 * l'application : il crée la notification interne ET tente, en best-effort,
 * un email (voir App\Services\Mailer — jamais bloquant, jamais d'exception
 * remontée à l'appelant).
 */
class Notification
{
    public static function creer(
        int $utilisateurId,
        int $filialeId,
        string $type,
        string $titre,
        ?string $message = null,
        ?string $lien = null,
        ?string $entiteType = null,
        ?int $entiteId = null
    ): int {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (utilisateur_id, filiale_id, type, titre, message, lien, entite_type, entite_id, lu, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
        );
        $stmt->execute([
            $utilisateurId,
            $filialeId,
            $type,
            $titre,
            $message,
            $lien,
            $entiteType,
            $entiteId,
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Crée la notification interne pour l'utilisateur donné et tente en plus
     * un email best-effort (si MAIL_FROM_ADDRESS est configuré et que
     * l'utilisateur a un email). Ne lève jamais d'exception : un échec
     * d'email ne doit jamais bloquer l'action qui a déclenché la notification.
     */
    public static function notifier(
        array $utilisateur,
        int $filialeId,
        string $type,
        string $titre,
        string $message,
        ?string $lien = null,
        ?string $entiteType = null,
        ?int $entiteId = null
    ): int {
        $id = self::creer((int) $utilisateur['id'], $filialeId, $type, $titre, $message, $lien, $entiteType, $entiteId);

        try {
            $appUrl = rtrim((string) \App\Core\Env::get('APP_URL', ''), '/');
            $lienComplet = $lien && $appUrl !== '' ? $appUrl . $lien : $lien;
            $corps = $message . ($lienComplet ? "\n\n" . $lienComplet : '');
            Mailer::envoyer((string) ($utilisateur['email'] ?? ''), $titre, $corps);
        } catch (\Throwable $e) {
            // Best-effort : un échec d'email ne doit jamais faire échouer
            // l'action métier qui a déclenché cette notification.
        }

        return $id;
    }

    public static function nonLuesCountFor(int $utilisateurId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu = 0'
        );
        $stmt->execute([$utilisateurId]);
        return (int) $stmt->fetchColumn();
    }

    public static function recentesFor(int $utilisateurId, int $limite = 20): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limite
        );
        $stmt->execute([$utilisateurId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM notifications WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function marquerLue(int $id, int $utilisateurId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$id, $utilisateurId]);
    }

    public static function marquerToutesLues(int $utilisateurId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET lu = 1 WHERE utilisateur_id = ? AND lu = 0'
        );
        $stmt->execute([$utilisateurId]);
    }
}
