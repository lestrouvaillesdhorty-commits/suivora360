<?php

namespace App\Models;

use App\Core\Database;

/**
 * Lien de partage sécurisé pour le parcours "Demander une offre" : un
 * récapitulatif (référence, articles, spécifications) partagé avec un
 * fournisseur par WhatsApp, sans exposer les informations confidentielles
 * du client final par défaut. Le lien est à durée de vie limitée et
 * révocable à tout moment.
 */
class ConsultationPartage
{
    public const DUREES_JOURS = [3, 7, 14, 30];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM consultation_partages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM consultation_partages WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forConsultation(int $consultationId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM consultation_partages WHERE consultation_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$consultationId]);
        return $stmt->fetchAll();
    }

    public static function estValide(array $partage): bool
    {
        if (!empty($partage['revoque_le'])) {
            return false;
        }
        return $partage['expire_le'] >= date('Y-m-d H:i:s');
    }

    public static function create(int $consultationId, array $data, int $userId): array
    {
        $pdo = Database::connection();
        $token = bin2hex(random_bytes(24));
        $dureeJours = (int) ($data['duree_jours'] ?? 7);
        if (!in_array($dureeJours, self::DUREES_JOURS, true)) {
            $dureeJours = 7;
        }
        $expireLe = date('Y-m-d H:i:s', strtotime("+$dureeJours days"));
        $piecesIds = array_values(array_filter(array_map('intval', $data['pieces_ids'] ?? [])));
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO consultation_partages
             (consultation_id, token, masquer_client, pieces_ids, echeance_reponse, expire_le, cree_par, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $consultationId,
            $token,
            !empty($data['masquer_client']) ? 1 : 0,
            json_encode($piecesIds),
            $data['echeance_reponse'] ?: null,
            $expireLe,
            $userId,
            $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        return self::find($id);
    }

    public static function marquerEnvoye(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE consultation_partages SET marque_envoye_le = ? WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public static function revoquer(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE consultation_partages SET revoque_le = ? WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public static function enregistrerAcces(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE consultation_partages SET nb_consultations = nb_consultations + 1, dernier_acces = ? WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public static function piecesIds(array $partage): array
    {
        $decoded = json_decode($partage['pieces_ids'] ?? '[]', true);
        return is_array($decoded) ? $decoded : [];
    }
}
