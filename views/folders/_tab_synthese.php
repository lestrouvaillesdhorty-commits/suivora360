<?php
use App\Core\View;
use App\Models\Cotation;
use App\Models\Dossier;

// Synthèse du dossier (v258) : de quoi parle le dossier (produit, client, fournisseur final, transport),
// les chiffres, le délai de traitement et ce qui s'est passé. Les étapes sont dans la barre 1 à 6 et l'onglet Exécution.
$base = '/index.php?r=dossiers/' . (int) $dossier['id'];
$aujourdhui = date('Y-m-d');
$actif = ($dossier['statut'] ?? '') === 'actif' && $dossier['etape'] !== 'cloture';
$jours = function (?string $d) use ($aujourdhui) { return $d && substr($d, 0, 10) < $aujourdhui ? (int) floor((strtotime($aujourdhui) - strtotime(substr($d, 0, 10))) / 86400) : 0; };
$retardInterne = $actif ? $jours($dossier['echeance'] ?? null) : 0;
$retardClient = $actif ? $jours($demande['date_souhaitee_client'] ?? null) : 0;
$fmtMontant = function ($m, $dev) { return $m === null || $m === '' ? '—' : number_format((float) $m, 2, ',', ' ') . ' ' . View::e((string) $dev); };
$jr = function (int $n) { return $n . ' jour' . ($n > 1 ? 's' : ''); };

$quiTransport = (string) ($offreRetenue['transport_qui'] ?? '');
$transitaireNom = '';
$tid = (int) ($dossier['tr_transitaire_id'] ?? 0) ?: (int) ($offreRetenue['transitaire_id'] ?? 0);
if ($tid > 0) { $tf = \App\Models\Fournisseur::find($tid); $transitaireNom = $tf ? (string) $tf['nom'] : ''; }
if ($transitaireNom === '' && !empty($commande)) {
    foreach (\App\Models\Commande::steps((int) $commande['id']) as $stT) { if ($stT['libelle'] === 'transitaire' && $stT['statut'] !== 'inclus' && !empty($stT['transitaire_nom'])) { $transitaireNom = (string) $stT['transitaire_nom']; } }
}
$trMontant = !empty($dossier['tr_montant']) ? $fmtMontant($dossier['tr_montant'], $dossier['tr_devise'] ?? '') : (($offreRetenue && (float) ($offreRetenue['transport_montant'] ?? 0) > 0) ? $fmtMontant($offreRetenue['transport_montant'], $offreRetenue['devise']) : null);
// Chiffres clés dans la devise de référence du dossier (celle de l'offre retenue), avec l'équivalent dans la devise du devis.
$ref = $offreRetenue ? (((string) ($offreRetenue['devise'] ?? '')) ?: 'EUR') : (($cotation['devise_achat'] ?? '') ?: 'EUR');
$devDevis = $cotation ? (string) ($cotation['devise'] ?? '') : '';
$tauxEur = (float) (\App\Models\Parametres::forFiliale((int) $dossier['filiale_id'])['taux_eur_fcfa'] ?? 655.957);
$tauxRefVersDevis = null; // 1 unité de la devise de référence = X devise du devis
if ($devDevis !== '' && $devDevis !== $ref) {
    $tauxRefVersDevis = ($cotation && ($cotation['devise_achat'] ?? '') === $ref && (float) ($cotation['taux_change'] ?? 0) > 0) ? (float) $cotation['taux_change'] : \App\Models\DossierTauxChange::parDefaut($ref, $devDevis, $tauxEur);
}
$fm = function (float $m, string $dev) { $cfa = in_array($dev, ['XAF', 'XOF'], true); return number_format($m, $cfa ? 0 : 2, ',', ' ') . ' ' . View::e($dev); };
$versRef = function ($m) use ($tauxRefVersDevis) { return $tauxRefVersDevis > 0 ? (float) $m / $tauxRefVersDevis : null; };
$tuile = function (?float $enRef, ?float $enDevis, string $suffixe = '') use ($ref, $devDevis, $tauxRefVersDevis, $fm) {
    if ($enRef === null) { return ['—', '']; }
    $sous = ($devDevis !== '' && $devDevis !== $ref && $enDevis !== null) ? 'soit ' . $fm($enDevis, $devDevis) : '';
    return [$fm($enRef, $ref) . $suffixe, $sous];
};
$vRef = fn($x) => $tauxRefVersDevis > 0 ? $x * $tauxRefVersDevis : null;
$trRefMontant = null; $trDansTuile = false;
if (!empty($dossier['tr_montant']) && (($dossier['tr_devise'] ?? $ref) === $ref || ($dossier['tr_devise'] ?? '') === '')) { $trRefMontant = (float) $dossier['tr_montant']; }
elseif ($offreRetenue && (float) ($offreRetenue['transport_montant'] ?? 0) > 0) { $trRefMontant = (float) $offreRetenue['transport_montant']; $trDansTuile = true; }
// Coût d'achat = prix des marchandises + frais saisis sur l'offre (assurance, emballage, douane…). Le transport de l'offre reste dans sa propre tuile.
$achatRef = $offreRetenue ? \App\Models\Offre::coutRendu($offreRetenue) - ($trDansTuile ? (float) $offreRetenue['transport_montant'] : 0) : null;
$venteDevis = $cotation ? (float) ($cotation['montant_ht'] ?? $cotation['montant_total']) : null;
$venteRef = $venteDevis !== null ? ($devDevis === $ref ? $venteDevis : $versRef($venteDevis)) : null;
$margeDevis = ($cotation && $cotation['marge_montant'] !== null) ? (float) $cotation['marge_montant'] : null;
$margeRef = $margeDevis !== null ? ($devDevis === $ref ? $margeDevis : $versRef($margeDevis)) : null;
$pctM = ($cotation && $cotation['marge_pourcentage'] !== null) ? ' · ' . rtrim(rtrim(number_format((float) $cotation['marge_pourcentage'], 1, ',', ''), '0'), ',') . ' %' : '';
[$achat, $achatSous] = $tuile($achatRef, $achatRef !== null ? $vRef($achatRef) : null);
[$transp, $transpSous] = $quiTransport === 'fournisseur' ? ['Inclus', 'dans le coût d\'achat'] : $tuile($trRefMontant, $trRefMontant !== null ? $vRef($trRefMontant) : null);
[$vente, $venteSous] = $tuile($venteRef, $venteDevis);
[$marge, $margeSous] = $tuile($margeRef, $margeDevis, $pctM);

