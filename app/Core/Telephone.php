<?php

namespace App\Core;

/**
 * Numéros de téléphone internationaux : indicatif + numéro, stockés ensemble
 * dans une seule colonne (« +237 677 00 00 00 ») pour rester compatibles avec
 * tous les écrans qui lisent déjà clients.telephone.
 *
 * Aucun indicatif n'est imposé : la liste couvre les pays courants de
 * l'activité, l'indicatif proposé par défaut vient du pays du client ou de
 * l'habitude de l'entreprise (voir Client::defautsOrganisation()).
 */
class Telephone
{
    /** pays => indicatif */
    public const PAYS_INDICATIFS = [
        'Cameroun' => '+237',
        'France' => '+33',
        "Côte d'Ivoire" => '+225',
        'Sénégal' => '+221',
        'Mali' => '+223',
        'Togo' => '+228',
        'Bénin' => '+229',
        'Gabon' => '+241',
        'Congo (Brazzaville)' => '+242',
        'RD Congo' => '+243',
        'Tchad' => '+235',
        'Guinée équatoriale' => '+240',
        'Centrafrique' => '+236',
        'Burkina Faso' => '+226',
        'Niger' => '+227',
        'Guinée' => '+224',
        'Nigeria' => '+234',
        'Ghana' => '+233',
        'Maroc' => '+212',
        'Tunisie' => '+216',
        'Algérie' => '+213',
        'Belgique' => '+32',
        'Allemagne' => '+49',
        'Royaume-Uni' => '+44',
        'Chine' => '+86',
        'Émirats arabes unis' => '+971',
        'Inde' => '+91',
        'Turquie' => '+90',
        'États-Unis' => '+1',
        // Autres pays (ordre alphabétique) : liste complète pour ne bloquer aucune saisie.
        'Afghanistan' => '+93',
        'Afrique du Sud' => '+27',
        'Albanie' => '+355',
        'Andorre' => '+376',
        'Angola' => '+244',
        'Antigua-et-Barbuda' => '+1268',
        'Arabie saoudite' => '+966',
        'Argentine' => '+54',
        'Arménie' => '+374',
        'Australie' => '+61',
        'Autriche' => '+43',
        'Azerbaïdjan' => '+994',
        'Bahamas' => '+1242',
        'Bahreïn' => '+973',
        'Bangladesh' => '+880',
        'Barbade' => '+1246',
        'Bélarus' => '+375',
        'Belize' => '+501',
        'Bhoutan' => '+975',
        'Birmanie (Myanmar)' => '+95',
        'Bolivie' => '+591',
        'Bosnie-Herzégovine' => '+387',
        'Botswana' => '+267',
        'Brésil' => '+55',
        'Brunei' => '+673',
        'Bulgarie' => '+359',
        'Burundi' => '+257',
        'Cambodge' => '+855',
        'Canada' => '+1',
        'Cap-Vert' => '+238',
        'Chili' => '+56',
        'Chypre' => '+357',
        'Colombie' => '+57',
        'Comores' => '+269',
        'Corée du Nord' => '+850',
        'Corée du Sud' => '+82',
        'Costa Rica' => '+506',
        'Croatie' => '+385',
        'Cuba' => '+53',
        'Danemark' => '+45',
        'Djibouti' => '+253',
        'Dominique' => '+1767',
        'Égypte' => '+20',
        'Salvador' => '+503',
        'Équateur' => '+593',
        'Érythrée' => '+291',
        'Espagne' => '+34',
        'Estonie' => '+372',
        'Eswatini' => '+268',
        'Éthiopie' => '+251',
        'Fidji' => '+679',
        'Finlande' => '+358',
        'Gambie' => '+220',
        'Géorgie' => '+995',
        'Grèce' => '+30',
        'Grenade' => '+1473',
        'Guatemala' => '+502',
        'Guinée-Bissau' => '+245',
        'Guyana' => '+592',
        'Haïti' => '+509',
        'Honduras' => '+504',
        'Hong Kong' => '+852',
        'Hongrie' => '+36',
        'Îles Salomon' => '+677',
        'Indonésie' => '+62',
        'Irak' => '+964',
        'Iran' => '+98',
        'Irlande' => '+353',
        'Islande' => '+354',
        'Israël' => '+972',
        'Italie' => '+39',
        'Jamaïque' => '+1876',
        'Japon' => '+81',
        'Jordanie' => '+962',
        'Kazakhstan' => '+7',
        'Kenya' => '+254',
        'Kirghizistan' => '+996',
        'Kiribati' => '+686',
        'Koweït' => '+965',
        'Laos' => '+856',
        'Lesotho' => '+266',
        'Lettonie' => '+371',
        'Liban' => '+961',
        'Libéria' => '+231',
        'Libye' => '+218',
        'Liechtenstein' => '+423',
        'Lituanie' => '+370',
        'Luxembourg' => '+352',
        'Macao' => '+853',
        'Macédoine du Nord' => '+389',
        'Madagascar' => '+261',
        'Malaisie' => '+60',
        'Malawi' => '+265',
        'Maldives' => '+960',
        'Malte' => '+356',
        'Maurice' => '+230',
        'Mauritanie' => '+222',
        'Mexique' => '+52',
        'Moldavie' => '+373',
        'Monaco' => '+377',
        'Mongolie' => '+976',
        'Monténégro' => '+382',
        'Mozambique' => '+258',
        'Namibie' => '+264',
        'Népal' => '+977',
        'Nicaragua' => '+505',
        'Norvège' => '+47',
        'Nouvelle-Zélande' => '+64',
        'Oman' => '+968',
        'Ouganda' => '+256',
        'Ouzbékistan' => '+998',
        'Pakistan' => '+92',
        'Panama' => '+507',
        'Papouasie-Nouvelle-Guinée' => '+675',
        'Paraguay' => '+595',
        'Pays-Bas' => '+31',
        'Pérou' => '+51',
        'Philippines' => '+63',
        'Pologne' => '+48',
        'Portugal' => '+351',
        'Qatar' => '+974',
        'République dominicaine' => '+1809',
        'République tchèque' => '+420',
        'Roumanie' => '+40',
        'Russie' => '+7',
        'Rwanda' => '+250',
        'Saint-Marin' => '+378',
        'Sainte-Lucie' => '+1758',
        'Sao Tomé-et-Principe' => '+239',
        'Serbie' => '+381',
        'Seychelles' => '+248',
        'Sierra Leone' => '+232',
        'Singapour' => '+65',
        'Slovaquie' => '+421',
        'Slovénie' => '+386',
        'Somalie' => '+252',
        'Soudan' => '+249',
        'Soudan du Sud' => '+211',
        'Sri Lanka' => '+94',
        'Suède' => '+46',
        'Suisse' => '+41',
        'Suriname' => '+597',
        'Syrie' => '+963',
        'Tadjikistan' => '+992',
        'Taïwan' => '+886',
        'Tanzanie' => '+255',
        'Thaïlande' => '+66',
        'Timor oriental' => '+670',
        'Trinité-et-Tobago' => '+1868',
        'Turkménistan' => '+993',
        'Ukraine' => '+380',
        'Uruguay' => '+598',
        'Vanuatu' => '+678',
        'Venezuela' => '+58',
        'Viêt Nam' => '+84',
        'Yémen' => '+967',
        'Zambie' => '+260',
        'Zimbabwe' => '+263',
    ];

