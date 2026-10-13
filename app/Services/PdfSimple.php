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
    private string $flux = '';
    private float $y = 0;
    private int $numPage = 0;
    private string $pied;

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
            $objets[$idPage] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $idFlux . ' 0 R >>';
            $comp = function_exists('gzcompress') ? gzcompress($contenu, 6) : false;
            $objets[$idFlux] = $comp !== false
                ? "<< /Length " . strlen($comp) . " /Filter /FlateDecode >>\nstream\n" . $comp . "\nendstream"
                : "<< /Length " . strlen($contenu) . " >>\nstream\n" . $contenu . "\nendstream";
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
        $this->texte(self::MARGE, 24, $pied, 8, false, [0.55, 0.55, 0.55]);
        $this->pages[] = $this->flux;
        $this->flux = '';
    }

    private function assurerPlace(float $hauteur): void
    {
        if ($this->y - $hauteur < self::MARGE + 14) {
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
