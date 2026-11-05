<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Storage;
use App\Models\DossierPieceJointe;

/**
 * Photos d'un produit rattachées à une offre fournisseur. Chaque photo est un document du dossier
 * (catégorie « Photos de produits », liée à l'offre) ; la visibilité « client » signifie
 * « à afficher sur le devis », « interne » = visible seulement par l'équipe.
 */
class PhotoProduit
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    public const MAX_PAR_ENVOI = 12;

    /** Photos d'une offre (les plus anciennes d'abord). */
    public static function deOffre(int $dossierId, int $offreId): array
    {
        if ($offreId <= 0 || !DossierPieceJointe::colonneOffre()) {
            return [];
        }
        $st = Database::connection()->prepare(
            "SELECT * FROM dossier_pieces_jointes WHERE dossier_id = ? AND offre_id = ? AND categorie = 'photos_produit' ORDER BY id"
        );
        $st->execute([$dossierId, $offreId]);
        return $st->fetchAll();
    }

    /** Photos à afficher sur le devis d'une cotation (offre de la cotation, visibilité « client »). */
    public static function pourDevis(array $cotation): array
    {
        if (empty($cotation['offre_id']) || empty($cotation['dossier_id'])) {
            return [];
        }
        return array_values(array_filter(
            self::deOffre((int) $cotation['dossier_id'], (int) $cotation['offre_id']),
            fn($p) => ($p['visibilite'] ?? '') === 'client'
        ));
    }

    public static function chemin(array $piece): string
    {
        return Storage::path('uploads/dossiers/' . (int) $piece['dossier_id'] . '/' . $piece['nom_fichier']);
    }

    /**
     * Image réduite et convertie en JPEG pour le PDF et l'aperçu.
     * @return array{data:string,w:int,h:int,cs:string}|null
     */
    public static function jpeg(string $chemin, int $max = 700): ?array
    {
        if (!is_file($chemin)) {
            return null;
        }
        $info = @getimagesize($chemin);
        if (!$info) {
            return null;
        }
        [$w, $h, $type] = $info;
        if (function_exists('imagecreatetruecolor')) {
            $src = null;
            if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) { $src = @imagecreatefromjpeg($chemin); }
            elseif ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) { $src = @imagecreatefrompng($chemin); }
            elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($chemin); }
            if ($src) {
                $k = min(1.0, $max / max($w, $h));
                $nw = max(1, (int) round($w * $k));
                $nh = max(1, (int) round($h * $k));
                $dst = imagecreatetruecolor($nw, $nh);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                ob_start();
                imagejpeg($dst, null, 82);
                $data = (string) ob_get_clean();
                imagedestroy($src);
                imagedestroy($dst);
                return $data !== '' ? ['data' => $data, 'w' => $nw, 'h' => $nh, 'cs' => 'DeviceRGB'] : null;
            }
        }
        // Sans GD : seul un JPEG classique (gris ou RVB) peut être repris tel quel.
        if ($type === IMAGETYPE_JPEG && in_array((int) ($info['channels'] ?? 3), [1, 3], true)) {
            return ['data' => (string) file_get_contents($chemin), 'w' => $w, 'h' => $h, 'cs' => ((int) ($info['channels'] ?? 3)) === 1 ? 'DeviceGray' : 'DeviceRGB'];
        }
        return null;
    }

    /** Photos du devis sous forme d'URI de données (aperçu HTML). */
    public static function urisDevis(array $cotation): array
    {
        $res = [];
        foreach (self::pourDevis($cotation) as $p) {
            $j = self::jpeg(self::chemin($p), 600);
            if ($j) {
                $res[] = 'data:image/jpeg;base64,' . base64_encode($j['data']);
            }
        }
        return $res;
    }

    /** Photos du devis prêtes pour le PDF. */
    public static function imagesDevis(array $cotation): array
    {
        $res = [];
        foreach (self::pourDevis($cotation) as $p) {
            $j = self::jpeg(self::chemin($p), 700);
            if ($j) {
                $res[] = $j;
            }
        }
        return $res;
    }

    /**
     * Enregistre les photos envoyées avec le formulaire d'offre (champ « photos[] » ; « photo_devis[] » = index des
     * photos à afficher sur le devis). Retourne un message d'avertissement ou null.
     */
    public static function enregistrer(array $dossier, array $user, int $offreId): ?string
    {
        $f = $_FILES['photos'] ?? null;
        if (!$f || !is_array($f['name'] ?? null)) {
                return null;
        }
        $surDevis = array_map('intval', (array) ($_POST['photo_devis'] ?? []));
        $ignorees = [];
        $ok = 0;
        foreach ($f['name'] as $i => $nom) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $nom === '') {
                continue;
            }
            if ($ok >= self::MAX_PAR_ENVOI) {
                $ignorees[] = $nom;
                continue;
            }
            $ext = strtolower(pathinfo((string) $nom, PATHINFO_EXTENSION));
            if (($f['error'][$i] ?? 1) !== UPLOAD_ERR_OK || $f['size'][$i] > DossierPieceJointe::TAILLE_MAX
                || !in_array($ext, self::EXTENSIONS, true) || !@getimagesize($f['tmp_name'][$i])) {
                $ignorees[] = $nom;
                continue;
            }
            $fichier = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'][$i], Storage::path('uploads/dossiers/' . $dossier['id'] . '/' . $fichier))) {
                $ignorees[] = $nom;
                continue;
            }
            DossierPieceJointe::create([
                'dossier_id' => $dossier['id'],
                'nom_original' => $nom,
                'nom_fichier' => $fichier,
                'taille' => $f['size'][$i],
                'type_mime' => $f['type'][$i] ?: 'image/jpeg',
                'uploaded_by' => (int) $user['id'],
                'categorie' => 'photos_produit',
                'visibilite' => in_array((int) $i, $surDevis, true) ? 'client' : 'interne',
                'offre_id' => $offreId,
            ]);
            $ok++;
        }
        return $ignorees ? 'Photo(s) non ajoutée(s) (JPG, PNG ou WebP, 10 Mo max) : ' . implode(', ', array_slice($ignorees, 0, 5)) : null;
    }

    /** Modification d'une offre : case « sur le devis » des photos existantes et suppression. */
    public static function miseAJour(array $dossier, int $offreId): void
    {
        $existantes = array_map('intval', (array) ($_POST['photo_exist'] ?? []));
        if (!$existantes) {
            return;
        }
        $sur = array_map('intval', (array) ($_POST['photo_exist_devis'] ?? []));
        $suppr = array_map('intval', (array) ($_POST['photo_suppr'] ?? []));
        $propres = array_column(self::deOffre((int) $dossier['id'], $offreId), null, 'id');
        foreach ($existantes as $id) {
            if (!isset($propres[$id])) {
                continue; // une photo qui n'est pas celle de cette offre n'est jamais touchée
            }
            if (in_array($id, $suppr, true)) {
                @unlink(self::chemin($propres[$id]));
                DossierPieceJointe::delete($id);
            } else {
                DossierPieceJointe::classer($id, 'photos_produit', in_array($id, $sur, true) ? 'client' : 'interne');
            }
        }
    }
}
