<?php

namespace App\Models;

use App\Core\Database;

class Fournisseur
{
    public const STATUTS = [
        'a_qualifier' => 'À qualifier',
        'approuve' => 'Approuvé',
        'sous_surveillance' => 'Sous surveillance',
        'suspendu' => 'Suspendu',
        'inactif' => 'Inactif',
    ];

    public const STATUT_BADGES = [
        'a_qualifier' => 'badge-yellow',
        'approuve' => 'badge-green',
        'sous_surveillance' => 'badge-orange',
        'suspendu' => 'badge-red',
        'inactif' => 'badge-gray',
    ];

    // Critères de notation détaillée (0 à 5), moyenne = note globale
    public const CRITERES_NOTE = [
        'note_prix' => 'Prix',
        'note_qualite' => 'Qualité',
        'note_delai' => 'Délai',
        'note_reactivite' => 'Réactivité',
        'note_conformite' => 'Conformité documentaire',
        'note_engagements' => 'Respect des engagements',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM fournisseurs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForFiliale(int $filialeId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM fournisseurs WHERE filiale_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY nom';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$filialeId]);
        return $stmt->fetchAll();
    }

    public static function visibleFor(array $user): array
    {
        $filialeIds = Filiale::visibleIdsFor($user);
        if (empty($filialeIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($filialeIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT fo.*, fi.nom AS filiale_nom FROM fournisseurs fo
             INNER JOIN filiales fi ON fi.id = fo.filiale_id
             WHERE fo.filiale_id IN ($placeholders)
             ORDER BY fo.nom"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $fournisseur): bool
    {
        return Filiale::userCanAccess($user, (int) $fournisseur['filiale_id']);
    }

    private static function normalizeNote($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;
        return $n >= 0 && $n <= 5 ? $n : null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $data['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'fournisseur');
        $code = Compteur::formatReference('FOU', $numero);

        $stmt = $pdo->prepare(
            'INSERT INTO fournisseurs
             (filiale_id, code, nom, statut, email, telephone, pays, ville, adresse, devise, secteur, site_web, categories_produits, marques, pays_desservis, incoterms_pratiques, quantite_min, fonction_contact, note_prix, note_qualite, note_delai, note_reactivite, note_conformite, note_engagements, notes, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $data['filiale_id'],
            $code,
            $data['nom'],
            $data['statut'] ?? 'a_qualifier',
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['adresse'] ?? '',
            $data['devise'] ?? '',
            $data['secteur'] ?? '',
            $data['site_web'] ?? '',
            $data['categories_produits'] ?? '',
            $data['marques'] ?? '',
            $data['pays_desservis'] ?? '',
            $data['incoterms_pratiques'] ?? '',
            $data['quantite_min'] ?? '',
            $data['fonction_contact'] ?? '',
            self::normalizeNote($data['note_prix'] ?? null),
            self::normalizeNote($data['note_qualite'] ?? null),
            self::normalizeNote($data['note_delai'] ?? null),
            self::normalizeNote($data['note_reactivite'] ?? null),
            self::normalizeNote($data['note_conformite'] ?? null),
            self::normalizeNote($data['note_engagements'] ?? null),
            $data['notes'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE fournisseurs SET nom = ?, statut = ?, email = ?, telephone = ?, pays = ?, ville = ?, adresse = ?, devise = ?, secteur = ?, site_web = ?, categories_produits = ?, marques = ?, pays_desservis = ?, incoterms_pratiques = ?, quantite_min = ?, fonction_contact = ?, note_prix = ?, note_qualite = ?, note_delai = ?, note_reactivite = ?, note_conformite = ?, note_engagements = ?, notes = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['nom'],
            $data['statut'] ?? 'a_qualifier',
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['adresse'] ?? '',
            $data['devise'] ?? '',
            $data['secteur'] ?? '',
            $data['site_web'] ?? '',
            $data['categories_produits'] ?? '',
            $data['marques'] ?? '',
            $data['pays_desservis'] ?? '',
            $data['incoterms_pratiques'] ?? '',
            $data['quantite_min'] ?? '',
            $data['fonction_contact'] ?? '',
            self::normalizeNote($data['note_prix'] ?? null),
            self::normalizeNote($data['note_qualite'] ?? null),
            self::normalizeNote($data['note_delai'] ?? null),
            self::normalizeNote($data['note_reactivite'] ?? null),
            self::normalizeNote($data['note_conformite'] ?? null),
            self::normalizeNote($data['note_engagements'] ?? null),
            $data['notes'] ?? '',
            $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE fournisseurs SET is_active = ?, statut = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $active ? 'approuve' : 'inactif', $id]);
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $fournisseur = self::find($id);
        return $fournisseur ? $fournisseur['nom'] : '—';
    }

    /**
     * Note globale = moyenne des critères renseignés. Null si aucun critère
     * n'a été évalué (on n'invente jamais de note).
     */
    public static function noteGlobale(array $fournisseur): ?float
    {
        $valeurs = [];
        foreach (array_keys(self::CRITERES_NOTE) as $champ) {
            if (isset($fournisseur[$champ]) && $fournisseur[$champ] !== null && $fournisseur[$champ] !== '') {
                $valeurs[] = (float) $fournisseur[$champ];
            }
        }
        if (empty($valeurs)) {
            return null;
        }
        return round(array_sum($valeurs) / count($valeurs), 1);
    }
}
