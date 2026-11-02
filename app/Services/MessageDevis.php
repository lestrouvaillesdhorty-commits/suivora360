<?php

namespace App\Services;

/** Message texte brut d'envoi d'un devis (e-mail, WhatsApp…), français ou anglais. Le devis PDF est joint séparément. */
class MessageDevis
{
    public static function construire(array $c, array $items, string $emetteur, string $lang, string $salut, string $signature): array
    {
        $en = $lang === 'en';
        $L = fn(string $fr, string $an) => $en ? $an : $fr;
        $t = DevisPdf::totaux($c);
        $dev = (string) $c['devise'];
        $sujet = $L('Devis ', 'Quotation ') . $c['reference'] . ' — ' . $emetteur;
        $m = [$salut . ',', '', $L('Veuillez trouver ci-joint notre devis ', 'Please find attached our quotation ') . $c['reference'] . $L(' pour votre demande.', ' for your request.'), ''];
        foreach ($items as $i => $it) {
            $q = rtrim(rtrim(number_format((float) $it['quantite'], 2, ',', ''), '0'), ',');
            $det = DevisPdf::details($it);
            $m[] = ($i + 1) . '. ' . $it['designation'] . ($det !== '' ? ' (' . $det . ')' : '') . ' — ' . $q . ' ' . ($it['unite'] ?? '')
                . ($it['prix_unitaire'] !== null ? ' × ' . DevisPdf::fmt($it['prix_unitaire']) . ' ' . $dev . ' ' . $L('HT', 'excl. VAT') : '');
        }
        if ($items) { $m[] = ''; }
        $m[] = $L('Total HT : ', 'Total excl. VAT: ') . DevisPdf::fmt($t['ht']) . ' ' . $dev;
        $m[] = $L('TVA', 'VAT') . ($t['tva_taux'] !== null ? ' (' . rtrim(rtrim(number_format($t['tva_taux'], 2, ',', ''), '0'), ',') . ' %)' : '') . ' : ' . DevisPdf::fmt($t['tva']) . ' ' . $dev;
        $m[] = $L('Total TTC : ', 'Total incl. VAT: ') . DevisPdf::fmt($t['ttc']) . ' ' . $dev;
        if (!empty($c['validite_devis'])) { $m[] = $L('Valable jusqu\'au ', 'Valid until ') . date('d/m/Y', strtotime($c['validite_devis'])); }
        $m[] = '';
        $m[] = $L('Dans l\'attente de votre retour.', 'Looking forward to your reply.');
        $m[] = $L('Cordialement,', 'Best regards,');
        $m[] = $signature !== '' ? $signature : $emetteur;
        return ['sujet' => $sujet, 'corps' => implode("\n", $m)];
    }
}
