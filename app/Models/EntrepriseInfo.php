<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Storage;

/** Fiche « Mon entreprise » (une par filiale) : en-tête, mentions et conditions des devis. */
class EntrepriseInfo
{
    public const CHAMPS = ['raison_sociale', 'adresse', 'telephone', 'email', 'site_web', 'identifiants', 'coordonnees_bancaires', 'devis_conditions'];
    public const EXTENSIONS_LOGO = ['png', 'jpg', 'jpeg'];

    public static function disponible(): bool
    {
        try {
            Database::connection()->query('SELECT 1 FROM entreprise_infos LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function forFiliale(int $filialeId): array
    {
        $vide = array_fill_keys(self::CHAMPS, '') + ['logo' => '', 'devis_validite_jours' => 30];
        if (!self::disponible()) {
            return $vide;
        }
        $st = Database::connection()->prepare('SELECT * FROM entreprise_infos WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $row = $st->fetch();
        return $row ? array_merge($vide, array_map(fn($v) => $v ?? '', $row)) : $vide;
    }

    public static function save(int $filialeId, array $d, ?string $logo = null): void
    {
        $pdo = Database::connection();
        $existe = self::forFiliale($filialeId);
        $st = $pdo->prepare('SELECT id FROM entreprise_infos WHERE filiale_id = ?');
        $st->execute([$filialeId]);
        $id = $st->fetchColumn();
        $vals = [];
        foreach (self::CHAMPS as $c) {
            $vals[$c] = trim((string) ($d[$c] ?? ''));
        }
        $jours = max(1, min(365, (int) ($d['devis_validite_jours'] ?? 30)));
        $logoFinal = $logo ?? ($existe['logo'] ?: null);
        if (!empty($d['supprimer_logo']) && $logo === null) {
            $logoFinal = null;
        }
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $pdo->prepare('UPDATE entreprise_infos SET raison_sociale=?, adresse=?, telephone=?, email=?, site_web=?, identifiants=?, coordonnees_bancaires=?, devis_conditions=?, devis_validite_jours=?, logo=?, updated_at=? WHERE id=?')
                ->execute([$vals['raison_sociale'], $vals['adresse'], $vals['telephone'], $vals['email'], $vals['site_web'], $vals['identifiants'], $vals['coordonnees_bancaires'], $vals['devis_conditions'], $jours, $logoFinal, $now, $id]);
        } else {
            $pdo->prepare('INSERT INTO entreprise_infos (filiale_id, raison_sociale, adresse, telephone, email, site_web, identifiants, coordonnees_bancaires, devis_conditions, devis_validite_jours, logo, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$filialeId, $vals['raison_sociale'], $vals['adresse'], $vals['telephone'], $vals['email'], $vals['site_web'], $vals['identifiants'], $vals['coordonnees_bancaires'], $vals['devis_conditions'], $jours, $logoFinal, $now]);
        }
    }

    /** Logo en « data URI » pour l'aperçu du devis (pas de lien public vers le fichier). */
    public static function logoDataUri(array $info): ?string
    {
        if (empty($info['logo'])) {
            return null;
        }
        $chemin = Storage::path('uploads/logos/' . basename((string) $info['logo']));
        if (!is_file($chemin)) {
            return null;
        }
        $ext = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        return 'data:image/' . ($ext === 'png' ? 'png' : 'jpeg') . ';base64,' . base64_encode((string) file_get_contents($chemin));
    }
}
