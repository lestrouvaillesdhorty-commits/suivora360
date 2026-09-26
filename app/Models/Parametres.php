<?php

namespace App\Models;

use App\Core\Database;

/**
 * Paramètres de calcul, un jeu par filiale (taux de change, majoration par
 * défaut, TVA, frais par défaut). Modifier un paramètre ne change jamais
 * une simulation, une offre ou une commande déjà enregistrée — le
 * Simulateur lit ces valeurs uniquement comme valeurs de départ éditables.
 */
class Parametres
{
    public const DEFAUTS = [
        'taux_eur_fcfa' => 655.957,
        'marge_defaut_pourcentage' => 20.0,
        'tva_defaut_pourcentage' => 20.0,
        'assurance_defaut' => 0.0,
        'dedouanement_defaut' => 0.0,
        'taux_date_maj' => null,
        'taux_source' => '',
        'diviseur_volumetrique_aerien' => 6000.0,
        'diviseur_volumetrique_maritime' => 1000.0,
    ];

    public const TVA_OPTIONS = [0, 5.5, 10, 20];

    public static function forFiliale(int $filialeId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM parametres WHERE filiale_id = ?');
        $stmt->execute([$filialeId]);
        $row = $stmt->fetch();
        if (!$row) {
            return array_merge(['filiale_id' => $filialeId], self::DEFAUTS);
        }
        return $row;
    }

    /**
     * Crée ou met à jour la ligne de paramètres de la filiale (upsert manuel,
     * compatible SQLite/MySQL sans dépendre de la syntaxe ON DUPLICATE KEY).
     */
    public static function update(int $filialeId, array $data): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM parametres WHERE filiale_id = ?');
        $stmt->execute([$filialeId]);
        $existing = $stmt->fetch();

        $values = [
            (float) ($data['taux_eur_fcfa'] ?? self::DEFAUTS['taux_eur_fcfa']),
            (float) ($data['marge_defaut_pourcentage'] ?? self::DEFAUTS['marge_defaut_pourcentage']),
            (float) ($data['tva_defaut_pourcentage'] ?? self::DEFAUTS['tva_defaut_pourcentage']),
            (float) ($data['assurance_defaut'] ?? self::DEFAUTS['assurance_defaut']),
            (float) ($data['dedouanement_defaut'] ?? self::DEFAUTS['dedouanement_defaut']),
            ($data['taux_date_maj'] ?? null) ?: null,
            trim($data['taux_source'] ?? ''),
            (float) ($data['diviseur_volumetrique_aerien'] ?? self::DEFAUTS['diviseur_volumetrique_aerien']),
            (float) ($data['diviseur_volumetrique_maritime'] ?? self::DEFAUTS['diviseur_volumetrique_maritime']),
        ];

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE parametres SET taux_eur_fcfa = ?, marge_defaut_pourcentage = ?, tva_defaut_pourcentage = ?, assurance_defaut = ?, dedouanement_defaut = ?, taux_date_maj = ?, taux_source = ?, diviseur_volumetrique_aerien = ?, diviseur_volumetrique_maritime = ?, updated_at = ? WHERE filiale_id = ?'
            );
            $stmt->execute([...$values, date('Y-m-d H:i:s'), $filialeId]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO parametres (filiale_id, taux_eur_fcfa, marge_defaut_pourcentage, tva_defaut_pourcentage, assurance_defaut, dedouanement_defaut, taux_date_maj, taux_source, diviseur_volumetrique_aerien, diviseur_volumetrique_maritime, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$filialeId, ...$values, date('Y-m-d H:i:s')]);
        }
    }
}
