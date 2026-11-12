<?php

namespace App\Models;

use App\Core\Database;

/**
 * Boîte « À valider » : messages, photos, PDF ou mails reçus (dépôt, collage,
 * partage depuis le téléphone) avant de devenir de vraies demandes. Rien n'est
 * créé tant que la personne n'a pas validé. Cloisonné par filiale.
 */
class EntrantDemande
{
    public const TAILLE_MAX = 10 * 1024 * 1024;

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM demandes_entrantes LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function find(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM demandes_entrantes WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** En attente de validation, dans les filiales données. */
    public static function enAttente(array $filialeIds): array
    {
        if (!$filialeIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($filialeIds), '?'));
        $st = Database::connection()->prepare("SELECT * FROM demandes_entrantes WHERE statut IN ('nouveau','lu') AND filiale_id IN ($in) ORDER BY id DESC");
        $st->execute(array_values($filialeIds));
        return $st->fetchAll();
    }

    public static function compter(array $filialeIds): int
    {
        if (!$filialeIds || !self::disponible()) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($filialeIds), '?'));
        $st = Database::connection()->prepare("SELECT COUNT(*) FROM demandes_entrantes WHERE statut IN ('nouveau','lu') AND filiale_id IN ($in)");
        $st->execute(array_values($filialeIds));
        return (int) $st->fetchColumn();
    }

    public static function creer(array $d): int
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO demandes_entrantes (filiale_id, utilisateur_id, source, texte, nom_original, nom_fichier, taille, type_mime, statut, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                (int) $d['filiale_id'], (int) $d['utilisateur_id'], $d['source'] ?? 'texte', $d['texte'] ?? null,
                $d['nom_original'] ?? null, $d['nom_fichier'] ?? null, $d['taille'] ?? null, $d['type_mime'] ?? null,
                'nouveau', date('Y-m-d H:i:s'),
            ]);
        return (int) $pdo->lastInsertId();
    }

    public static function enregistrerLecture(int $id, array $articles): void
    {
        Database::connection()->prepare("UPDATE demandes_entrantes SET lecture = ?, statut = 'lu' WHERE id = ?")
            ->execute([json_encode($articles, JSON_UNESCAPED_UNICODE), $id]);
    }

    public static function marquer(int $id, string $statut, ?int $demandeId = null): void
    {
        Database::connection()->prepare('UPDATE demandes_entrantes SET statut = ?, demande_id = ? WHERE id = ?')
            ->execute([$statut, $demandeId, $id]);
    }

    /** Source déduite du nom du fichier ou du texte. */
    public static function sourceDe(string $nom, string $texte = ''): string
    {
        $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
        if ($nom === '') {
            return 'texte';
        }
        return match (true) {
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'photo',
            in_array($ext, ['eml', 'msg'], true) => 'mail',
            default => 'fichier',
        };
    }

    public static function libelleSource(string $s): string
    {
        return ['texte' => 'Texte', 'pdf' => 'PDF', 'photo' => 'Photo', 'mail' => 'E-mail', 'fichier' => 'Fichier'][$s] ?? 'Message';
    }
}
