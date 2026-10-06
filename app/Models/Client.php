<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    public const STATUTS = [
        'prospect' => 'Prospect',
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'inactif' => 'Inactif',
    ];

    public const STATUT_BADGES = [
        'prospect' => 'badge-blue',
        'actif' => 'badge-green',
        'suspendu' => 'badge-orange',
        'inactif' => 'badge-gray',
    ];

    public const TYPES = [
        'particulier' => 'Particulier',
        'entreprise' => 'Entreprise',
        'distributeur' => 'Distributeur',
        'grossiste' => 'Grossiste',
        'revendeur' => 'Revendeur',
        'gms' => 'GMS / Enseigne',
        'administration' => 'Administration',
    ];

    public const CONDITIONS_PAIEMENT = [
        'comptant' => 'Comptant',
        'acompte_solde' => 'Acompte et solde avant expédition',
        '30j' => '30 jours',
        '45j' => '45 jours',
        '60j' => '60 jours',
        'autre' => 'Autre',
    ];

    public const MODES_TRANSPORT = [
        'maritime' => 'Maritime',
        'aerien' => 'Aérien',
        'routier' => 'Routier',
        'multimodal' => 'Multimodal',
        'autre' => 'Autre',
    ];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function allForFiliale(int $filialeId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM clients WHERE filiale_id = ?';
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
            "SELECT c.*, f.nom AS filiale_nom FROM clients c
             INNER JOIN filiales f ON f.id = c.filiale_id
             WHERE c.filiale_id IN ($placeholders)
             ORDER BY c.nom"
        );
        $stmt->execute($filialeIds);
        return $stmt->fetchAll();
    }

    public static function userCanAccess(array $user, array $client): bool
    {
        return Filiale::userCanAccess($user, (int) $client['filiale_id']);
    }

    /**
     * Recherche simple pour le sélecteur client du module Demandes (section
     * 2.B — "Recherche d'un client existant") : filtre côté serveur sur le
     * nom, limité à la filiale, pour rester utilisable même quand la liste
     * de clients est longue (pas de dépendance JS obligatoire : un simple
     * select reste toujours utilisable, cette recherche ne fait qu'aider).
     */
    public static function rechercherPourFiliale(int $filialeId, string $terme, int $limite = 30): array
    {
        $sql = 'SELECT * FROM clients WHERE filiale_id = ? AND is_active = 1';
        $params = [$filialeId];
        if (trim($terme) !== '') {
            $sql .= ' AND nom LIKE ?';
            $params[] = '%' . trim($terme) . '%';
        }
        $sql .= ' ORDER BY nom LIMIT ' . (int) $limite;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * [ajouté 04/10, création rapide de client depuis le module Demandes]
     * Contrôle des doublons avant création — même nom (insensible à la
     * casse/aux espaces) OU même e-mail non vide, au sein de la même
     * filiale. Ne bloque rien : signale seulement le client existant pour
     * que la personne choisisse (réutiliser ou confirmer malgré tout).
     */
    public static function rechercherDoublon(int $filialeId, string $nom, string $email): ?array
    {
        $nom = trim($nom);
        $email = trim($email);
        if ($nom === '' && $email === '') {
            return null;
        }
        $sql = 'SELECT * FROM clients WHERE filiale_id = ? AND (LOWER(nom) = LOWER(?)';
        $params = [$filialeId, $nom];
        if ($email !== '') {
            $sql .= ' OR (email != \'\' AND LOWER(email) = LOWER(?))';
            $params[] = $email;
        }
        $sql .= ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $filiale = Filiale::find((int) $data['filiale_id']);
        $numero = Compteur::next((int) $filiale['organisation_id'], 'client');
        $code = Compteur::formatReference('CLI', $numero);

        $stmt = $pdo->prepare(
            'INSERT INTO clients
             (filiale_id, code, nom, statut, type, email, telephone, pays, ville, code_postal, adresse, adresse_livraison, secteur, siret, tva, incoterm_habituel, mode_transport_habituel, conditions_paiement, fonction_contact, notes, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([
            $data['filiale_id'],
            $code,
            $data['nom'],
            $data['statut'] ?? 'actif',
            $data['type'] ?? '',
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['code_postal'] ?? '',
            $data['adresse'] ?? '',
            $data['adresse_livraison'] ?? '',
            $data['secteur'] ?? '',
            $data['siret'] ?? '',
            $data['tva'] ?? '',
            $data['incoterm_habituel'] ?? '',
            $data['mode_transport_habituel'] ?? '',
            $data['conditions_paiement'] ?? '',
            $data['fonction_contact'] ?? '',
            $data['notes'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clients SET nom = ?, statut = ?, type = ?, email = ?, telephone = ?, pays = ?, ville = ?, code_postal = ?, adresse = ?, adresse_livraison = ?, secteur = ?, siret = ?, tva = ?, incoterm_habituel = ?, mode_transport_habituel = ?, conditions_paiement = ?, fonction_contact = ?, notes = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['nom'],
            $data['statut'] ?? 'actif',
            $data['type'] ?? '',
            $data['email'] ?? '',
            $data['telephone'] ?? '',
            $data['pays'] ?? '',
            $data['ville'] ?? '',
            $data['code_postal'] ?? '',
            $data['adresse'] ?? '',
            $data['adresse_livraison'] ?? '',
            $data['secteur'] ?? '',
            $data['siret'] ?? '',
            $data['tva'] ?? '',
            $data['incoterm_habituel'] ?? '',
            $data['mode_transport_habituel'] ?? '',
            $data['conditions_paiement'] ?? '',
            $data['fonction_contact'] ?? '',
            $data['notes'] ?? '',
            $id,
        ]);
    }

    /**
     * Désactiver/Réactiver : conservé pour compatibilité (bouton rapide),
     * agit à la fois sur is_active (ancien mécanisme) et sur statut (nouveau,
     * plus riche — Prospect/Actif/Suspendu/Inactif).
     */
    public static function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET is_active = ?, statut = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $active ? 'actif' : 'inactif', $id]);
    }

    public static function nameOf(?int $id): string
    {
        if (!$id) {
            return '—';
        }
        $client = self::find($id);
        return $client ? $client['nom'] : '—';
    }

    /**
     * Chiffre d'affaires total : somme des cotations acceptées liées au
     * client, regroupée par devise (pas de conversion multi-devises pour
     * l'instant — Phase 4 — donc on n'additionne jamais des montants dans
     * des devises différentes sous un seul total, ce qui serait trompeur).
     * Retourne ['EUR' => 1200.0, 'FCFA' => 500000.0, ...].
     */
    public static function caTotal(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(devise, '—') AS devise, SUM(montant_total) AS total
             FROM cotations WHERE client_id = ? AND statut = 'acceptee'
             GROUP BY devise"
        );
        $stmt->execute([$clientId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['devise']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Montant restant à payer : somme des factures émises (non payées,
     * non annulées) rattachées aux cotations de ce client, regroupée par
     * devise pour la même raison que caTotal().
     */
    public static function resteAPayer(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(fa.devise, '—') AS devise, SUM(fa.montant) AS total
             FROM factures fa
             INNER JOIN cotations co ON co.id = fa.cotation_id
             WHERE co.client_id = ? AND fa.statut = 'emise'
             GROUP BY fa.devise"
        );
        $stmt->execute([$clientId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['devise']] = (float) $row['total'];
        }
        return $result;
    }
}