    /** Liste de pays proposée dans les formulaires clients. */
    public static function pays(): array
    {
        $noms = array_keys(self::PAYS_INDICATIFS);
        // Pays courants de l'activité d'abord (29 premiers), puis le reste par ordre alphabétique.
        $courants = array_slice($noms, 0, 29);
        $autres = array_slice($noms, 29);
        sort($autres, SORT_LOCALE_STRING);
        usort($autres, fn($a, $b) => strcmp(iconv('UTF-8', 'ASCII//TRANSLIT', $a) ?: $a, iconv('UTF-8', 'ASCII//TRANSLIT', $b) ?: $b));
        return array_merge($courants, $autres);
    }

    /** Indicatifs distincts, du plus long au plus court (pour le découpage). */
    private static function indicatifsTries(): array
    {
        $codes = array_values(array_unique(self::PAYS_INDICATIFS));
        usort($codes, fn($a, $b) => strlen($b) <=> strlen($a));
        return $codes;
    }

    public static function indicatifPourPays(?string $pays): string
    {
        return self::PAYS_INDICATIFS[$pays ?? ''] ?? '';
    }

    /** Sépare « +237 677000000 » en ['+237', '677000000']. Sans indicatif reconnu : ['', texte]. */
    public static function decouper(?string $tel): array
    {
        $tel = trim((string) $tel);
        if ($tel === '') {
            return ['', ''];
        }
        if (str_starts_with($tel, '00')) {
            $tel = '+' . substr($tel, 2);
        }
        if ($tel[0] === '+') {
            $compact = preg_replace('/[\s.\-()]/', '', $tel);
            foreach (self::indicatifsTries() as $code) {
                if (str_starts_with($compact, $code)) {
                    $reste = trim(preg_replace('/^\+?\s*' . preg_quote(ltrim($code, '+'), '/') . '[\s.\-]*/', '', $tel));
                    return [$code, $reste];
                }
            }
        }
        return ['', $tel];
    }

    /** Assemble indicatif + numéro (vide si aucun numéro saisi). */
    public static function composer(?string $indicatif, ?string $numero): string
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return '';
        }
        $indicatif = trim((string) $indicatif);
        if ($indicatif !== '' && !preg_match('/^\+\d{1,4}$/', $indicatif)) {
            $indicatif = '';
        }
        // Numéro déjà saisi au format international : on ne double pas l'indicatif.
        if ($numero[0] === '+' || str_starts_with($numero, '00')) {
            return $numero;
        }
        $numero = ltrim($numero);
        return trim($indicatif . ' ' . $numero);
    }

    /** Numéro exploitable par WhatsApp (chiffres, format international) ou '' si indisponible. */
    public static function pourWhatsApp(?string $tel): string
    {
        $tel = trim((string) $tel);
        if ($tel === '' || !($tel[0] === '+' || str_starts_with($tel, '00'))) {
            return '';
        }
        $chiffres = preg_replace('/\D/', '', $tel);
        if (str_starts_with($tel, '00')) {
            $chiffres = substr($chiffres, 2);
        }
        return strlen($chiffres) >= 8 ? $chiffres : '';
    }
}
