<?php

namespace App\Services;

/**
 * Générateur PDF minimal en PHP pur (aucune librairie : hébergement
 * mutualisé). Format A4, polices standard Helvetica / Helvetica-Bold
 * (encodage WinAnsi, donc accents français et €), texte, titres, tableaux
 * avec retour à la ligne, pagination automatique et pied de page.
 */
class PdfSimple
{
    private const W = 595.28;
    private const H = 841.89;
    private const MARGE = 42.0;

    /** Largeurs Helvetica (1/1000 em) pour les caractères ASCII 32..126. */
    private const LARGEURS = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
    ];

    private array $pages = [];
    /** Images JPEG du document : [['data','w','h','cs']…] ; $pageImgs[page] = index des images utilisées. */
    private array $imgs = [];
    private array $pageImgs = [];
    private array $curImgs = [];
    private string $flux = '';
    private float $y = 0;
    private int $numPage = 0;
    private string $pied;
    private string $pied2 = '';

    /** Ligne d'informations de l'entreprise, imprimée au-dessus du pied de page sur chaque page. */
    public function piedEntreprise(string $ligne): void
    {
        $this->pied2 = $ligne;
    }

    public function __construct(string $pied = '')
    {
        $this->pied = $pied;
        $this->nouvellePage();
    }

    // ---------------------------------------------------------------- API

    public function titre(string $texte, float $taille = 18): void
    {
        $this->ecrireLignes($this->couper($texte, $taille, self::W - 2 * self::MARGE, true), $taille, true, [0.12, 0.14, 0.2], 4);
    }

    public function sousTitre(string $texte): void
    {
        $this->ecrireLignes([$texte], 9.5, false, [0.4, 0.4, 0.4], 10);
    }

    public function section(string $texte): void
    {
        $this->assurerPlace(46);
        $this->y -= 10;
        $this->ecrireLignes([$texte], 12, true, [0.16, 0.18, 0.25], 3);
        $this->ligne(self::MARGE, $this->y + 2, self::W - self::MARGE, [0.85, 0.86, 0.9]);
        $this->y -= 5;
    }

    public function paragraphe(string $texte, float $taille = 10): void
    {
        foreach (preg_split('/\R/u', $texte) as $bloc) {
            $lignes = $this->couper($bloc === '' ? ' ' : $bloc, $taille, self::W - 2 * self::MARGE, false);
            $this->ecrireLignes($lignes, $taille, false, [0.1, 0.1, 0.1], 1);
        }
        $this->y -= 3;
    }

    /** Tableau à colonnes : $largeurs en parts relatives. */
    public function tableau(array $entetes, array $lignes, array $largeurs): void
    {
        $total = array_sum($largeurs);
        $utile = self::W - 2 * self::MARGE;
        $cols = array_map(fn($l) => $utile * $l / $total, $largeurs);
        $taille = 8.5;

        $dessinerLigne = function (array $cellules, bool $entete) use ($cols, $taille) {
            $coupees = [];
            $nb = 1;
            foreach ($cellules as $i => $c) {
                $coupees[$i] = $this->couper((string) $c, $taille, $cols[$i] - 8, $entete);
                $nb = max($nb, count($coupees[$i]));
            }
            $hauteur = $nb * ($taille + 2.5) + 6;
            $this->assurerPlace($hauteur + 4);
            $x = self::MARGE;
            if ($entete) {
                $this->rect(self::MARGE, $this->y - $hauteur + 2, $utile = array_sum($cols), $hauteur, [0.94, 0.95, 0.97]);
            }
            foreach ($coupees as $i => $lignesCellule) {
                $yy = $this->y - $taille - 1;
                foreach ($lignesCellule as $l) {
                    $this->texte($x + 4, $yy, $l, $taille, $entete, [0.1, 0.1, 0.1]);
                    $yy -= $taille + 2.5;
                }
                $x += $cols[$i];
            }
            $this->y -= $hauteur;
            $this->ligne(self::MARGE, $this->y + 2, self::MARGE + array_sum($cols), [0.88, 0.89, 0.92]);
        };

        $dessinerLigne($entetes, true);
        foreach ($lignes as $l) {
            $dessinerLigne($l, false);
        }
        $this->y -= 4;
    }

    /** Paires libellé / valeur (détails de la demande). */
    public function paires(array $paires): void
    {
        $this->tableau(['Information', 'Valeur'], array_map(fn($k, $v) => [$k, $v], array_keys($paires), array_values($paires)), [2, 3]);
    }

    public function rendre(): string
    {
        $this->cloturerPage();
        $objets = [];
        $nbPages = count($this->pages);
        // 1: catalogue, 2: pages, 3: Helvetica, 4: Helvetica-Bold, ensuite (page, contenu) par page
        $objets[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $nbPages; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objets[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nbPages . ' >>';
        $objets[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objets[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $i => $contenu) {
            $idPage = 5 + $i * 2;
            $idFlux = $idPage + 1;
            $xobj = '';
            foreach ($this->pageImgs[$i] ?? [] as $n) {
                $xobj .= '/Im' . $n . ' ' . (5 + $nbPages * 2 + $n) . ' 0 R ';
            }
            $objets[$idPage] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . ($xobj !== '' ? ' /XObject << ' . $xobj . '>>' : '') . ' >> /Contents ' . $idFlux . ' 0 R >>';
            $comp = function_exists('gzcompress') ? gzcompress($contenu, 6) : false;
            $objets[$idFlux] = $comp !== false
                ? "<< /Length " . strlen($comp) . " /Filter /FlateDecode >>\nstream\n" . $comp . "\nendstream"
                : "<< /Length " . strlen($contenu) . " >>\nstream\n" . $contenu . "\nendstream";
        }
        foreach ($this->imgs as $n => $im) {
            $objets[5 + $nbPages * 2 + $n] = '<< /Type /XObject /Subtype /Image /Width ' . $im['w'] . ' /Height ' . $im['h']
                . ' /ColorSpace /' . $im['cs'] . ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($im['data']) . " >>\nstream\n" . $im['data'] . "\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        ksort($objets);
        foreach ($objets as $n => $corps) {
            $offsets[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . $corps . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objets));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($n = 1; $n <= $max; $n++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$n] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }


    /** Planche de photos (3 par ligne) : $images = [['data'=>jpeg,'w'=>..,'h'=>..,'cs'=>'DeviceRGB'|'DeviceGray']…]. */
    public function photos(array $images, string $titre = 'Photos'): void
    {
        if (!$images) {
            return;
        }
        $this->section($titre);
        $colonnes = 3;
        $gap = 10.0;
        $cell = (self::W - 2 * self::MARGE - ($colonnes - 1) * $gap) / $colonnes;
        $boxH = $cell * 0.75;
        foreach (array_chunk($images, $colonnes) as $ligne) {
            $this->assurerPlace($boxH + 8);
            $haut = $this->y;
            foreach ($ligne as $k => $im) {
                $r = min($cell / $im['w'], $boxH / $im['h']);
                $w = $im['w'] * $r;
                $h = $im['h'] * $r;
                $x = self::MARGE + $k * ($cell + $gap) + ($cell - $w) / 2;
                $yb = $haut - $boxH + ($boxH - $h) / 2;
                $this->imgs[] = $im;
                $n = count($this->imgs) - 1;
                $this->curImgs[] = $n;
                $this->flux .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n", $w, $h, $x, $yb, $n);
            }
            $this->y = $haut - $boxH - 8;
        }
    }


    // ------------------------------------------- mise en page « devis » (identique à l'aperçu)

    private array $teal = [0.043, 0.486, 0.541];

    /** Couleur principale du document (#RRGGBB) : bandeau, tableau, total. */
    public function couleur(?string $hex): void
    {
        if ($hex && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            $this->teal = [hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255];
        }
    }

    public function espace(float $h): void
    {
        $this->y -= $h;
    }

    /** En-tête : à gauche logo + nom + coordonnées, à droite titre du document ; filet turquoise dessous. */
    public function enteteDevis(?array $logo, string $nom, array $coord, string $titre, array $droite): void
    {
        $largG = (self::W - 2 * self::MARGE) * 0.58;
        $hG = 0.0;
        $lh = 0.0;
        if ($logo && $logo['w'] > 0 && $logo['h'] > 0) {
            $r = min(150 / $logo['w'], 46 / $logo['h']);
            $lw = $logo['w'] * $r;
            $lh = $logo['h'] * $r;
            $hG += $lh + 6;
        }
        $lNom = $this->couper($nom, 15, $largG, true);
        $hG += count($lNom) * 18;
        $lCoord = [];
        foreach ($coord as $c) {
            foreach ($this->couper($c, 8.5, $largG, false) as $l) {
                $lCoord[] = $l;
            }
        }
        $hG += count($lCoord) * 11.5 + 2;
        $lTitre = $this->couper($titre, 14, (self::W - 2 * self::MARGE) * 0.4, true);
        $hD = count($lTitre) * 17 + count($droite) * 12.5;
        $h = max($hG, $hD);
        $this->assurerPlace($h + 16);
        $haut = $this->y;
        $yy = $haut;
        if ($logo && $lh > 0) {
            $this->imgs[] = $logo;
            $n = count($this->imgs) - 1;
            $this->curImgs[] = $n;
            $this->flux .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n", $lw, $lh, self::MARGE, $yy - $lh, $n);
            $yy -= $lh + 6;
        }
        foreach ($lNom as $l) {
            $yy -= 14;
            $this->texte(self::MARGE, $yy, $l, 15, true, [0.11, 0.125, 0.19]);
            $yy -= 4;
        }
        $yy -= 2;
        foreach ($lCoord as $l) {
            $yy -= 8.5;
            $this->texte(self::MARGE, $yy, $l, 8.5, false, [0.33, 0.35, 0.4]);
            $yy -= 3;
        }
        $xd = self::W - self::MARGE;
        $yy = $haut;
        foreach ($lTitre as $l) {
            $yy -= 14;
            $this->texte($xd - $this->largeur($l, 14, true), $yy, $l, 14, true, [0.11, 0.125, 0.19]);
            $yy -= 3;
        }
        foreach ($droite as $l) {
            $yy -= 9.5;
            $this->texte($xd - $this->largeur($l, 9.5, false), $yy, $l, 9.5, false, [0.33, 0.35, 0.4]);
            $yy -= 3;
        }
        $this->y = $haut - $h - 8;
        $this->rect(self::MARGE, $this->y, self::W - 2 * self::MARGE, 2.5, $this->teal);
        $this->y -= 16;
    }

    /** Cadres d'information côte à côte (fond clair) : [[titre, [lignes]], …] ; la 1re ligne est en gras. */
    public function blocsInfo(array $blocs): void
    {
        $n = max(1, count($blocs));
        $gap = 12.0;
        $larg = (self::W - 2 * self::MARGE - ($n - 1) * $gap) / $n;
        $rendu = [];
        $hMax = 0.0;
        foreach ($blocs as [$titre, $lignes]) {
            $ls = [];
            foreach ($lignes as $k => $l) {
                foreach ($this->couper((string) $l, 9.5, $larg - 20, $k === 0) as $j => $c) {
                    $ls[] = [$c, $k === 0];
                }
            }
            $rendu[] = [$titre, $ls];
            $hMax = max($hMax, 12 + 14 + count($ls) * 12.5 + 8);
        }
        $this->assurerPlace($hMax + 6);
        $haut = $this->y;
        foreach ($rendu as $i => [$titre, $ls]) {
            $x = self::MARGE + $i * ($larg + $gap);
            $this->rect($x, $haut - $hMax, $larg, $hMax, [0.949, 0.969, 0.973]);
            $this->texte($x + 10, $haut - 17, $titre, 8.5, true, [0.4, 0.42, 0.47]);
            $yy = $haut - 17;
            foreach ($ls as [$c, $gras]) {
                $yy -= 12.5;
                $this->texte($x + 10, $yy, $c, 9.5, $gras, [0.11, 0.125, 0.19]);
            }
        }
        $this->y = $haut - $hMax - 14;
    }

    /**
     * Tableau à en-tête turquoise. Cellule = texte, ou [texte, détail] (détail en petit gris dessous).
     * $droite = indices des colonnes alignées à droite.
     */
    public function tableauDevis(array $entetes, array $lignes, array $largeurs, array $droite = []): void
    {
        $utile = self::W - 2 * self::MARGE;
        $total = array_sum($largeurs);
        $cols = array_map(fn($l) => $utile * $l / $total, $largeurs);
        $taille = 9;
        $ligne = function (array $cellules, bool $entete) use ($cols, $taille, $droite, $utile) {
            $pret = [];
            $nb = 1;
            foreach ($cellules as $i => $c) {
                $txt = is_array($c) ? (string) $c[0] : (string) $c;
                $det = is_array($c) ? (string) ($c[1] ?? '') : '';
                $a = $this->couper($txt, $taille, $cols[$i] - 12, $entete);
                $b = $det !== '' ? $this->couper($det, 7.5, $cols[$i] - 12, false) : [];
                $pret[$i] = [$a, $b];
                $nb = max($nb, count($a) + (count($b) ? count($b) * 0.85 : 0));
            }
            $h = $nb * ($taille + 3) + 9;
            $this->assurerPlace($h + 4);
            if ($entete) {
                $this->rect(self::MARGE, $this->y - $h, $utile, $h, $this->teal);
            }
            $x = self::MARGE;
            foreach ($pret as $i => [$a, $b]) {
                $yy = $this->y - 5;
                foreach ($a as $l) {
                    $yy -= $taille;
                    $px = in_array($i, $droite, true) ? $x + $cols[$i] - 6 - $this->largeur($l, $taille, $entete) : $x + 6;
                    $this->texte($px, $yy, $l, $taille, $entete, $entete ? [1, 1, 1] : [0.11, 0.125, 0.19]);
                    $yy -= 3;
                }
                foreach ($b as $l) {
                    $yy -= 7.5;
                    $this->texte($x + 6, $yy, $l, 7.5, false, [0.4, 0.42, 0.47]);
                    $yy -= 2.5;
                }
                $x += $cols[$i];
            }
            $this->y -= $h;
            if (!$entete) {
                $this->ligne(self::MARGE, $this->y, self::MARGE + $utile, [0.9, 0.91, 0.94]);
            }
        };
        $ligne($entetes, true);
        foreach ($lignes as $l) {
            $ligne($l, false);
        }
        $this->y -= 8;
    }

    /** Bloc de totaux aligné à droite ; la dernière ligne (TTC) est mise en valeur. */
    public function totauxDevis(array $lignes, string $libelleTtc, string $valeurTtc): void
    {
        $larg = 240.0;
        $x1 = self::W - self::MARGE - $larg;
        $x2 = self::W - self::MARGE;
        $this->assurerPlace(count($lignes) * 17 + 40);
        foreach ($lignes as [$k, $v]) {
            $this->y -= 13;
            $this->texte($x1, $this->y, $k, 9.5, false, [0.11, 0.125, 0.19]);
            $this->texte($x2 - $this->largeur($v, 9.5, false), $this->y, $v, 9.5, false, [0.11, 0.125, 0.19]);
            $this->y -= 4;
        }
        $this->y -= 3;
        $this->rect($x1, $this->y, $larg, 1.6, $this->teal);
        $this->y -= 20;
        $this->texte($x1, $this->y, $libelleTtc, 13, true, [0.11, 0.125, 0.19]);
        $this->texte($x2 - $this->largeur($valeurTtc, 13, true), $this->y, $valeurTtc, 13, true, [0.11, 0.125, 0.19]);
        $this->y -= 16;
    }

    /** Intitulé en gras suivi d'un paragraphe (remarques, conditions…). */
    public function noteTitree(string $titre, string $texte, float $taille = 9): void
    {
        $this->assurerPlace(40);
        $this->y -= 8;
        $this->ecrireLignes([$titre], 10, true, [0.11, 0.125, 0.19], 1);
        $this->paragraphe($texte, $taille);
    }

    /** Zone de signature : texte à gauche, cadre en pointillés à droite. */
    public function signatureDevis(string $titre, string $aide): void
    {
        $this->assurerPlace(110);
        $this->y -= 14;
        $haut = $this->y;
        $moitie = (self::W - 2 * self::MARGE - 18) / 2;
        $this->texte(self::MARGE, $haut - 10, $titre, 10, true, [0.11, 0.125, 0.19]);
        $yy = $haut - 10;
        foreach ($this->couper($aide, 8, $moitie, false) as $l) {
            $yy -= 11;
            $this->texte(self::MARGE, $yy, $l, 8, false, [0.4, 0.42, 0.47]);
        }
        $x = self::MARGE + $moitie + 18;
        $this->flux .= sprintf("0.75 0.88 0.9 RG 1 w [4 3] 0 d %.2F %.2F %.2F %.2F re S [] 0 d\n", $x, $haut - 80, $moitie, 80);
        $this->y = $haut - 92;
    }

    // ------------------------------------------------------------- interne

    private function nouvellePage(): void
    {
        if ($this->numPage > 0) {
            $this->cloturerPage();
        }
        $this->numPage++;
        $this->flux = '';
        $this->y = self::H - self::MARGE;
    }

    private function cloturerPage(): void
    {
        if ($this->flux === '' && $this->numPage === 0) {
            return;
        }
        $pied = trim($this->pied . '   Page ' . $this->numPage);
        if ($this->pied2 !== '') {
            $this->ligne(self::MARGE, 46, self::W - self::MARGE, [0.85, 0.86, 0.9]);
            $reste = $this->pied2;
            $yy = 36;
            foreach (array_slice($this->couper($reste, 7.5, self::W - 2 * self::MARGE, false), 0, 2) as $l) {
                $this->texte(self::MARGE, $yy, $l, 7.5, false, [0.4, 0.43, 0.5]);
                $yy -= 9;
            }
        }
        $this->texte(self::MARGE, 14, $pied, 8, false, [0.55, 0.55, 0.55]);
        $this->pages[] = $this->flux;
        $this->pageImgs[] = $this->curImgs;
        $this->curImgs = [];
        $this->flux = '';
    }

    private function assurerPlace(float $hauteur): void
    {
        if ($this->y - $hauteur < self::MARGE + ($this->pied2 !== '' ? 36 : 14)) {
            $this->nouvellePage();
        }
    }

    private function ecrireLignes(array $lignes, float $taille, bool $gras, array $couleur, float $apres): void
    {
        foreach ($lignes as $l) {
            $this->assurerPlace($taille + 4);
            $this->y -= $taille;
            $this->texte(self::MARGE, $this->y, $l, $taille, $gras, $couleur);
            $this->y -= 3;
        }
        $this->y -= $apres;
    }

    private function texte(float $x, float $y, string $texte, float $taille, bool $gras, array $couleur): void
    {
        $this->flux .= sprintf("BT /%s %.2f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n",
            $gras ? 'F2' : 'F1', $taille, $couleur[0], $couleur[1], $couleur[2], $x, $y, $this->echapper($texte));
    }

    private function ligne(float $x1, float $y, float $x2, array $couleur): void
    {
        $this->flux .= sprintf("%.3f %.3f %.3f RG 0.6 w %.2f %.2f m %.2f %.2f l S\n", $couleur[0], $couleur[1], $couleur[2], $x1, $y, $x2, $y);
    }

    private function rect(float $x, float $y, float $w, float $h, array $couleur): void
    {
        $this->flux .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n", $couleur[0], $couleur[1], $couleur[2], $x, $y, $w, $h);
    }

    /** UTF-8 -> WinAnsi (CP1252) échappé pour une chaîne PDF. */
    private function echapper(string $texte): string
    {
        $texte = str_replace(["\u{00A0}", "\u{202F}"], ' ', $texte);
        $win = mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $win);
    }

    private function largeur(string $texte, float $taille, bool $gras): float
    {
        static $accents = null;
        if ($accents === null) {
            $accents = [];
            foreach (['àáâãäå' => 'a', 'çĉ' => 'c', 'èéêë' => 'e', 'ìíîï' => 'i', 'ñ' => 'n', 'òóôõöø' => 'o', 'ùúûü' => 'u', 'ýÿ' => 'y',
                      'ÀÁÂÃÄÅ' => 'A', 'Ç' => 'C', 'ÈÉÊË' => 'E', 'ÌÍÎÏ' => 'I', 'Ñ' => 'N', 'ÒÓÔÕÖØ' => 'O', 'ÙÚÛÜ' => 'U', 'Ý' => 'Y'] as $liste => $base) {
                foreach (preg_split('//u', $liste, -1, PREG_SPLIT_NO_EMPTY) as $c) {
                    $accents[$c] = $base;
                }
            }
        }
        $w = 0;
        foreach (preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) as $c) {
            $c = $accents[$c] ?? $c;
            $o = strlen($c) === 1 ? ord($c) : 0;
            $w += ($o >= 32 && $o <= 126) ? self::LARGEURS[$o - 32] : 556;
        }
        return $w * $taille / 1000 * ($gras ? 1.06 : 1.0);
    }

    /** Coupe un texte en lignes qui tiennent dans $largeurMax. */
    private function couper(string $texte, float $taille, float $largeurMax, bool $gras): array
    {
        $texte = trim(preg_replace('/[ \t]+/u', ' ', str_replace(["\r", "\n"], ' ', $texte)));
        if ($texte === '') {
            return [''];
        }
        $lignes = [];
        $courante = '';
        foreach (explode(' ', $texte) as $mot) {
            // Mot plus long que la ligne : découpe dure.
            while ($this->largeur($mot, $taille, $gras) > $largeurMax && mb_strlen($mot) > 1) {
                $n = mb_strlen($mot);
                $coupe = $n;
                while ($coupe > 1 && $this->largeur(mb_substr($mot, 0, $coupe), $taille, $gras) > $largeurMax) {
                    $coupe--;
                }
                if ($courante !== '') {
                    $lignes[] = $courante;
                    $courante = '';
                }
                $lignes[] = mb_substr($mot, 0, $coupe);
                $mot = mb_substr($mot, $coupe);
            }
            $essai = $courante === '' ? $mot : $courante . ' ' . $mot;
            if ($courante !== '' && $this->largeur($essai, $taille, $gras) > $largeurMax) {
                $lignes[] = $courante;
                $courante = $mot;
            } else {
                $courante = $essai;
            }
        }
        if ($courante !== '') {
            $lignes[] = $courante;
        }
        return $lignes;
    }
}
