<?php use App\Core\Auth; use App\Core\View; use App\Models\Demande; $avecRemise = \App\Core\Schema::colonne('cotation_items', 'remise_pct'); ?>
<?php
// [ajouté 06/10, étape 3] Nouvelle version d'une cotation : mêmes champs,
// pré-remplis depuis $cotationPrecedente (null = création normale).
$v = fn(string $champ) => $cotationPrecedente[$champ] ?? '';
$vNum = fn(string $champ) => ($cotationPrecedente[$champ] ?? null) !== null ? rtrim(rtrim(number_format((float) $cotationPrecedente[$champ], 2, '.', ''), '0'), '.') : '';
?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<?php
// [07/10, module Clients] Devise préférée du client de la demande : simple préremplissage d'une NOUVELLE cotation (jamais appliquée aux cotations existantes).
$deviseClientDefaut = '';
if (!$cotationPrecedente && !empty($demande['client_id']) && \App\Models\Client::schemaPret()) {
    $clientDevise = \App\Models\Client::find((int) $demande['client_id']);
    $deviseClientDefaut = (string) ($clientDevise['devise_preferee'] ?? '');
}
?>
<h1 style="margin-top:8px"><?= $cotationPrecedente ? 'Nouvelle version de la cotation ' . View::e($cotationPrecedente['reference']) . ' (v' . ((int) $cotationPrecedente['version'] + 1) . ')' : 'Nouvelle cotation client' ?></h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<?php if ($offreRetenue && !empty($modeGroupe)):
  $dvG = $calcAchat['devise'];
  $fG = fn($x) => number_format((float) $x, 2, ',', ' ') . ' ' . $dvG;
