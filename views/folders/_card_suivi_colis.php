<?php
use App\Core\View;
use App\Core\Auth;
use App\Models\SuiviColis;
use App\Models\TypeProduit;
use App\Models\Fournisseur;
// Carte « Suivi du colis » : colis confié à un transitaire qui l'expédie dès qu'il peut (pas de départ programmé).
if (!SuiviColis::disponible()) { return; }
$dc = $dossier;
$transitairesC = \App\Models\TransitaireTarif::transitaires(Auth::user());
$typesC = TypeProduit::disponible() ? TypeProduit::actifs((int) $dossier['filiale_id']) : [];
$tidC = (int) ($dc['colis_transitaire_id'] ?? 0) ?: (int) ($dc['tr_transitaire_id'] ?? 0) ?: (int) ($offreRetenue['transitaire_id'] ?? 0);
$paysC = (string) ($dc['colis_pays'] ?? '') !== '' ? (string) $dc['colis_pays'] : (string) ($demande['destination_pays'] ?? '');
$villeC = (string) ($dc['colis_ville'] ?? '') !== '' ? (string) $dc['colis_ville'] : (string) ($demande['lieu_livraison'] ?? '');
// Marchandise saisie sur la demande : préremplit le type de produit et « nombre de colis, poids » tant qu'ils ne sont pas renseignés ici.
$artsC = !empty($demande['id']) ? \App\Models\DemandeArticle::forDemande((int) $demande['id']) : [];
$sumC = ['colis' => 0, 'kg' => 0.0]; $typeArtC = 0;
foreach ($artsC as $ar) { $sumC['colis'] += (int) ($ar['colis'] ?? 0); if (strtolower((string) ($ar['unite'] ?? '')) === 'kg') { $sumC['kg'] += (float) ($ar['quantite'] ?? 0); } if (!$typeArtC && !empty($ar['type_produit_id'])) { $typeArtC = (int) $ar['type_produit_id']; } }
if ((int) ($dc['colis_type_produit_id'] ?? 0) === 0 && $typeArtC) { $dc['colis_type_produit_id'] = $typeArtC; }
if ((string) ($dc['colis_description'] ?? '') === '' && ($sumC['colis'] || $sumC['kg'])) { $dc['colis_description'] = trim(($sumC['colis'] ? $sumC['colis'] . ' colis' : '') . ($sumC['colis'] && $sumC['kg'] ? ' · ' : '') . ($sumC['kg'] ? rtrim(rtrim(number_format($sumC['kg'], 2, '.', ''), '0'), '.') . ' kg' : '')); }
$typeLibC = '';
foreach ($typesC as $t) { if ((int) $t['id'] === (int) ($dc['colis_type_produit_id'] ?? 0)) { $typeLibC = (string) $t['libelle']; } }
$transitaireC = null;
foreach ($transitairesC as $f) { if ((int) $f['id'] === $tidC) { $transitaireC = $f; } }
$etatC = !empty($dc['colis_livre_le']) ? ['Livré', 'success'] : (!empty($dc['colis_parti_le']) ? ['En route vers la destination', 'accent'] : (!empty($dc['colis_recu_le']) ? ['Chez le transitaire', 'warning'] : ['En attente de la marchandise', 'neutre']));
$clientC = !empty($demande['client_id']) ? \App\Models\Client::find((int) $demande['client_id']) : null;
$telClientC = preg_replace('/\D+/', '', (string) ($clientC['telephone'] ?? $demande['expediteur_telephone'] ?? ''));
$retardC = !empty($dc['colis_arrivee_le']) && empty($dc['colis_livre_le']) && $dc['colis_arrivee_le'] < date('Y-m-d') ? (int) ((time() - strtotime($dc['colis_arrivee_le'])) / 86400) : 0;
$msgClientC = "Bonjour" . (!empty($clientC['nom']) ? ' ' . $clientC['nom'] : '') . ",\n\nPoint sur votre colis (dossier " . ($dossier['reference'] ?? '') . ") : "
    . (!empty($dc['colis_livre_le']) ? 'il a été livré le ' . date('d/m/Y', strtotime($dc['colis_livre_le'])) . '.' : (!empty($dc['colis_parti_le']) ? 'il est parti le ' . date('d/m/Y', strtotime($dc['colis_parti_le'])) . ' vers ' . trim($villeC . ($villeC !== '' && $paysC !== '' ? ', ' : '') . $paysC) . (!empty($dc['colis_arrivee_le']) ? ', arrivée estimée le ' . date('d/m/Y', strtotime($dc['colis_arrivee_le'])) : '') . '.' : (!empty($dc['colis_recu_le']) ? 'il est bien arrivé chez notre transitaire, le départ sera programmé dès que possible.' : "nous attendons sa réception chez notre transitaire.")))
    . ((string) ($dc['colis_suivi'] ?? '') !== '' ? "\nN° de suivi : " . $dc['colis_suivi'] : '') . "\n\nBien cordialement";