// Délai de traitement : de la réception de la demande à la livraison (ou à aujourd'hui si le dossier est en cours).
$debut = substr((string) ($demande['recue_le'] ?? $dossier['created_at']), 0, 10);
$fin = null; $finLibelle = '';
if (!empty($commande)) {
    foreach (\App\Models\Commande::steps((int) $commande['id']) as $stp) { if ($stp['libelle'] === 'livraison' && $stp['statut'] === 'termine' && !empty($stp['date_reelle'])) { $fin = substr($stp['date_reelle'], 0, 10); } }
}
if ($fin === null && $dossier['etape'] === 'cloture') { $fin = substr((string) $dossier['updated_at'], 0, 10); }
$delaiJ = max(0, (int) floor(((strtotime($fin ?? $aujourdhui)) - strtotime($debut)) / 86400));
$delaiSous = $fin !== null ? 'de la demande à la ' . (!empty($commande) ? 'livraison' : 'clôture') : 'depuis la demande, en cours';
$cible = $demande['date_souhaitee_client'] ?? null;
$delaiNote = '';
if ($cible) {
    if ($fin !== null) { $delaiNote = $fin <= $cible ? 'dans les temps' : 'en retard de ' . $jr((int) floor((strtotime($fin) - strtotime($cible)) / 86400)); }
    else { $delaiNote = $aujourdhui <= $cible ? 'livraison souhaitée le ' . date('d/m', strtotime($cible)) : 'en retard de ' . $jr((int) floor((strtotime($aujourdhui) - strtotime($cible)) / 86400)); }
}

// Produit
$photo = null;
foreach ($piecesJointes as $p) { if (str_starts_with((string) $p['type_mime'], 'image/') && ($p['categorie'] ?? '') === 'photos_produit') { $photo = $p; break; } }
if (!$photo) { foreach ($piecesJointes as $p) { if (str_starts_with((string) $p['type_mime'], 'image/')) { $photo = $p; break; } } }
$principaux = array_slice(array_values(array_filter(array_map(fn($a) => trim((string) ($a['designation'] ?? '')), $articles ?? []))), 0, 3);
$clientNom = '';
if (!empty($demande['client_id'])) { $cl = \App\Models\Client::find((int) $demande['client_id']); $clientNom = $cl ? (string) $cl['nom'] : ''; }
if ($clientNom === '') { $clientNom = (string) ($demande['expediteur_entreprise'] ?? ''); }
$dest = trim(($demande['lieu_livraison'] ?? '') . (($demande['lieu_livraison'] ?? '') && ($demande['destination_pays'] ?? '') ? ' — ' : '') . ($demande['destination_pays'] ?? ''));
$fo = $offreRetenue ? \App\Models\Fournisseur::find((int) $offreRetenue['fournisseur_id']) : null;

