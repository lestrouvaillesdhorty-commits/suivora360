<?php

namespace App\Services;

/**
 * Message TEXTE BRUT d'une consultation (e-mail, WhatsApp, messagerie Alibaba…) : tout ce que le
 * fournisseur doit savoir est dans le message lui-même (articles, quantités, caractéristiques,
 * destination, incoterm, échéance), sans exiger l'ouverture d'un lien. Le lien vers le
 * récapitulatif en ligne (photos, documents, PDF) reste facultatif. Respecte le masquage du client.
 */
class MessageConsultation
{
    private const EN_LIBELLES = [
        "Lieu d'enlèvement" => 'Pick-up location', 'Destination' => 'Destination', 'Marchandises' => 'Goods',
        'Poids estimé' => 'Estimated weight', 'Volume estimé' => 'Estimated volume',
        'Mode de transport souhaité' => 'Preferred transport mode', 'Nature du service' => 'Type of service',
        'Type de prestation' => 'Type of service', "Site d'intervention" => 'Intervention site',
        'Visite préalable nécessaire' => 'Prior site visit needed', 'Date souhaitée' => 'Requested date',
    ];
    private const EN_UNITES = ['Pièce' => 'pcs', 'pièce' => 'pcs', 'Pièces' => 'pcs', 'Unité' => 'units', 'Carton' => 'cartons', 'Palette' => 'pallets', 'Conteneur' => 'containers'];

    /**
     * @param array $d  Données du récapitulatif (PartagePublicController::donneesRecap)
     * @return array{sujet:string,corps:string}
     */
    public static function construire(array $d, string $lang, ?string $lien, string $salut, string $signature): array
    {
        $en = $lang === 'en';
        $consultation = $d['consultation'];
        $dossier = $d['dossier'] ?? [];
        $demande = $d['demande'] ?? [];
        $partage = $d['partage'];
        $masquer = !empty($d['masquerClient']);
        $L = fn(string $fr, string $anglais) => $en ? $anglais : $fr;

        $sujet = $L("Demande d'offre — ", 'Request for quotation — ') . ($dossier['reference'] ?? '') . (!empty($dossier['objet']) ? ' — ' . $dossier['objet'] : '');
        $t = [];
        $t[] = $salut . ',';
        $t[] = '';
        $t[] = $L("Nous souhaitons recevoir votre meilleure offre pour la demande suivante", 'We would like to receive your best offer for the following request')
            . (!empty($dossier['objet']) ? ' (' . $dossier['objet'] . ')' : '') . ' :';

        if (!empty($d['articles'])) {
            $t[] = '';
            $t[] = $L('ARTICLES', 'ITEMS');
            foreach (array_values($d['articles']) as $i => $a) {
                $q = $a['quantite'] ?? null;
                $q = ($q !== null && $q !== '') ? rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',') : '';
                $u = (string) ($a['unite'] ?? '');
                if ($en && isset(self::EN_UNITES[$u])) {
                    $u = self::EN_UNITES[$u];
                }
                $t[] = ($i + 1) . ') ' . trim($q . ' ' . $u) . ($q !== '' || $u !== '' ? ' – ' : '') . ($a['designation'] ?? '');
                $det = [];
                if (!empty($a['conditionnement'])) { $det[] = $L('Conditionnement', 'Packing') . ' : ' . $a['conditionnement']; }
                if (!empty($a['reference'])) { $det[] = $L('Réf.', 'Ref.') . ' : ' . $a['reference']; }
                if (!empty($a['marque'])) { $det[] = $L('Marque', 'Brand') . ' : ' . $a['marque']; }
                if (!empty($a['caracteristiques'])) { $det[] = $L('Caractéristiques', 'Specifications') . ' : ' . $a['caracteristiques']; }
                foreach ($det as $x) {
                    $t[] = '   ' . $x;
                }
            }
        }

        if (!empty($d['detailsDemande'])) {
            $t[] = '';
            $t[] = $L('DÉTAILS', 'DETAILS');
            foreach ($d['detailsDemande'] as $lib => $val) {
                $t[] = '- ' . ($en ? (self::EN_LIBELLES[$lib] ?? $lib) : $lib) . ' : ' . $val;
            }
        }

        $logi = [];
        $liv = \App\Models\ConsultationFournisseur::livraison($consultation, $demande ?: null);
        $adr = $liv['etape_adresse'] !== '' ? preg_replace('/\s*\R\s*/u', ', ', $liv['etape_adresse']) : '';
        if ($liv['etape']) {
            $logi[] = $L('Livrer à', 'Deliver to') . ' : ' . $liv['etape_pays'];
            if ($adr !== '') { $logi[] = $L('Adresse de livraison', 'Delivery address') . ' : ' . $adr; }
            if ($liv['finale'] !== '') { $logi[] = $L('Destination finale', 'Final destination') . ' : ' . $liv['finale']; }
        } else {
            if ($liv['finale'] !== '') { $logi[] = $L('Destination', 'Destination') . ' : ' . $liv['finale']; }
            if ($adr !== '') { $logi[] = $L('Adresse de livraison', 'Delivery address') . ' : ' . $adr; }
            elseif (!$masquer && !empty($demande['lieu_livraison'])) { $logi[] = $L('Lieu de livraison', 'Delivery place') . ' : ' . $demande['lieu_livraison']; }
        }
        if ($liv['incoterm'] !== '') { $logi[] = $L('Incoterm souhaité', 'Requested Incoterm') . ' : ' . $liv['incoterm']; }
        if (!empty($partage['echeance_reponse'])) { $logi[] = $L('Réponse souhaitée avant le', 'Please reply before') . ' : ' . date('d/m/Y', strtotime($partage['echeance_reponse'])); }
        if ($logi) {
            $t[] = '';
            $t[] = $L('LOGISTIQUE', 'LOGISTICS');
            foreach ($logi as $x) { $t[] = '- ' . $x; }
        }

        $prec = trim((string) ($consultation['articles_demandes'] ?? ''));
        if ($prec !== '') {
            $t[] = '';
            $t[] = $L('PRÉCISIONS', 'ADDITIONAL INFORMATION');
            $t[] = $prec;
        }

        $t[] = '';
        $t[] = $L("Merci d'indiquer pour chaque article :", 'Please indicate for each item:');
        $enInfos = ['Prix unitaire et devise' => 'Unit price and currency', 'Délai de production / livraison' => 'Production / delivery lead time', 'Quantité minimale de commande' => 'Minimum order quantity', "Validité de l'offre" => 'Offer validity', 'Conditions de paiement' => 'Payment terms', 'Incoterm proposé' => 'Proposed Incoterm', 'Échantillon possible' => 'Sample availability', 'Emballage export' => 'Export packing', 'Poids et volume par colis' => 'Weight and volume per package', 'Certificats (origine, conformité)' => 'Certificates (origin, conformity)'];
        foreach (\App\Models\ConsultationFournisseur::infos($consultation) as $inf) {
            $t[] = '- ' . ($en ? ($enInfos[$inf] ?? $inf) : $inf);
        }
        if ($lien) {
            $t[] = '';
            $t[] = $L('Récapitulatif en ligne (photos, documents, PDF) : ', 'Online summary (photos, documents, PDF): ') . $lien;
        }
        $t[] = '';
        $t[] = $L('Cordialement,', 'Best regards,');
        $t[] = $signature;

        return ['sujet' => $sujet, 'corps' => implode("\n", $t)];
    }
}
