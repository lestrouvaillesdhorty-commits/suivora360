<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Storage;

/** Fiche « Mon entreprise » (une par filiale) : en-tête, mentions et conditions des devis. */
class EntrepriseInfo
{
    public const CHAMPS = ['raison_sociale', 'adresse', 'telephone', 'email', 'site_web', 'identifiants', 'coordonnees_bancaires', 'devis_conditions'];
    /** Champs ajoutés par la migration V50 (enregistrés seulement si les colonnes existent). */
    public const CHAMPS_V50 = ['forme_juridique', 'capital', 'pays', 'regime_tva', 'couleur', 'mise_en_page', 'mentions_pied'];
    public const COULEUR_DEFAUT = '#0B7C8A';
    public const MISES_EN_PAGE = ['classique' => 'Classique', 'moderne' => 'Moderne', 'sobre' => 'Sobre'];
    public const COULEURS_PROPOSEES = ['#0B7C8A', '#2F6FED', '#1B2B3A', '#B4570F', '#6A3FB5'];

    /**
     * Valeurs de départ par pays : l'entreprise peut tout modifier. 'ident' = aide à la saisie des numéros d'immatriculation.
     * « autre » laisse tous les champs libres.
     */
    public const PAYS = [
        'FR' => ['nom' => 'France', 'ident' => "SIRET : …\nRCS : …\nN° TVA intracommunautaire : FR…", 'regimes' => ['TVA sur les débits', 'TVA sur les encaissements', 'TVA non applicable, art. 293 B du CGI']],
        'CM' => ['nom' => 'Cameroun', 'ident' => "RCCM : …\nNIU : …\nN° contribuable : …", 'regimes' => ['Assujetti à la TVA (19,25 %)', 'Non assujetti à la TVA']],
        'SN' => ['nom' => 'Sénégal', 'ident' => "NINEA : …\nRCCM : …", 'regimes' => ['Assujetti à la TVA', 'Non assujetti à la TVA']],
        'CI' => ['nom' => "Côte d'Ivoire", 'ident' => "RCCM : …\nN° CC (compte contribuable) : …", 'regimes' => ['Assujetti à la TVA', 'Non assujetti à la TVA']],
        'BE' => ['nom' => 'Belgique', 'ident' => "N° d'entreprise (BCE) : …\nN° TVA : BE…", 'regimes' => ['Assujetti à la TVA', 'Franchise de TVA (petites entreprises)']],
        'MA' => ['nom' => 'Maroc', 'ident' => "ICE : …\nRC : …\nIF : …", 'regimes' => ['Assujetti à la TVA', 'Non assujetti à la TVA']],
        'AUTRE' => ['nom' => 'Autre pays', 'ident' => "N° d'immatriculation : …\nN° de TVA / identifiant fiscal : …", 'regimes' => []],
    ];
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
        $vide = array_fill_keys(array_merge(self::CHAMPS, self::CHAMPS_V50), '') + ['logo' => '', 'devis_validite_jours' => 30];
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
        $v50 = [];
        foreach (self::CHAMPS_V50 as $c) {
            if (\App\Core\Schema::colonne('entreprise_infos', $c)) {
                $val = trim((string) ($d[$c] ?? ''));
                if ($c === 'couleur') { $val = preg_match('/^#[0-9A-Fa-f]{6}$/', $val) ? strtoupper($val) : ''; }
                if ($c === 'mise_en_page') { $val = isset(self::MISES_EN_PAGE[$val]) ? $val : 'classique'; }
                if ($c === 'pays') { $val = isset(self::PAYS[$val]) ? $val : ''; }
                $v50[$c] = $val;
            }
        }
        if ($id) {
            $pdo->prepare('UPDATE entreprise_infos SET raison_sociale=?, adresse=?, telephone=?, email=?, site_web=?, identifiants=?, coordonnees_bancaires=?, devis_conditions=?, devis_validite_jours=?, logo=?, updated_at=? WHERE id=?')
                ->execute([$vals['raison_sociale'], $vals['adresse'], $vals['telephone'], $vals['email'], $vals['site_web'], $vals['identifiants'], $vals['coordonnees_bancaires'], $vals['devis_conditions'], $jours, $logoFinal, $now, $id]);
        } else {
            $pdo->prepare('INSERT INTO entreprise_infos (filiale_id, raison_sociale, adresse, telephone, email, site_web, identifiants, coordonnees_bancaires, devis_conditions, devis_validite_jours, logo, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$filialeId, $vals['raison_sociale'], $vals['adresse'], $vals['telephone'], $vals['email'], $vals['site_web'], $vals['identifiants'], $vals['coordonnees_bancaires'], $vals['devis_conditions'], $jours, $logoFinal, $now]);
        }
        if ($v50) {
            $set = implode(', ', array_map(fn($c) => $c . ' = ?', array_keys($v50)));
            $pdo->prepare('UPDATE entreprise_infos SET ' . $set . ' WHERE filiale_id = ?')->execute(array_merge(array_values($v50), [$filialeId]));
        }
    }

    /** Couleur principale des documents (défaut turquoise). */
    public static function couleurEffective(array $info): string
    {
        $c = (string) ($info['couleur'] ?? '');
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $c) ? strtoupper($c) : self::COULEUR_DEFAUT;
    }

    public static function miseEnPage(array $info): string
    {
        $m = (string) ($info['mise_en_page'] ?? '');
        return isset(self::MISES_EN_PAGE[$m]) ? $m : 'classique';
    }

    /** « SAS au capital de 10 000 € » + régime de TVA, pour le pied de page. */
    public static function ligneJuridique(array $info): string
    {
        $forme = trim((string) ($info['forme_juridique'] ?? ''));
        $cap = trim((string) ($info['capital'] ?? ''));
        $p = [];
        if ($forme !== '') { $p[] = $forme . ($cap !== '' ? ' au capital de ' . $cap : ''); }
        elseif ($cap !== '') { $p[] = 'Capital : ' . $cap; }
        if (trim((string) ($info['regime_tva'] ?? '')) !== '') { $p[] = 'Régime de TVA : ' . trim((string) $info['regime_tva']); }
        return implode('  ·  ', $p);
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
