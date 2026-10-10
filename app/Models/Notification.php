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


    /**
     * [08/10] Destinataires d'une notification liée à une filiale : utilisateurs actifs de
     * l'organisation, ayant l'un des rôles donnés, qui ont accès à cette filiale.
     */
    public static function destinatairesFiliale(int $filialeId, array $roles, array $exclureIds = []): array
    {
        $filiale = Filiale::find($filialeId);
        if (!$filiale || empty($roles)) {
            return [];
        }
        $res = [];
        foreach (Utilisateur::allForOrganisation((int) $filiale['organisation_id']) as $u) {
            if ((int) ($u['actif'] ?? 1) !== 1 || !in_array($u['role'], $roles, true) || in_array((int) $u['id'], $exclureIds, true)) {
                continue;
            }
            if (\App\Core\Permissions::seesAllFiliales($u['role']) || in_array($filialeId, Utilisateur::filialeIds((int) $u['id']), true)) {
                $res[] = $u;
            }
        }
        return $res;
    }

    /** Responsable du dossier s'il est actif, sinon propriétaires de la filiale. */
    public static function destinatairesDossier(array $dossier, array $exclureIds = []): array
    {
        if (!empty($dossier['responsable_id'])) {
            $u = Utilisateur::find((int) $dossier['responsable_id']);
            if ($u && (int) ($u['actif'] ?? 1) === 1 && !in_array((int) $u['id'], $exclureIds, true)) {
                return [$u];
            }
        }
        return self::destinatairesFiliale((int) $dossier['filiale_id'], ['proprietaire'], $exclureIds);
    }

    /** Vrai si une notification identique (même type + entité) existe déjà depuis $jours jours. */
    public static function existeRecente(int $utilisateurId, string $type, string $entiteType, int $entiteId, int $jours): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND type = ? AND entite_type = ? AND entite_id = ? AND created_at >= ?'
        );
        $stmt->execute([$utilisateurId, $type, $entiteType, $entiteId, date('Y-m-d H:i:s', time() - $jours * 86400)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Notifie plusieurs destinataires, sans jamais lever d'exception (best-effort). */
    public static function notifierTous(array $destinataires, int $filialeId, string $type, string $titre, string $message, ?string $lien, string $entiteType, int $entiteId): void
    {
        foreach ($destinataires as $u) {
            try {
                self::notifier($u, $filialeId, $type, $titre, $message, $lien, $entiteType, $entiteId);
            } catch (\Throwable $e) {
                // une notification en échec ne doit jamais bloquer l'action métier
            }
        }
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
