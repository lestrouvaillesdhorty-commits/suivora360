<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Schema;

/**
 * Page publique des départs : liste en lecture seule des expéditions que l'entreprise choisit d'afficher.
 * N'expose que trajet, mode, dates, places restantes et tarif affiché : jamais de client, de réservation ni de coût d'achat.
 */
class DepartsPublics
{
    public const UNITES = ['m3' => 'm³', 'kg' => 'kg', 'colis' => 'colis', 'palette' => 'palette'];
    private const CACHE_SECONDES = 300;

    public static function disponible(): bool
    {
        return Schema::colonne('entreprise_infos', 'depart_token') && Schema::colonne('expeditions', 'public_affiche');
    }

    /** Réglages de l'entreprise (filiale) : actif, token, numéro WhatsApp. */
    public static function reglages(int $filialeId): array
    {
        $vide = ['actif' => 0, 'token' => '', 'whatsapp' => ''];
        if (!self::disponible()) {
            return $vide;
        }
        $st = Database::connection()->prepare('SELECT depart_actif, depart_token, depart_whatsapp FROM entreprise_infos WHERE filiale_id = ? LIMIT 1');
        $st->execute([$filialeId]);
        $r = $st->fetch();
        return $r ? ['actif' => (int) $r['depart_actif'], 'token' => (string) $r['depart_token'], 'whatsapp' => (string) $r['depart_whatsapp']] : $vide;
    }

    public static function enregistrer(int $filialeId, bool $actif, string $whatsapp, bool $nouveauLien = false): void
    {
        if (!self::disponible()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id, depart_token FROM entreprise_infos WHERE filiale_id = ? LIMIT 1');
        $st->execute([$filialeId]);
        $ligne = $st->fetch();
        if (!$ligne) {
            $pdo->prepare('INSERT INTO entreprise_infos (filiale_id, updated_at) VALUES (?, ?)')->execute([$filialeId, date('Y-m-d H:i:s')]);
            $ligne = ['depart_token' => ''];
        }
        $token = (string) $ligne['depart_token'];
        if ($token === '' || $nouveauLien) {
            $token = bin2hex(random_bytes(10));
        }
        $tel = preg_replace('/[^0-9+]/', '', $whatsapp);
        $pdo->prepare('UPDATE entreprise_infos SET depart_actif = ?, depart_token = ?, depart_whatsapp = ? WHERE filiale_id = ?')
            ->execute([$actif ? 1 : 0, $token, mb_substr((string) $tel, 0, 30), $filialeId]);
        self::viderCache($filialeId);
    }

    /** Réglages d'affichage d'une expédition. */
    public static function enregistrerExpedition(int $expeditionId, array $d): void
    {
        if (!Schema::colonne('expeditions', 'public_affiche')) {
            return;
        }
        $num = function ($v) {
            $v = str_replace([' ', ','], ['', '.'], trim((string) $v));
            return $v !== '' && is_numeric($v) && (float) $v >= 0 ? round((float) $v, 2) : null;
        };
        $unite = isset(self::UNITES[$d['public_tarif_unite'] ?? '']) ? $d['public_tarif_unite'] : null;
        Database::connection()->prepare('UPDATE expeditions SET public_affiche = ?, public_tarif_alim = ?, public_tarif_non_alim = ?, public_tarif_unite = ? WHERE id = ?')
            ->execute([!empty($d['public_affiche']) ? 1 : 0, $num($d['public_tarif_alim'] ?? ''), $num($d['public_tarif_non_alim'] ?? ''), $unite, $expeditionId]);
        $exp = Expedition::find($expeditionId);
        if ($exp) {
            self::viderCache((int) $exp['filiale_id']);
        }
    }

    public static function findByToken(string $token): ?array
    {
        if (!self::disponible() || !preg_match('/^[a-f0-9]{20}$/', $token)) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT * FROM entreprise_infos WHERE depart_token = ? AND depart_actif = 1 LIMIT 1');
        $st->execute([$token]);
        return $st->fetch() ?: null;
    }

    private static function fichierCache(int $filialeId): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/departs_' . $filialeId . '.json';
    }

    public static function viderCache(int $filialeId): void
    {
        $f = self::fichierCache($filialeId);
        if (is_file($f)) {
            @unlink($f);
        }
    }

    /** Départs affichables (ouverts, non clos, cochés « afficher »), prêts pour la page publique. Mis en cache quelques minutes. */
    public static function departs(int $filialeId): array
    {
        $f = self::fichierCache($filialeId);
        if (is_file($f) && time() - filemtime($f) < self::CACHE_SECONDES) {
            $c = json_decode((string) file_get_contents($f), true);
            if (is_array($c) && ($c['jour'] ?? '') === date('Y-m-d')) {
                return $c['departs'];
            }
        }
        $st = Database::connection()->prepare("SELECT * FROM expeditions WHERE filiale_id = ? AND statut = 'ouverte' AND public_affiche = 1 ORDER BY date_depart IS NULL, date_depart, id");
        $st->execute([$filialeId]);
        $aujourdhui = date('Y-m-d');
        $liste = [];
        foreach ($st->fetchAll() as $e) {
            $cloture = (string) ($e['cloture_reservations'] ?? '');
            if ($cloture !== '' && $cloture < $aujourdhui) {
                continue;
            }
            $depart = (string) ($e['date_depart'] ?? '');
            if ($depart !== '' && $depart < $aujourdhui) {
                continue;
            }
            $cap = (float) ($e['capacite_volume'] ?? 0);
            $places = null;
            $etat = 'sur_demande';
            if (Expedition::modeAchat($e) === 'capacite' && $cap > 0) {
                $b = Expedition::bilan($e);
                $places = max(0, round($cap - (float) $b['volume'], 1));
                $etat = $places <= 0 ? 'complet' : ($places <= max(2, $cap * 0.2) ? 'peu' : 'ok');
            }
            $liste[] = [
                'origine' => (string) $e['origine'], 'destination' => (string) $e['destination'], 'mode' => (string) $e['mode'],
                'type' => (string) $e['type'], 'depart' => $depart, 'cloture' => $cloture,
                'places' => $places, 'etat' => $etat,
                'tarif_alim' => $e['public_tarif_alim'] !== null && $e['public_tarif_alim'] !== '' ? (float) $e['public_tarif_alim'] : null,
                'tarif_non_alim' => $e['public_tarif_non_alim'] !== null && $e['public_tarif_non_alim'] !== '' ? (float) $e['public_tarif_non_alim'] : null,
                'unite' => (string) ($e['public_tarif_unite'] ?? ''), 'devise' => (string) ($e['devise'] ?: 'EUR'),
            ];
        }
        $dir = dirname($f);
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            @file_put_contents($f, json_encode(['jour' => $aujourdhui, 'departs' => $liste], JSON_UNESCAPED_UNICODE));
        }
        return $liste;
    }

    /** Message WhatsApp prérempli pour un départ. */
    public static function messageWhatsapp(array $d, string $produit): string
    {
        $modes = Expedition::MODES;
        $date = $d['depart'] !== '' ? date('d/m/Y', strtotime($d['depart'])) : 'date à confirmer';
        return "Bonjour, je voudrais réserver sur le départ du $date : " . $d['origine'] . ' → ' . $d['destination']
            . ' (' . strtolower($modes[$d['mode']] ?? $d['mode']) . ").\nType de produit : $produit\nQuantité estimée : \nMon nom : ";
    }
}
