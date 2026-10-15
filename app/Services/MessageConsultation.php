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
        if (!empty($demande['destination_pays'])) { $logi[] = $L('Destination', 'Destination') . ' : ' . $demande['destination_pays']; }
        if (!$masquer && !empty($demande['lieu_livraison'])) { $logi[] = $L('Lieu de livraison', 'Delivery place') . ' : ' . $demande['lieu_livraison']; }
        if (!empty($demande['incoterm_souhaite'])) { $logi[] = $L('Incoterm souhaité', 'Requested Incoterm') . ' : ' . $demande['incoterm_souhaite']; }
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
        $t[] = $L(
            "Merci d'indiquer pour chaque article : prix unitaire et devise, quantité minimale de commande, délai de production / livraison, conditions de paiement, validité de l'offre et incoterm proposé.",
            'Please indicate for each item: unit price and currency, minimum order quantity, production / delivery lead time, payment terms, offer validity and proposed Incoterm.'
        );
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
