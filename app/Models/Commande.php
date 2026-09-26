<?php

namespace App\Models;

use App\Core\Database;

class Commande
{
    public const ETAPES_STEPS = [
        'paiement' => 'Paiement',
        'expedition' => 'Expédition',
        'douane' => 'Dédouanement',
        'livraison' => 'Livraison',
        'solde' => 'Solde',
    ];

    public const STEP_STATUTS = [
        'a_faire' => 'À faire',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
    ];

    /**
     * Libellés spécifiques par étape et par statut — plus explicites que le
     * générique "À faire / En cours / Terminé", qui créait de la confusion
     * (notamment pour la livraison : impossible de savoir si la marchandise
     * était à préparer, expédiée ou reçue).
     */
    public const STEP_STATUT_LABELS = [
        'paiement' => ['a_faire' => 'Paiement à faire', 'en_cours' => 'Paiement en cours', 'termine' => 'Payé'],
        'expedition' => ['a_faire' => 'À préparer', 'en_cours' => 'En préparation', 'termine' => 'Expédiée'],
        'douane' => ['a_faire' => 'Dédouanement à faire', 'en_cours' => 'En cours de dédouanement', 'termine' => 'Dédouané'],
        'livraison' => ['a_faire' => 'À expédier', 'en_cours' => 'En transit', 'termine' => 'Reçue'],
        'solde' => ['a_faire' => 'Solde à payer', 'en_cours' => 'Solde en cours', 'termine' => 'Soldé'],
    ];

    public static function libelleStatutEtape(string $etape, string $statut): string
    {
        return self::STEP_STATUT_LABELS[$etape][$statut] ?? (self::STEP_STATUTS[$statut] ?? $statut);
    }

    /**
     * Avancement automatique (0-100) : chaque étape terminée compte pour
     * une part égale, une étape "en cours" compte pour moitié — pas de
     * ressaisie manuelle du pourcentage.
     */
    public static function progression(array $steps): int
    {
        if (empty($steps)) {
            return 0;
        }
        $total = count($steps);
        $poids = 0.0;
        foreach ($steps as $step) {
            if ($step['statut'] === 'termine') {
                $poids += 1.0;
            } elseif ($step['statut'] === 'en_cours') {
                $poids += 0.5;
            }
        }
        return (int) round(($poids / $total) * 100);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commandes WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByDossier(int $dossierId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commandes WHERE dossier_id = ?');
        $stmt->execute([$dossierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée la commande à partir d'une cotation, avec les étapes de suivi
     * opérationnel standard (paiement, expédition, douane, livraison, solde).
     */
    public static function create(int $dossierId, int $filialeId, int $cotationId): int
    {
        $existing = self::findByDossier($dossierId);
        if ($existing) {
            return (int) $existing['id'];
        }

        $pdo = Database::connection();
        $filiale = Filiale::find($filialeId);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'commande');
        $reference = Compteur::formatReference('CMD', $numero);
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO commandes (dossier_id, filiale_id, cotation_id, reference, statut, etape, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$dossierId, $filialeId, $cotationId, $reference, 'en_cours', 'paiement', '', $now, $now]);
            $commandeId = (int) $pdo->lastInsertId();

            $ordre = 0;
            $insertStep = $pdo->prepare(
                'INSERT INTO commande_steps (commande_id, libelle, statut, date_prevue, date_reelle, notes, ordre, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?)'
            );
            foreach (self::ETAPES_STEPS as $code => $label) {
                $ordre++;
                $insertStep->execute([$commandeId, $code, $ordre === 1 ? 'en_cours' : 'a_faire', '', $ordre, $now, $now]);
            }

            $pdo->commit();
            return $commandeId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function steps(int $commandeId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commande_steps WHERE commande_id = ? ORDER BY ordre');
        $stmt->execute([$commandeId]);
        return $stmt->fetchAll();
    }

    public static function updateStep(int $stepId, int $commandeId, string $statut, ?string $dateReelle, string $notes): void
    {
        if (!array_key_exists($statut, self::STEP_STATUTS)) {
            throw new \InvalidArgumentException('Statut d\'étape invalide.');
        }
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE commande_steps SET statut = ?, date_reelle = ?, notes = ?, updated_at = ? WHERE id = ? AND commande_id = ?'
        );
        $stmt->execute([$statut, $dateReelle ?: null, trim($notes), $now, $stepId, $commandeId]);

        self::refreshEtape($commandeId);
    }

    /**
     * Recalcule l'étape courante de la commande (première étape non terminée)
     * et son statut global (terminée si toutes les étapes sont terminées).
     */
    private static function refreshEtape(int $commandeId): void
    {
        $steps = self::steps($commandeId);
        $etapeCourante = null;
        $toutesTerminees = true;
        foreach ($steps as $step) {
            if ($step['statut'] !== 'termine') {
                $toutesTerminees = false;
                if ($etapeCourante === null) {
                    $etapeCourante = $step['libelle'];
                }
            }
        }
        $etape = $toutesTerminees ? 'terminee' : ($etapeCourante ?? 'terminee');
        $statut = $toutesTerminees ? 'terminee' : 'en_cours';

        $stmt = Database::connection()->prepare('UPDATE commandes SET etape = ?, statut = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$etape, $statut, date('Y-m-d H:i:s'), $commandeId]);
    }

    public static function updateSuivi(int $commandeId, string $prochaineAction, ?string $dateRelance): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE commandes SET prochaine_action = ?, date_relance = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([trim($prochaineAction), $dateRelance ?: null, date('Y-m-d H:i:s'), $commandeId]);
    }

    public static function estEnRetard(array $commande): bool
    {
        return $commande['statut'] !== 'terminee'
            && !empty($commande['date_relance'])
            && $commande['date_relance'] < date('Y-m-d');
    }

    public static function userCanAccess(array $user, array $commande): bool
    {
        return Filiale::userCanAccess($user, (int) $commande['filiale_id']);
    }
}