$w = Auth::canWrite() && ($dossier['statut'] ?? '') === 'actif';
$d = fn($k) => View::e((string) ($dc[$k] ?? ''));
$msg = "Bonjour" . ($transitaireC ? ' ' . $transitaireC['nom'] : '') . ",\n\nUn colis va arriver chez vous (dossier " . ($dossier['reference'] ?? '') . ").\n"
    . ($typeLibC !== '' ? "Type de produit : $typeLibC\n" : '')
    . (($dc['colis_description'] ?? '') !== '' ? 'Colis : ' . $dc['colis_description'] . "\n" : '')
    . (!empty($dc['colis_mode']) ? "Mode d'expédition : " . (\App\Models\Expedition::MODES[$dc['colis_mode']] ?? '') . "\n" : '')
    . 'Destination : ' . trim($villeC . ($villeC !== '' && $paysC !== '' ? ', ' : '') . $paysC) . "\n"
    . (($dc['colis_suivi'] ?? '') !== '' ? 'N° de suivi : ' . $dc['colis_suivi'] . "\n" : '')
    . "\nMerci de me confirmer la réception et la date d'expédition.";
$tel = $transitaireC ? preg_replace('/\D+/', '', (string) ($transitaireC['telephone'] ?? '')) : '';
$mail = $transitaireC ? trim((string) ($transitaireC['email'] ?? '')) : '';
?>
<div class="card" id="suivi-colis">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap"><h2 style="margin:0">Suivi du colis</h2><span class="sc-etat sc-<?= $etatC[1] ?>"><?= View::e($etatC[0]) ?></span></div>
  <?php if ($retardC > 0): ?><div class="amber-alert" style="margin-top:10px">Arrivée estimée dépassée de <?= $retardC ?> jour<?= $retardC > 1 ? 's' : '' ?> (prévue le <?= date('d/m/Y', strtotime($dc['colis_arrivee_le'])) ?>). Demandez des nouvelles au transitaire.</div><?php endif; ?>
  <form method="post" action="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/suivi-colis" id="formColis">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="hint" style="margin:10px 0 6px">Ce que le transitaire doit savoir</div>
    <div class="sc-grid">
      <div class="form-group"><label>Transitaire</label>
        <select name="colis_transitaire_id" <?= $w ? '' : 'disabled' ?>><option value="">Choisir…</option>
          <?php foreach ($transitairesC as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (int) $f['id'] === $tidC ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Type de produit</label>
        <select name="colis_type_produit_id" <?= $w ? '' : 'disabled' ?>><option value="">Choisir…</option>
          <?php foreach ($typesC as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) ($dc['colis_type_produit_id'] ?? 0) ? 'selected' : '' ?>><?= View::e($t['libelle']) ?></option><?php endforeach; ?></select></div>
      <?php if (\App\Core\Schema::colonne('dossiers', 'colis_mode')): ?>
      <div class="form-group"><label>Mode d'expédition</label>
        <select name="colis_mode" <?= $w ? '' : 'disabled' ?>><option value="">Choisir…</option>
          <?php foreach (\App\Models\Expedition::MODES as $k => $l): ?><option value="<?= $k ?>" <?= ($dc['colis_mode'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="form-group"><label>Pays de destination</label><input type="text" name="colis_pays" value="<?= View::e($paysC) ?>" <?= $w ? '' : 'disabled' ?>></div>
      <div class="form-group"><label>Ville de destination</label><input type="text" name="colis_ville" value="<?= View::e($villeC) ?>" <?= $w ? '' : 'disabled' ?>></div>
      <div class="form-group"><label>Nombre de colis, poids</label><input type="text" name="colis_description" value="<?= $d('colis_description') ?>" placeholder="1 colis · 3 kg" <?= $w ? '' : 'disabled' ?>></div>
      <div class="form-group"><label>N° de suivi du transitaire</label><input type="text" name="colis_suivi" value="<?= $d('colis_suivi') ?>" placeholder="ex. KG-482910" <?= $w ? '' : 'disabled' ?>></div>
    </div>
    <div class="hint" style="margin:14px 0 6px">Avancement du colis : saisissez chaque date quand le transitaire vous prévient</div>
    <div class="sc-dates">
      <div><label>Reçu chez le transitaire</label><input type="date" name="colis_recu_le" value="<?= $d('colis_recu_le') ?>" <?= $w ? '' : 'disabled' ?>></div>
      <div><label>Parti le</label><input type="date" name="colis_parti_le" value="<?= $d('colis_parti_le') ?>" <?= $w ? '' : 'disabled' ?>></div>
      <div><label>Arrivée estimée</label><input type="date" name="colis_arrivee_le" value="<?= $d('colis_arrivee_le') ?>" <?= $w ? '' : 'disabled' ?>></div>
      <div><label>Livré le</label><input type="date" name="colis_livre_le" value="<?= $d('colis_livre_le') ?>" <?= $w ? '' : 'disabled' ?>></div>
    </div>
    <div class="hint" style="margin-top:8px">Une date saisie met à jour les étapes de la commande et l'avancement du dossier.</div>
    <?php if ($w): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
      <button type="submit" class="btn btn-sm">Enregistrer</button>
      <?php if ($tel !== ''): ?><a class="btn btn-sm btn-secondary" target="_blank" rel="noopener" href="https://wa.me/<?= $tel ?>?text=<?= rawurlencode($msg) ?>">Prévenir par WhatsApp</a><?php endif; ?>
      <?php if ($telClientC !== ''): ?><a class="btn btn-sm btn-secondary" target="_blank" rel="noopener" href="https://wa.me/<?= $telClientC ?>?text=<?= rawurlencode($msgClientC) ?>">Prévenir le client (WhatsApp)</a><?php endif; ?>
      <?php if ($mail !== ''): ?><a class="btn btn-sm btn-secondary" href="mailto:<?= View::e($mail) ?>?subject=<?= rawurlencode('Colis à recevoir — dossier ' . ($dossier['reference'] ?? '')) ?>&body=<?= rawurlencode($msg) ?>">Prévenir par e-mail</a><?php endif; ?>
    </div>
    <?php if ($transitaireC && $tel === '' && $mail === ''): ?><div class="hint" style="margin-top:8px">Ajoutez le téléphone ou l'e-mail du transitaire sur sa fiche pour pouvoir le prévenir d'ici.</div>
    <?php elseif (!$transitaireC): ?><div class="hint" style="margin-top:8px">Choisissez un transitaire et enregistrez : les boutons WhatsApp et e-mail apparaîtront.</div><?php endif; ?>
    <?php endif; ?>
  </form>
</div>