?>
  <div class="alert alert-succes"><strong><?= count($offresRetenues) ?> offre(s) retenue(s)</strong> — coût d'achat réparti par article :
    <div style="margin-top:6px;font-size:13px">
    <?php foreach ($calcAchat['offres'] as $l): ?>• <?= View::e($l['fournisseur']) ?> : prix <?= $fG($l['prix']) ?><?php if ($l['frais_propres'] > 0.004): ?> + ses frais <?= $fG($l['frais_propres']) ?><?php endif; ?><?php if ($l['part_communs'] > 0.004): ?> + part des frais communs <?= $fG($l['part_communs']) ?><?php endif; ?> = <strong><?= $fG($l['total']) ?></strong><br><?php endforeach; ?>
    <?php foreach ($calcAchat['frais'] as $f): ?>• Frais communs « <?= View::e($f['libelle']) ?> » : <?= $fG($f['montant']) ?> (répartis au prorata de la valeur d'achat)<br><?php endforeach; ?>
    = <strong>coût total d'achat : <?= $fG($calcAchat['total']) ?></strong> — c'est la base de la marge ci-dessous.</div>
    <?php if (!empty($etatTaux['devises'])): ?><div style="margin-top:6px;font-size:12.5px">Devises converties en <?= View::e($dvG) ?> :<?php foreach ($etatTaux['devises'] as $dev => $e): ?> 1 <?= View::e($dev) ?> = <?= $e['taux'] ? View::e(rtrim(rtrim(number_format($e['taux'], 6, '.', ''), '0'), '.')) : '?' ?> <?= View::e($dvG) ?>;<?php endforeach; ?> <a href="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>&onglet=achats&sous=offres">modifier les taux</a></div><?php endif; ?>
    <?php if ($calcAchat['ignores']): ?><div style="margin-top:6px;color:#9b1c1c">Non additionné : <?= View::e(implode(' ; ', $calcAchat['ignores'])) ?>. <a href="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>&onglet=achats&sous=offres">Indiquer le taux</a></div><?php endif; ?>
  </div>
<?php elseif ($offreRetenue):
  $coutTotalOffre = \App\Models\Offre::coutRendu($offreRetenue);
  $fraisOffre = [
      'Expédition jusqu\'à la destination finale' => $offreRetenue['transport_montant'] ?? 0, 'Assurance' => $offreRetenue['assurance_montant'] ?? 0,
      'Emballage' => $offreRetenue['emballage_montant'] ?? 0, 'Douane / droits' => $offreRetenue['douane_montant'] ?? 0,
      'Dédouanement' => $offreRetenue['dedouanement_montant'] ?? 0, 'Autres frais' => $offreRetenue['autres_frais_montant'] ?? 0,
  ];
  $fmtM = fn($x) => number_format((float) $x, 2, ',', ' ') . ' ' . $offreRetenue['devise'];
?>
  <div class="alert alert-succes">Offre retenue : <?= View::e($offreRetenue['fournisseur_nom']) ?> (incoterm négocié : <?= View::e($offreRetenue['incoterm_negocie']) ?: '—' ?>)
    <div style="margin-top:6px;font-size:13px">Prix du fournisseur : <strong><?= $fmtM($offreRetenue['montant_total']) ?></strong>
    <?php foreach ($fraisOffre as $lib => $m): if ((float) $m > 0): ?> + <?= View::e($lib) ?> : <?= $fmtM($m) ?><?php endif; endforeach; ?>
    = <strong>coût total d'achat : <?= $fmtM($coutTotalOffre) ?></strong> — c'est la base de la marge ci-dessous.</div>
  </div>
<?php else: ?>
  <div class="alert alert-erreur">Aucune offre n'a encore été retenue pour ce dossier (voir le Comparateur). Vous pouvez tout de même créer une cotation manuellement.</div>
<?php endif; ?>

<?php if ($demande): ?>
<div class="card" style="max-width:760px">
  <h2 style="font-size:14px;color:#666">Souhaits initiaux du client (à la qualification)</h2>
  <div class="info-row"><span class="label">Destination</span><span><?= View::e($demande['destination_pays']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Incoterm souhaité</span><span><?= View::e($demande['incoterm_souhaite']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Mode de paiement souhaité</span><span><?= View::e($demande['mode_paiement_souhaite']) ?: '—' ?></span></div>
</div>
<?php endif; ?>

<style>
.cot-grid{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:16px;align-items:start;max-width:1200px}
.cot-grid>.card{margin:0}
.cot-aside{position:sticky;top:12px}
.cot-etape{display:flex;align-items:center;gap:10px;margin:22px 0 10px;font-weight:700;font-size:15px}
.cot-etape:first-child{margin-top:0}
.cot-etape .n{width:26px;height:26px;border-radius:50%;background:#0B7C8A;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;flex:0 0 auto}
.cot-etape small{font-weight:400;color:#667;font-size:12px}
#recap-calc{border:2px solid #0B7C8A;border-radius:14px;background:#fff;padding:12px 16px}
#recap-calc summary{cursor:pointer;list-style:none}
#recap-calc summary::-webkit-details-marker{display:none}
.rc-l{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid #EEF1F5;font-size:13.5px}
.rc-l small{display:block;font-size:11.5px;color:#778}
.rc-l b.t{color:#0B7C8A;font-weight:800}
.rc-l span:last-child{white-space:nowrap;text-align:right}
.rc-ok,.rc-warn{margin-top:8px;border-radius:8px;padding:7px 10px;font-size:12.5px;font-weight:600}
.rc-ok{background:#E8F5EC;color:#1E7B3A}.rc-warn{background:#FFF4E0;color:#92400E}
@media(max-width:900px){.cot-grid{grid-template-columns:minmax(0,1fr)}.cot-aside{position:static;order:-1}}
</style>
<?php
// Lignes « achat » du récapitulatif (lecture seule, reprises de l'offre retenue).
$rcDev = !empty($modeGroupe) ? ($calcAchat['devise'] ?? 'EUR') : ($offreRetenue['devise'] ?? '');
$rcAchat = [];
try {
    $nf = fn($x) => number_format((float) $x, 2, ',', ' ') . ' ' . $rcDev;
    if ($offreRetenue && !empty($modeGroupe)) {
        foreach ($calcAchat['offres'] as $l) { $rcAchat[] = [View::e($l['fournisseur']), $nf($l['total']), 'prix ' . $nf($l['prix']) . ($l['frais_propres'] > 0.004 ? ' + frais ' . $nf($l['frais_propres']) : '') . ($l['part_communs'] > 0.004 ? ' + part frais communs ' . $nf($l['part_communs']) : '')]; }
    } elseif ($offreRetenue) {
        $rcAchat[] = ['Prix du fournisseur (' . View::e($offreRetenue['fournisseur_nom']) . ')', $nf($offreRetenue['montant_total']), ''];
        foreach (['transport_montant' => 'Expédition jusqu\'à la destination', 'assurance_montant' => 'Assurance', 'emballage_montant' => 'Emballage', 'douane_montant' => 'Douane / droits', 'dedouanement_montant' => 'Dédouanement', 'autres_frais_montant' => 'Autres frais'] as $k => $lib) {
            if ((float) ($offreRetenue[$k] ?? 0) > 0) { $rcAchat[] = ['+ ' . $lib, $nf($offreRetenue[$k]), 'frais de l\'offre']; }
        }
    }
} catch (\Throwable $eRc) { $rcAchat = []; }
?>
<div class="cot-grid">
<div class="card">
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/cotations">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="offre_id" value="<?= ($cotationPrecedente['offre_id'] ?? null) ?: ($offreRetenue['id'] ?? '') ?>">
    <?php if ($cotationPrecedente): ?><input type="hidden" name="cotation_precedente_id" value="<?= (int) $cotationPrecedente['id'] ?>"><?php endif; ?>

    <div class="cot-etape"><span class="n">1</span>Client et devise</div>
    <div class="form-group">
      <label>Facturé à (client)</label>
      <select name="client_id" required>
        <option value="">— Sélectionner —</option>
        <?php $clientDefaut = $cotationPrecedente ? (int) $cotationPrecedente['client_id'] : (int) ($demande['client_id'] ?? 0); ?>
        <?php foreach (\App\Models\Client::regrouper($clients) as $bloc): ?>
          <?php if ($bloc['groupe']): ?><optgroup label="<?= View::e($bloc['groupe']) ?>"><?php endif; ?>
          <?php foreach ($bloc['clients'] as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($clientDefaut === (int) $c['id']) ? 'selected' : '' ?>><?= View::e($c['nom']) ?></option>
          <?php endforeach; ?>
          <?php if ($bloc['groupe']): ?></optgroup><?php endif; ?>
        <?php endforeach; ?>
      </select>
    </div>

    <?php
    $deviseOffre = $cotationPrecedente ? ($cotationPrecedente['devise_achat'] ?? $cotationPrecedente['devise'] ?? 'EUR') : ($offreRetenue['devise'] ?? ($deviseClientDefaut ?: 'EUR'));
    $deviseDevis = $cotationPrecedente['devise'] ?? ($deviseClientDefaut ?: $deviseOffre);
    $tauxPrec = $cotationPrecedente ? (float) ($cotationPrecedente['taux_change'] ?? 1) : 0;
    $achatOrigine = $cotationPrecedente
        ? ($cotationPrecedente['montant_achat'] !== null ? rtrim(rtrim(number_format((float) $cotationPrecedente['montant_achat'] / ($tauxPrec > 0 ? $tauxPrec : 1), 2, '.', ''), '0'), '.') : '')
        : (!empty($modeGroupe) ? rtrim(rtrim(number_format((float) $calcAchat['total'], 2, '.', ''), '0'), '.') : (isset($offreRetenue['montant_total']) ? rtrim(rtrim(number_format(\App\Models\Offre::coutRendu($offreRetenue), 2, '.', ''), '0'), '.') : ''));
    $devisesListe = ['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'];
    $tvaSel = $cotationPrecedente && $cotationPrecedente['tva_taux'] !== null ? (float) $cotationPrecedente['tva_taux'] : (float) $tvaDefaut;
    $tvaOptions = \App\Models\Parametres::TVA_OPTIONS; if (!in_array($tvaSel, $tvaOptions, true)) { $tvaOptions[] = $tvaSel; }
    ?>
    <input type="hidden" name="devise_achat" id="devise_achat" value="<?= View::e($deviseOffre) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Devise du devis (celle du client)</label>
        <select name="devise" id="devise_devis">
          <?php foreach ($devisesListe as $d): ?><option value="<?= $d ?>" <?= $deviseDevis === $d ? 'selected' : '' ?>><?= in_array($d, ['XAF', 'XOF'], true) ? 'FCFA (' . $d . ')' : $d ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="bloc-taux" style="display:none">
        <label>Taux de change : 1 <span id="lib-offre"><?= View::e($deviseOffre) ?></span> = ? <span id="lib-devis"><?= View::e($deviseDevis) ?></span></label>
        <input type="number" step="0.000001" name="taux_change" id="taux_change" value="<?= $tauxPrec > 0 ? View::e((string) $tauxPrec) : '' ?>">
        <div class="hint" style="font-size:11px;color:#888;margin-top:2px">Proposé d'après les paramètres de l'entreprise (EUR ↔ FCFA) ; modifiable.</div>
      </div>
    </div>
    <?php
    $d2Actif = $cotationPrecedente && !empty($cotationPrecedente['devise2']);
    $d2Sel = $d2Actif ? strtoupper((string) $cotationPrecedente['devise2']) : ((in_array($deviseDevis, ['XAF', 'XOF'], true)) ? 'EUR' : 'XAF');
    $d2Taux = $d2Actif ? (float) $cotationPrecedente['taux_devise2'] : 0;
    ?>
    <div class="form-group" style="border:1px dashed #BFD6DB;border-radius:10px;padding:10px 12px;background:#F7FBFC">
      <label style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="checkbox" name="afficher_devise2" value="1" id="d2_on" <?= $d2Actif ? 'checked' : '' ?>> Afficher aussi le prix dans une 2e devise</label>
      <div class="hint" style="font-size:11.5px;color:#667;margin:2px 0 0 24px">Le client peut payer dans les deux. Information seulement : le montant officiel reste dans la devise du devis.</div>
      <div id="d2_bloc" style="display:<?= $d2Actif ? 'block' : 'none' ?>;margin-top:8px">
        <div class="form-row">
          <div class="form-group"><label>2e devise</label>
            <select name="devise2" id="d2_dev"><?php foreach ($devisesListe as $d): ?><option value="<?= $d ?>" <?= $d2Sel === $d ? 'selected' : '' ?>><?= in_array($d, ['XAF', 'XOF'], true) ? 'FCFA (' . $d . ')' : $d ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label>Taux : 1 <span id="d2_lib1"><?= View::e($deviseDevis) ?></span> = ? <span id="d2_lib2"></span></label>
            <input type="number" step="0.000001" min="0" name="taux_devise2" id="d2_taux" value="<?= $d2Taux > 0 ? View::e((string) $d2Taux) : '' ?>"></div>
        </div>
        <div id="d2_apercu" style="font-weight:600;font-size:13px"></div>
        <div class="hint" style="font-size:11.5px;color:#667">Ce taux est à vous : il peut être différent du taux d'achat. Une nouvelle version reprend celui de la version précédente.</div>
      </div>
    </div>
    <div class="cot-etape"><span class="n">2</span>Coût d'achat, marge et remises <small>le coût est repris de l'offre retenue, frais compris</small></div>
    <?php if (Auth::canModifierMarge((int) $dossier['filiale_id'])): ?>
    <div class="form-row">
      <div class="form-group"><label>Montant d'achat (coût fournisseur, en <?= View::e($deviseOffre) ?>)</label><input type="number" step="0.01" name="achat_origine" id="achat_origine" value="<?= View::e($achatOrigine) ?>"></div>
      <div class="form-group"><label>Marge (%)</label><input type="number" step="0.01" name="marge_pourcentage" placeholder="ex: 15" value="<?= $vNum('marge_pourcentage') ?>"></div>
    </div>
    <?php elseif (Auth::canSeeMarges()): ?>
    <div class="form-row">
      <div class="form-group">
        <label>Montant d'achat (coût fournisseur, en <?= View::e($deviseOffre) ?>)</label>
        <input type="number" step="0.01" value="<?= View::e($achatOrigine) ?>" disabled>
        <input type="hidden" name="achat_origine" id="achat_origine" value="<?= View::e($achatOrigine) ?>">
      </div>
      <div class="form-group">
        <label>Marge (%)</label>
        <input type="number" step="0.01" value="" disabled placeholder="Fixée par les Achats">
        <div style="font-size:11px;color:#888;margin-top:2px">Visible une fois saisie par les Achats — non modifiable depuis ce rôle.</div>
      </div>
    </div>
    <?php else: ?>
      <input type="hidden" name="achat_origine" id="achat_origine" value="<?= View::e($achatOrigine) ?>">
    <?php endif; ?>

    <?php if ($avecRemise): ?>
    <div class="rem-bloc">
      <div class="form-row">
        <div class="form-group"><label>Remise globale (%)</label><input type="number" step="0.01" min="0" max="100" name="remise_globale_pct" id="remise_globale_pct" value="<?= $vNum('remise_globale_pct') ?>" placeholder="ex. 2"></div>
        <div class="form-group"><label>Motif de la remise globale</label><input type="text" name="remise_motif" maxlength="255" value="<?= View::e($v('remise_motif')) ?>" placeholder="Fidélité, volume…"></div>
      </div>
      <div class="hint" style="font-size:11.5px;color:#667">Les remises par ligne se saisissent dans les articles. Le montant HT ci-dessous est le <strong>net après remises</strong> ; la marge est calculée après remises et n'apparaît jamais sur le devis.</div>
      <div id="rem-recap"></div>
    </div>
    <?php endif; ?>
    <div class="cot-etape"><span class="n">3</span>Montants du devis <small>calculés automatiquement, modifiables</small></div>
    <div class="tot-bloc">
      <div class="form-row">
        <div class="form-group"><label>Montant HT (hors taxe)</label><input type="number" step="0.01" name="montant_ht" id="montant_ht" value="<?= $cotationPrecedente ? $vNum('montant_ht') : '' ?>"></div>
        <div class="form-group"><label>TVA (%)</label>
          <select name="tva_taux" id="tva_taux"><?php foreach ($tvaOptions as $t): ?><option value="<?= View::e((string) $t) ?>" <?= (float) $t === $tvaSel ? 'selected' : '' ?>><?= View::e(rtrim(rtrim(number_format((float) $t, 2, ',', ''), '0'), ',')) ?> %</option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="tot-ligne"><span>Montant de la TVA</span><span><strong id="aff-tva">—</strong></span></div>
      <div class="form-group" style="margin:8px 0 0"><label>Montant total facturé au client (TTC)</label><input type="number" step="0.01" name="montant_total" id="montant_total" required value="<?= $cotationPrecedente ? $vNum('montant_total') : '' ?>"></div>
      <div class="hint" style="font-size:11px;color:#667;margin-top:4px">Tout se calcule tout seul (achat × taux × marge, puis TVA). Vous pouvez corriger le HT ou le TTC à la main.</div>
    </div>
    <div class="cot-etape"><span class="n">4</span>Conditions</div>
    <div class="form-row">
      <div class="form-group">
        <label>Mode de paiement négocié</label>
        <select name="mode_paiement_negocie">
          <option value="">— Non précisé —</option>
          <?php foreach (Demande::MODES_PAIEMENT as $code => $label): ?>
            <option value="<?= $code ?>" <?= (($cotationPrecedente['mode_paiement_negocie'] ?? ($demande['mode_paiement_souhaite'] ?? '')) === $code) ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Incoterm client</label>
        <select name="incoterm_client">
          <option value="">— Non précisé —</option>
          <?php foreach (Demande::INCOTERMS as $code => $label): ?>
            <option value="<?= $code ?>" <?= (($cotationPrecedente['incoterm_client'] ?? ($demande['incoterm_souhaite'] ?? '')) === $code) ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php
    $resTransport = [];
    try { if (class_exists(\App\Models\Expedition::class) && \App\Models\Expedition::disponible() && \App\Models\Cotation::colonnesTransport()) { $resTransport = \App\Models\Expedition::reservationsDuDossier((int) $dossier['id']); } } catch (\Throwable $eT) { $resTransport = []; }
    if ($resTransport): ?>
    <div class="form-row">
      <div class="form-group"><label>Transport (expédition)</label>
        <select name="transport_mode">
          <option value="">— Non concerné —</option>
          <option value="inclus" <?= (($cotationPrecedente['transport_mode'] ?? '') === 'inclus') ? 'selected' : '' ?>>Inclus dans le prix</option>
          <option value="a_part" <?= (($cotationPrecedente['transport_mode'] ?? '') === 'a_part') ? 'selected' : '' ?>>Facturé à part (ligne Transport ajoutée)</option>
        </select></div>
      <div class="form-group"><label>Réservation</label>
        <select name="transport_reservation_id">
          <?php foreach ($resTransport as $rt): ?><option value="<?= (int) $rt['id'] ?>"><?= View::e(($rt['expedition_reference'] ?? '') . ' · ' . ($rt['origine'] ?? '') . ' → ' . ($rt['destination'] ?? '') . ' · ' . number_format((float) $rt['prix_vente'], 0, ',', ' ') . ' ' . ($rt['devise'] ?? '')) ?></option><?php endforeach; ?>
        </select></div>
    </div>
    <div class="hint" style="margin:-6px 0 10px">Facturé à part : le prix de la réservation s'ajoute au total du devis à l'enregistrement. Inclus : le coût imputé est déduit de la marge.</div>
    <?php endif; ?>
    <div class="form-group"><label>Validité du devis</label><input type="date" name="validite_devis" value="<?= View::e($v('validite_devis')) ?>"></div>

    <div class="cot-etape"><span class="n">5</span>Articles <small>prix de vente = coût × taux × (1 + marge)</small></div>
    <div class="form-group">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="margin:0">Détail des articles (optionnel)</label>
        <button type="button" class="btn btn-sm btn-secondary" id="add-item">+ Ligne</button>
      </div>
      <div style="font-size:12px;color:#666;margin-top:4px"><?php if (!$cotationPrecedente && !empty($itemsPrecedents)): ?><?= !empty($depuisOffre) ? "Articles et prix repris de l'offre retenue. " : 'Articles repris de la demande. ' ?><?php endif; ?>Les prix unitaires (HT) se calculent depuis le coût, le taux et la marge ; vous pouvez les corriger.</div>
      <table style="margin-top:10px" id="items-table"><tbody id="items-body">
        <?php foreach ($itemsPrecedents ?? [] as $item) { include __DIR__ . '/../partials/_ligne_article.php'; } ?>
      </tbody></table>
    </div>

    <div class="cot-etape"><span class="n">6</span>Livraison et notes <small>facultatif</small></div>
    <div class="form-group"><label>Adresse de livraison (si différente de celle du client)</label><textarea name="adresse_livraison" rows="2" placeholder="Laisser vide pour reprendre l'adresse de livraison de la fiche client"><?= View::e($v('adresse_livraison')) ?></textarea></div>
    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($v('notes')) ?></textarea></div>

    <button type="submit" class="btn"><?= $cotationPrecedente ? 'Créer la nouvelle version' : 'Créer la cotation' ?></button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>
<aside class="cot-aside"><div id="recap-calc"><details id="rc-det" open>
  <summary><div style="font-weight:800;font-size:15px">Comment le prix est calculé</div><div style="font-size:13px;margin-top:2px">Total TTC : <strong id="rc-ttc-top">—</strong></div></summary>
  <div style="margin-top:6px">
    <?php foreach ($rcAchat as $l): ?><div class="rc-l"><span><?= $l[0] ?><?php if ($l[2]): ?><small><?= $l[2] ?></small><?php endif; ?></span><span><?= $l[1] ?></span></div><?php endforeach; ?>
    <div id="rc-live"></div>
  </div>
</details></div></aside>
</div>

<template id="item-row-template"><?php $item = []; include __DIR__ . '/../partials/_ligne_article.php'; ?></template>

<script>
document.getElementById('add-item').addEventListener('click', function () {
  var tpl = document.getElementById('item-row-template');
  var clone = tpl.content.cloneNode(true);
  document.getElementById('items-body').appendChild(clone);
});
document.getElementById('items-body').addEventListener('click', function (e) {
  if (e.target.classList.contains('remove-item')) {
    e.target.closest('tr').remove();
  }
});
</script>
<script>
// Cotation : coût d'achat (devise de l'offre) × taux de change × (1 + marge) = HT ; + TVA = TTC.
(function () {
  var f = document.getElementById('montant_total').closest('form'), body = document.getElementById('items-body');
  var $ = function (id) { return document.getElementById(id); };
  var ht = $('montant_ht'), ttc = $('montant_total'), tva = $('tva_taux'), achat = $('achat_origine'), marge = f.querySelector('[name="marge_pourcentage"]'),
      dv = $('devise_devis'), dOffre = $('devise_achat'), taux = $('taux_change'), bloc = $('bloc-taux');
  var T = <?= json_encode(['EUR' => ['XAF' => $tauxEurFcfa, 'XOF' => $tauxEurFcfa], 'XAF' => ['EUR' => 1 / max($tauxEurFcfa, 0.0001), 'XOF' => 1], 'XOF' => ['EUR' => 1 / max($tauxEurFcfa, 0.0001), 'XAF' => 1]]) ?>;
  var auto = !<?= $cotationPrecedente ? 'true' : 'false' ?>, tauxManuel = <?= $tauxPrec > 0 ? 'true' : 'false' ?>;
  var prevTx = NaN, prevM = 0;
  var r2 = function (x) { return (Math.round(x * 100) / 100).toFixed(2); };
  var num = function (el) { var v = el ? parseFloat(el.value) : NaN; return v; };
  function memeDevise() { return dv.value === dOffre.value; }
  function majTaux() {
    $('lib-offre').textContent = dOffre.value; $('lib-devis').textContent = dv.value;
    bloc.style.display = memeDevise() ? 'none' : '';
    if (!memeDevise() && !tauxManuel) { var t = (T[dOffre.value] || {})[dv.value]; taux.value = t ? String(Math.round(t * 1000000) / 1000000) : ''; }
  }
  function tauxEff() { return memeDevise() ? 1 : num(taux); }
  var remL = 0, glob = $('remise_globale_pct');
  function sommeLignes() {
    var s = 0, n = 0; remL = 0;
    body.querySelectorAll('tr').forEach(function (tr) {
      var q = parseFloat(tr.querySelector('[name="item_quantite[]"]').value), p = parseFloat(tr.querySelector('[name="item_prix_unitaire[]"]').value), m = tr.querySelector('.art-montant'),
          ri = tr.querySelector('[name="item_remise[]"]'), r = ri ? (parseFloat(ri.value) || 0) : 0;
      if (!isNaN(q) && !isNaN(p)) { s += q * p; remL += q * p * Math.min(100, Math.max(0, r)) / 100; n++; if (m) m.value = r2(q * p * (1 - Math.min(100, Math.max(0, r)) / 100)); } else if (m) m.value = '';
    });
    return n ? s : null;
  }
  // Net HT = (brut - remises de lignes) x (1 - remise globale %). Affiche le détail sous le bloc des remises.
  function net(brut) {
    var g = glob ? Math.min(100, Math.max(0, parseFloat(glob.value) || 0)) : 0, apres = brut - remL, rg = apres * g / 100, n = apres - rg, rc = $('rem-recap');
    if (rc) {
      if (remL > 0.004 || rg > 0.004) {
        var d = dv.value, L = function (a, b, c) { return '<div class="tl ' + (c || '') + '"><span>' + a + '</span><span>' + b + '</span></div>'; };
        rc.innerHTML = L('Total brut HT', r2(brut) + ' ' + d) + (remL > 0.004 ? L('Remises sur lignes', '− ' + r2(remL) + ' ' + d, 'o') : '') + (rg > 0.004 ? L('Remise globale ' + g + ' %', '− ' + r2(rg) + ' ' + d, 'o') : '') + L('Total net HT', r2(n) + ' ' + d, 'n');
      } else { rc.innerHTML = ''; }
    }
    return n;
  }
  function affTva() { var h = num(ht), t = num(ttc); $('aff-tva').textContent = (!isNaN(h) && !isNaN(t)) ? r2(t - h) + ' ' + dv.value : '—'; }
  function depuisHt() { var h = num(ht), t = num(tva); if (!isNaN(h)) ttc.value = r2(h * (1 + (isNaN(t) ? 0 : t) / 100)); affTva(); }
  function recalc() {
    var tx = tauxEff(), m = marge ? (parseFloat(marge.value) || 0) : 0, a = num(achat);
    if (!isNaN(tx) && tx > 0) {
      // Lignes sans coût de base (nouvelle version, ligne ajoutée à la main) : on le déduit du prix affiché
      // avec le taux et la marge précédents, pour que le taux et la marge les fassent aussi bouger.
      if (!isNaN(prevTx) && prevTx > 0) {
        body.querySelectorAll('[name="item_prix_unitaire[]"]').forEach(function (inp) {
          var v = parseFloat(inp.value);
          if (!inp.hasAttribute('data-cout') && !inp.dataset.manuel && !isNaN(v)) { inp.dataset.cout = String(v / (prevTx * (1 + prevM / 100))); }
        });
      }
      prevTx = tx; prevM = m;
      body.querySelectorAll('[data-cout]').forEach(function (inp) { inp.value = r2(parseFloat(inp.dataset.cout) * tx * (1 + m / 100)); });
    }
    var s = sommeLignes();
    if (auto) {
      if (!isNaN(a) && !isNaN(tx) && tx > 0) ht.value = r2(net(a * tx * (1 + m / 100)));
      else if (s !== null) ht.value = r2(net(s));
      depuisHt();
    }
  }
  ht.addEventListener('input', function () { auto = false; depuisHt(); });
  ttc.addEventListener('input', function () { auto = false; var t = num(tva), v = num(ttc); if (!isNaN(v)) ht.value = r2(v / (1 + (isNaN(t) ? 0 : t) / 100)); affTva(); });
  tva.addEventListener('change', depuisHt);
  if (glob) glob.addEventListener('input', function () { auto = true; recalc(); });
  [achat, marge].forEach(function (el) { if (el) el.addEventListener('input', function () { auto = true; recalc(); }); });
  taux.addEventListener('input', function () { tauxManuel = true; auto = true; recalc(); });
  dv.addEventListener('change', function () { tauxManuel = false; majTaux(); auto = true; recalc(); });
  body.addEventListener('input', function (e) {
    if (e.target.name === 'item_prix_unitaire[]') { e.target.removeAttribute('data-cout'); e.target.dataset.manuel = '1'; }
    var s = sommeLignes(); if (auto && s !== null && isNaN(num(achat))) { ht.value = r2(net(s)); depuisHt(); } else if (auto && !isNaN(num(achat))) { recalc(); }
  });
  body.addEventListener('click', function () { setTimeout(sommeLignes, 0); });

  // 2e devise facultative : taux proposé d'après les paramètres, aperçu du montant.
  var d2on = $('d2_on'), d2dev = $('d2_dev'), d2tx = $('d2_taux'), d2man = !!d2tx.value;
  function d2taux() {
    var a = dv.value, b = d2dev.value; if (a === b) return '';
    var t = (T[a] || {})[b]; return t ? String(Math.round(t * 1000000) / 1000000) : '';
  }
  function majD2() {
    $('d2_bloc').style.display = d2on.checked ? 'block' : 'none';
    $('d2_lib1').textContent = dv.value; $('d2_lib2').textContent = d2dev.value;
    if (!d2man) { d2tx.value = d2taux(); }
    var t = parseFloat(d2tx.value), v = parseFloat(ttc.value), cfa = ['XAF', 'XOF'].indexOf(d2dev.value) >= 0;
    $('d2_apercu').textContent = (d2dev.value === dv.value) ? 'Choisissez une devise différente de celle du devis.' : ((!isNaN(t) && t > 0 && !isNaN(v)) ? 'Aperçu : ' + fm(v, dv.value) + ' ≈ ' + (v * t).toLocaleString('fr-FR', {minimumFractionDigits: cfa ? 0 : 2, maximumFractionDigits: cfa ? 0 : 2}) + ' ' + (cfa ? 'FCFA' : d2dev.value) : '');
  }
  d2on.addEventListener('change', majD2); d2dev.addEventListener('change', function () { d2man = false; majD2(); });
  d2tx.addEventListener('input', function () { d2man = true; majD2(); });
  dv.addEventListener('change', function () { d2man = false; if (d2dev.value === dv.value) { d2dev.value = (dv.value === 'EUR') ? 'XAF' : 'EUR'; } majD2(); });
  ttc.addEventListener('input', majD2); ht.addEventListener('input', majD2); tva.addEventListener('change', majD2);
  setInterval(majD2, 700);
  // Récapitulatif lisible + contrôle de cohérence
  function fm(x, d) { return (isNaN(x) ? '—' : x.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2})) + ' ' + d; }
  function recap() {
    var el = $('rc-live'); if (!el) return;
    var d = dv.value, o = dOffre.value, tx = tauxEff(), m = marge ? (parseFloat(marge.value) || 0) : 0, a = num(achat), h = num(ht), t = num(tva), T2 = num(ttc);
    var L = function (a1, b, small, tot) { return '<div class="rc-l"><span>' + (tot ? '<b class="t">' + a1 + '</b>' : a1) + (small ? '<small>' + small + '</small>' : '') + '</span><span>' + (tot ? '<b class="t">' + b + '</b>' : b) + '</span></div>'; };
    var h2 = '';
    if (!isNaN(a)) {
      h2 += L('= Coût d\'achat utilisé', fm(a, o), 'modifiable à l\'étape 2', true);
      if (!memeDevise() && !isNaN(tx) && tx > 0) { h2 += L('× Taux de change', String(Math.round(tx * 1000000) / 1000000), '1 ' + o + ' = ' + tx + ' ' + d) + L('= Coût d\'achat en ' + d, fm(a * tx, d), '', true); }
      var base = a * (memeDevise() ? 1 : (tx > 0 ? tx : NaN));
      if (!isNaN(base)) { h2 += L('+ Votre marge', m + ' % = ' + fm(base * m / 100, d), 'jamais visible sur le devis'); }
    }
    var g = glob ? (parseFloat(glob.value) || 0) : 0;
    if (g > 0) h2 += L('− Remise globale', g + ' %', '');
    h2 += L('= Prix HT', fm(h, d), '', true);
    h2 += L('+ TVA', isNaN(t) ? '—' : (t + ' % = ' + fm(T2 - h, d)), '');
    h2 += L('= Total facturé au client (TTC)', fm(T2, d), '', true);
    var rows = 0, sb = 0, rl = 0;
    body.querySelectorAll('tr').forEach(function (tr) {
      var q = parseFloat(tr.querySelector('[name="item_quantite[]"]').value), p = parseFloat(tr.querySelector('[name="item_prix_unitaire[]"]').value), ri = tr.querySelector('[name="item_remise[]"]'), r = ri ? (parseFloat(ri.value) || 0) : 0;
      if (!isNaN(q) && !isNaN(p)) { rows++; sb += q * p * (1 - Math.min(100, Math.max(0, r)) / 100); }
    });
    if (rows && !isNaN(h)) {
      var attendu = sb * (1 - Math.min(100, Math.max(0, g)) / 100), ecart = attendu - h;
      h2 += Math.abs(ecart) < 0.6 ? '<div class="rc-ok">✔ La somme des lignes d\'articles (' + fm(attendu, d) + ') correspond au prix HT.</div>'
        : '<div class="rc-warn">⚠ Écart de ' + fm(Math.abs(ecart), d) + ' entre la somme des lignes (' + fm(attendu, d) + ') et le prix HT (' + fm(h, d) + '). ' + (auto ? 'Vérifiez les lignes (prix modifié à la main ?).' : 'Le HT a été saisi à la main.') + '</div>';
    }
    el.innerHTML = h2; var top = $('rc-ttc-top'); if (top) top.textContent = fm(T2, d);
  }
  ['input', 'change', 'click'].forEach(function (ev) { f.addEventListener(ev, function () { setTimeout(recap, 30); }); });
  if (window.matchMedia && window.matchMedia('(max-width:900px)').matches && $('rc-det')) { $('rc-det').removeAttribute('open'); }
  majTaux(); prevTx = tauxEff(); prevM = marge ? (parseFloat(marge.value) || 0) : 0; recalc(); affTva(); recap();
})();
</script>