// Journal : ce qui s'est passé, du plus récent au plus ancien
$journal = array_slice(array_reverse($historique ?? []), 0, 8);
?>
<div class="sy">
  <?php if ($retardInterne > 0 || $retardClient > 0): ?>
  <div class="sy-alerte"><span aria-hidden="true" style="font-size:18px">&#9888;</span><span><b>En retard de <?= $jr(max($retardInterne, $retardClient)) ?></b>
    <?php if ($retardInterne > 0): ?> · échéance interne le <?= date('d/m/Y', strtotime($dossier['echeance'])) ?><?php endif; ?>
    <?php if ($retardClient > 0): ?> · échéance client le <?= date('d/m/Y', strtotime($demande['date_souhaitee_client'])) ?><?php endif; ?></span></div>
  <?php endif; ?>

  <div class="sy-kpis">
    <div class="sy-k"><span>Coût d'achat</span><b><?= $achat ?></b><small><?= $achatSous ?></small></div>
    <div class="sy-k"><span>Livraison au client</span><b><?= $transp ?></b><small><?= $transpSous ?></small></div>
    <div class="sy-k"><span>Prix de vente HT</span><b><?= $vente ?></b><small><?= $venteSous ?></small></div>
    <div class="sy-k"><span>Marge</span><b><?= $marge ?></b><small><?= $margeSous ?></small></div>
    <div class="sy-k"><span>Délai de traitement</span><b><?= $jr($delaiJ) ?></b><small><?= View::e($delaiSous) ?><?= $delaiNote !== '' ? ' · ' . View::e($delaiNote) : '' ?></small></div>
  </div>

  <div class="fiche-grid">
    <div class="card">
      <h2>Le produit</h2>
      <div class="sy-prod">
        <div class="sy-photo"><?php if ($photo): ?><img src="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/pieces/<?= (int) $photo['id'] ?>/telecharger&apercu=1" alt=""><?php else: ?><span>Pas de photo</span><?php endif; ?></div>
        <div style="flex:1;min-width:0">
          <div class="kv-row"><span class="label">Famille</span><span class="value"><?= View::e((string) ($demande['famille_produit'] ?? '')) ?: 'À préciser' ?></span></div>
          <div class="kv-row"><span class="label">Articles</span><span class="value"><?= count($articles ?? []) ?> ligne(s)<?php if ($principaux): ?><span class="sy-s"> · <?= View::e(implode(', ', $principaux)) ?></span><?php endif; ?></span></div>
        </div>
      </div>
      <div style="margin-top:10px"><a href="<?= $base ?>&onglet=besoin" class="card-link-hint">Voir le besoin détaillé →</a></div>
    </div>

    <div class="card">
      <h2>Client et livraison</h2>
      <div class="kv-row"><span class="label">Client</span><span class="value"><?= View::e($clientNom) ?: 'À préciser' ?></span></div>
      <div class="kv-row"><span class="label">Destination</span><span class="value"><?= $dest !== '' ? View::e($dest) : 'À préciser' ?></span></div>
      <div class="kv-row"><span class="label">Date souhaitée</span><span class="value"><?= $cible ? date('d/m/Y', strtotime($cible)) : 'À préciser' ?></span></div>
      <?php if ($cotation): ?><div class="kv-row"><span class="label">Cotation</span><span class="value"><?= View::e($cotation['reference']) ?> · <?= $fmtMontant($cotation['montant_total'], $cotation['devise']) ?> · <?= View::e(Cotation::STATUTS[$cotation['statut']] ?? $cotation['statut']) ?><?php if (($cotation['transport_mode'] ?? '') === 'inclus'): ?><span class="sy-s"> · transport inclus dans le prix</span><?php elseif (($cotation['transport_mode'] ?? '') === 'a_part'): ?><span class="sy-s"> · transport facturé à part</span><?php endif; ?></span></div><?php endif; ?>
    </div>

    <div class="card">
      <h2><?= View::e(ucfirst($libelleFournisseurMin)) ?> final</h2>
      <?php if ($offreRetenue): ?>
        <div class="kv-row"><span class="label">Retenu</span><span class="value"><?= View::e($offreRetenue['fournisseur_nom']) ?></span></div>
        <div class="kv-row"><span class="label">Pays</span><span class="value"><?= View::e((string) ($fo['pays'] ?? '')) ?: '—' ?></span></div>
        <div class="kv-row"><span class="label">Incoterm · délai</span><span class="value"><?= View::e((string) ($offreRetenue['incoterm_negocie'] ?? '')) ?: '—' ?> · <?= View::e((string) ($offreRetenue['delai_livraison'] ?? '')) ?: '—' ?></span></div>
      <?php else: ?>
        <div class="empty-state"><?= !empty($offres) ? 'Offres en cours de comparaison.' : 'En attente d\'offres.' ?></div>
      <?php endif; ?>
      <div style="margin-top:10px"><a href="<?= $base ?>&onglet=achats" class="card-link-hint">Achats et offres →</a></div>
    </div>

    <div class="card">
      <h2>Livraison au client</h2>
      <?php if ($quiTransport === 'fournisseur'): ?>
        <div class="kv-row"><span class="label">Assuré par</span><span class="value">Le fournisseur, inclus dans son prix</span></div>
        <?php if (!empty($offreRetenue['incoterm_negocie'])): ?><div class="kv-row"><span class="label">Incoterm</span><span class="value"><?= View::e($offreRetenue['incoterm_negocie']) ?></span></div><?php endif; ?>
      <?php elseif ($quiTransport === 'transitaire' || $transitaireNom !== '' || $trMontant): ?>
        <div class="kv-row"><span class="label">Transitaire</span><span class="value"><?= $transitaireNom !== '' ? View::e($transitaireNom) : 'À choisir (Modifier le transport)' ?></span></div>
        <?php if (!empty($dossier['tr_mode'])): ?><div class="kv-row"><span class="label">Mode · destination</span><span class="value"><?= View::e(\App\Models\Expedition::MODES[$dossier['tr_mode']] ?? $dossier['tr_mode']) ?> · <?= View::e((string) $dossier['tr_destination']) ?></span></div><?php endif; ?>
        <div class="kv-row"><span class="label">Montant</span><span class="value"><?= $trMontant ?? '—' ?></span></div>
      <?php else: ?>
        <div class="empty-state">Transport à définir.</div>
      <?php endif; ?>
      <div style="margin-top:10px"><a href="<?= $base ?>&onglet=execution#transport" class="card-link-hint">Modifier le transport →</a></div>
    </div>
  </div>

  <div class="fiche-grid" style="margin-top:18px">
    <div class="card">
      <h2>Ce qui s'est passé</h2>
      <?php if (!$journal): ?><div class="empty-state">Rien pour l'instant.</div><?php endif; ?>
      <?php foreach ($journal as $h):
          $titre = \App\Models\AuditLog::libelle((string) $h['action']);
          if ($h['action'] === 'maj_etape_dossier' && !empty($h['details']) && isset(\App\Models\Dossier::ETAPES_LABELS[$h['details']])) { $titre = 'Dossier passé à l\'étape « ' . \App\Models\Dossier::ETAPES_LABELS[$h['details']] . ' »'; }
          if ($h['action'] === 'changement_statut_cotation' && !empty($h['details']) && isset(Cotation::STATUTS[$h['details']])) { $titre = 'Cotation marquée « ' . Cotation::STATUTS[$h['details']] . ' »'; }
      ?>
      <div class="sy-ev"><span class="sy-dt"><?= date('d/m', strtotime($h['created_at'])) ?></span><span class="sy-pt"></span><div><?= View::e($titre) ?><?php if (!empty($h['utilisateur_nom'])): ?><div class="sy-s"><?= View::e($h['utilisateur_nom']) ?></div><?php endif; ?></div></div>
      <?php endforeach; ?>
      <div style="margin-top:8px"><a href="<?= $base ?>&onglet=equipe" class="card-link-hint">Tout l'historique →</a></div>
    </div>
    <div class="card">
      <h2>Notes internes</h2>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/notes">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <textarea name="notes" rows="4" placeholder="Notes internes, points bloquants…"><?= View::e($dossier['notes']) ?></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:8px;flex-wrap:wrap">
          <button type="submit" class="btn btn-sm">Enregistrer</button>
          <a href="<?= $base ?>&onglet=suivi" class="card-link-hint">Notes et échanges →</a>
        </div>
      </form>
      <div style="font-size:12px;color:#999;margin-top:8px">Dernière modification : <?= date('d/m/Y H:i', strtotime($dossier['updated_at'])) ?></div>
    </div>
  </div>
</div>
