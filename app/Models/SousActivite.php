<?php

namespace App\Models;

use App\Core\Database;

/**
 * Sous-activités configurables par filiale (ex. Import / Export / Local pour
 * le sourcing). Une sous-activité dépend d'une activité de
 * Demande::ACTIVITES. Tant que migrate_v37.php n'a pas été lancée,
 * disponible() = false et les écrans retombent sur le fonctionnement actuel.
 */
class SousActivite
{
    public const DEFAUTS = [
        'Sourcing et approvisionnement' => ['Sourcing et approvisionnement', 'Import', 'Export', 'Local'],
        'Transport et logistique' => ['Import', 'Export', 'Dédouanement et transit'],
        'Prestation de service' => ['Phytosanitaire', 'Nettoyage', 'Achat pour compte', 'Accompagnement', 'Maintenance'],
    ];

    /** Familles de produit proposées sur une demande de sourcing. */
    public const FAMILLES = ['Alimentaire', 'Machines et équipements', 'Fournitures', 'Autres non alimentaires'];

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM sous_activites LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Liste d'une filiale, groupée par activité : [activite => [[id, nom, actif, usage], ...]]. */
    public static function parActivite(int $filialeId, bool $actifsSeulement = false): array
    {
        $out = array_fill_keys(Demande::ACTIVITES, []);
        if (!self::disponible()) {
            return $out;
        }
        $sql = 'SELECT * FROM sous_activites WHERE filiale_id = ?' . ($actifsSeulement ? ' AND actif = 1' : '') . ' ORDER BY ordre, id';
        $st = Database::connection()->prepare($sql);
        $st->execute([$filialeId]);
        foreach ($st->fetchAll() as $r) {
            $out[$r['activite']][] = $r;
        }
        return $out;
    }

    /** Noms actifs, pour les listes déroulantes : [activite => [nom, ...]]. */
    public static function nomsActifs(int $filialeId): array
    {
        $out = [];
        foreach (self::parActivite($filialeId, true) as $act => $rows) {
            $out[$act] = array_map(fn($r) => $r['nom'], $rows);
        }
        return $out;
    }

    /** Nombre de demandes utilisant chaque sous-activité de la filiale : ['activite|nom' => n]. */
    public static function usages(int $filialeId): array
    {
        if (!self::disponible() || !self::colonnesDemandes()) {
            return [];
        }
        $st = Database::connection()->prepare("SELECT activite, sous_activite, COUNT(*) n FROM demandes WHERE filiale_id = ? AND sous_activite IS NOT NULL AND sous_activite <> '' GROUP BY activite, sous_activite");
        $st->execute([$filialeId]);
        $u = [];
        foreach ($st->fetchAll() as $r) {
            $u[$r['activite'] . '|' . $r['sous_activite']] = (int) $r['n'];
        }
        return $u;
    }

    public static function colonnesDemandes(): bool
    {
        try {
            Database::connection()->query('SELECT sous_activite, famille_produit FROM demandes LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function ajouter(int $filialeId, string $activite, string $nom): bool
    {
        $nom = trim(mb_substr($nom, 0, 120));
        if ($nom === '' || !in_array($activite, Demande::ACTIVITES, true) || !self::disponible()) {
            return false;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id, actif FROM sous_activites WHERE filiale_id = ? AND activite = ? AND LOWER(nom) = LOWER(?)');
        $st->execute([$filialeId, $activite, $nom]);
        if ($row = $st->fetch()) {
            $pdo->prepare('UPDATE sous_activites SET actif = 1 WHERE id = ?')->execute([$row['id']]);
            return true;
        }
        $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre),0)+1 FROM sous_activites WHERE filiale_id = ' . (int) $filialeId)->fetchColumn();
        $pdo->prepare('INSERT INTO sous_activites (filiale_id, activite, nom, ordre, actif, created_at) VALUES (?,?,?,?,1,?)')
            ->execute([$filialeId, $activite, $nom, $ordre, date('Y-m-d H:i:s')]);
        return true;
    }

    public static function renommer(int $filialeId, int $id, string $nom): bool
    {
        $nom = trim(mb_substr($nom, 0, 120));
        if ($nom === '' || !self::disponible()) {
            return false;
        }
        // Les demandes déjà créées gardent l'ancien libellé (texte) : on le met à jour avec.
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT * FROM sous_activites WHERE id = ? AND filiale_id = ?');
        $st->execute([$id, $filialeId]);
        $row = $st->fetch();
        if (!$row) {
            return false;
        }
        $pdo->prepare('UPDATE sous_activites SET nom = ? WHERE id = ?')->execute([$nom, $id]);
        if (self::colonnesDemandes()) {
            $pdo->prepare('UPDATE demandes SET sous_activite = ? WHERE filiale_id = ? AND activite = ? AND sous_activite = ?')
                ->execute([$nom, $filialeId, $row['activite'], $row['nom']]);
        }
        return true;
    }

    public static function basculer(int $filialeId, int $id): bool
    {
        if (!self::disponible()) {
            return false;
        }
        $st = Database::connection()->prepare('UPDATE sous_activites SET actif = 1 - actif WHERE id = ? AND filiale_id = ?');
        $st->execute([$id, $filialeId]);
        return $st->rowCount() > 0;
    }

    /** Une sous-activité valide pour cette filiale et cette activité, sinon chaîne vide. */
    public static function valider(int $filialeId, string $activite, string $nom): string
    {
        $nom = trim($nom);
        if ($nom === '') {
            return '';
        }
        return in_array($nom, self::nomsActifs($filialeId)[$activite] ?? [], true) ? $nom : '';
    }

    public static function familleValide(string $f): string
    {
        return in_array($f, self::FAMILLES, true) ? $f : '';
    }
}
