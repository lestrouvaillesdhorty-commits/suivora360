<?php
use App\Core\View;
use App\Models\Expedition;
/** Fiche expédition. Variables : $exp, $reservations, $bilan, $dossiers, $voitMarges, $peutEcrire, $csrfToken. */
$fmt = fn($n, $d = 0) => number_format((float) $n, $d, ',', ' ');
$dev = View::e($exp['devise']);
$base = '/index.php?r=expeditions/' . (int) $exp['id'];
$ouverte = $exp['statut'] === 'ouverte';
$complet = $exp['type'] === 'conteneur_complet';
$tarifMode = Expedition::modeAchat($exp) === 'tarif';
?>
<div class="page-header">
  <h1><?= View::e($exp['reference']) ?> · <?= View::e($exp['origine']) ?> → <?= View::e($exp['destination']) ?></h1>
  <?php if ($peutEcrire): ?><a class="btn" href="<?= $base ?>/modifier">Modifier</a><?php endif; ?>
</div>
<div class="card">
  <div class="kv-row"><span class="label">Type</span><span class="value"><?= View::e(Expedition::TYPES[$exp['type']]) ?> · <?= View::e(Expedition::MODES[$exp['mode']]) ?></span></div>
  <?php if ($exp['partenaire']): ?><div class="kv-row"><span class="label">Partenaire</span><span class="value"><?php if (!empty($exp['fournisseur_id'])): ?><a href="/index.php?r=fournisseurs/<?= (int) $exp['fournisseur_id'] ?>"><?= View::e($exp['partenaire']) ?></a><?php else: ?><?= View::e($exp['partenaire']) ?><?php endif; ?></span></div><?php endif; ?>
  <div class="kv-row"><span class="label">Dates</span><span class="value">Clôture <?= $exp['cloture_reservations'] ? date('d/m/Y', strtotime($exp['cloture_reservations'])) : '—' ?> · Départ <?= $exp['date_depart'] ? date('d/m/Y', strtotime($exp['date_depart'])) : '—' ?> · Arrivée <?= $exp['date_arrivee'] ? date('d/m/Y', strtotime($exp['date_arrivee'])) : '—' ?></span></div>
  <?php if ($exp['numero_suivi']): ?><div class="kv-row"><span class="label">N° de suivi</span><span class="value"><?= View::e($exp['numero_suivi']) ?></span></div><?php endif; ?>
  <div class="kv-row"><span class="label">Statut</span><span class="value">
    <?php if ($peutEcrire): ?>
    <form method="post" action="<?= $base ?>/statut" style="display:inline-flex;gap:6px;align-items:center">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <select name="statut"><?php foreach (Expedition::STATUTS as $k => $l): ?><option value="<?= $k ?>" <?= $exp['statut'] === $k ? 'selected' : '' ?>><?= View::e(Expedition::libelleStatut($exp, $k)) ?></option><?php endforeach; ?></select>
      <button class="btn" type="submit">Changer</button>
    </form>
    <?php else: ?><?= View::e(Expedition::libelleStatut($exp)) ?><?php endif; ?>
  </span></div>
  <div class="hint">Le statut fait avancer les étapes Expédition, Dédouanement et Livraison des dossiers liés (jamais en arrière).</div>
</div>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:12px 0">
  <?php if ($tarifMode): ?>
  <div class="card"><div class="label">Volume réservé</div><strong style="font-size:1.4em"><?= $fmt($bilan['volume'], 2) ?> m³</strong><div class="hint"><?= $fmt($bilan['poids']) ?> kg</div></div>
  <?php else: ?>
  <div class="card"><div class="label">Remplissage</div><strong style="font-size:1.4em"><?= $bilan['remplissage'] !== null ? (int) $bilan['remplissage'] . ' %' : '—' ?></strong><div class="hint"><?= $fmt($bilan['volume'], 2) ?> m³ · <?= $fmt($bilan['poids']) ?> kg</div></div>
  <?php endif; ?>
  <div class="card"><div class="label">Vendu</div><strong style="font-size:1.4em"><?= $fmt($bilan['vente']) ?> <?= $dev ?></strong><div class="hint"><?= (int) $bilan['nb'] ?> réservation(s)</div></div>
  <?php if ($voitMarges): ?>
  <div class="card"><div class="label">Coût d'achat<?= $tarifMode ? ' (selon le tarif)' : '' ?></div><strong style="font-size:1.4em"><?= $fmt($bilan['cout'] ?? $exp['cout_achat']) ?> <?= $dev ?></strong></div>
  <?php if (!$tarifMode): ?><div class="card"><div class="label">Capacité non vendue</div><strong style="font-size:1.4em"><?= $fmt($bilan['libre']) ?> <?= $dev ?></strong><div class="hint">achetée, non utilisée : perte</div></div><?php endif; ?>
  <div class="card"><div class="label">Résultat</div><strong style="font-size:1.4em;color:<?= $bilan['resultat'] < 0 ? '#b3261e' : '#1e7a3c' ?>"><?= $fmt($bilan['resultat']) ?> <?= $dev ?></strong></div>
  <?php endif; ?>
</div>
<?php if ($bilan['surcharge']): ?><div class="alert alert-erreur">La capacité de l'expédition est dépassée.</div><?php endif; ?>

<div class="card">
  <h2>Réservations</h2>
  <?php if (!$reservations): ?>
    <div class="empty-state">Aucune réservation pour le moment.</div>
  <?php else: ?>
  <table class="table responsive-cards">
    <thead><tr><th>Client / dossier</th><th>Marchandise</th><th>Volume</th><th>Poids</th><th>Colis / pal.</th><th>Prix vendu</th><?php if ($voitMarges): ?><th>Coût imputé</th><th>Marge</th><?php endif; ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($reservations as $r): $l = $bilan['lignes'][(int) $r['id']] ?? ['cout' => 0, 'marge' => 0]; ?>
      <tr>
        <td data-label="Client"><?= View::e($r['client_nom'] ?: '—') ?><?php if (!empty($r['dossier_id'])): ?> · <a href="/index.php?r=dossiers/<?= (int) $r['dossier_id'] ?>">dossier</a><?php endif; ?><?php if ($r['description']): ?><div class="hint"><?= View::e($r['description']) ?></div><?php endif; ?>
          <?php if (!empty($r['cas_special'])): ?><div><span class="badge <?= ($r['validation_client'] ?? 'ok') === 'a_valider' ? 'badge-orange' : 'badge-green' ?>"><?= View::e($r['type_libelle'] ?? (Expedition::CAS_SPECIAUX[$r['cas_special']] ?? $r['cas_special'])) ?> · <?= ($r['validation_client'] ?? 'ok') === 'a_valider' ? 'à valider avec le client' : 'validé' . (!empty($r['valide_le']) ? ' le ' . date('d/m/Y', strtotime($r['valide_le'])) : '') ?></span>
          <?php if (($r['validation_client'] ?? 'ok') === 'a_valider' && $peutEcrire): ?>
            <form method="post" action="<?= $base ?>/valider-reservation" style="display:inline"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-secondary" type="submit">Marquer validé</button></form>
          <?php endif; ?></div><?php endif; ?></td>
        <td data-label="Marchandise"><?= View::e(($r['type_libelle'] ?? '') !== '' ? $r['type_libelle'] : (Expedition::MARCHANDISES[$r['type_marchandise']] ?? '')) ?></td>
        <td data-label="Volume"><?= $r['volume'] !== null ? $fmt($r['volume'], 2) . ' m³' : '—' ?></td>
        <td data-label="Poids"><?= $r['poids'] !== null ? $fmt($r['poids']) . ' kg' : '—' ?><?php if ($r['poids'] !== null && Expedition::v60()): ?><div class="hint"><?= !empty($r['poids_confirme']) ? 'confirmé' : 'estimé' ?></div><?php endif; ?></td>
        <td data-label="Colis / pal."><?= (int) $r['colis'] ?> / <?= (int) $r['palettes'] ?></td>
        <td data-label="Prix vendu"><?= $fmt($r['prix_vente']) ?> <?= $dev ?><div class="hint"><?= View::e(Expedition::TARIFICATIONS[$r['tarification']] ?? '') ?><?= !empty($r['supplement_vente']) && (float) $r['supplement_vente'] > 0 ? ' · dont supplément ' . $fmt($r['supplement_vente']) : '' ?></div></td>
        <?php if ($voitMarges): ?>
        <td data-label="Coût imputé"><?= $fmt($l['cout']) ?><?php if (!empty($r['supplement_cout']) && (float) $r['supplement_cout'] > 0): ?><div class="hint">dont supplément <?= $fmt($r['supplement_cout']) ?><?= !empty($r['supplement_motif']) ? ' (' . View::e($r['supplement_motif']) . ')' : '' ?></div><?php endif; ?></td>
        <td data-label="Marge" style="color:<?= $l['marge'] < 0 ? '#b3261e' : 'inherit' ?>"><?= $fmt($l['marge']) ?></td>
        <?php endif; ?>
        <td><?php if ($peutEcrire): ?><div style="display:flex;gap:6px;align-items:flex-start;flex-wrap:wrap">
          <details style="margin:0"><summary class="btn btn-sm btn-secondary" style="display:inline-block;cursor:pointer;padding:0 12px;height:36px;min-height:36px;line-height:36px;font-size:13px;list-style:none;box-sizing:border-box">Modifier</summary>
            <form method="post" action="<?= $base ?>/modifier-reservation" style="margin-top:6px;min-width:190px">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>">
              <?php if (!empty($typesProduit)): ?><div class="form-group"><label>Type de produit</label><select name="type_produit_id"><option value="">Type actuel</option><?php foreach ($typesProduit as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($r['type_produit_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= View::e($t['libelle']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
              <div class="form-group"><label>Volume (m³)</label><input type="text" name="volume" inputmode="decimal" value="<?= $r['volume'] !== null ? View::e((string) $r['volume']) : '' ?>"></div>
              <div class="form-group"><label>Poids (kg)</label><input type="text" name="poids" inputmode="decimal" value="<?= $r['poids'] !== null ? View::e((string) $r['poids']) : '' ?>"></div>
              <div class="form-group"><label>Colis</label><input type="text" name="colis" inputmode="numeric" value="<?= $r['colis'] !== null ? (int) $r['colis'] : '' ?>"></div>
              <div class="form-group"><label>Palettes</label><input type="text" name="palettes" inputmode="numeric" value="<?= $r['palettes'] !== null ? (int) $r['palettes'] : '' ?>"></div>
              <?php if (Expedition::v60()): ?><label style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" name="poids_confirme" value="1" <?= !empty($r['poids_confirme']) ? 'checked' : '' ?>> Poids confirmé par le transitaire</label><?php endif; ?>
              <div class="hint">Le prix vendu et le coût sont recalculés.</div>
              <button class="btn btn-sm btn-primary" type="submit">Enregistrer</button>
            </form>
          </details>
          <form method="post" action="<?= $base ?>/supprimer-reservation" onsubmit="return confirm('Retirer cette réservation ?')" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-sm btn-secondary" type="submit" style="color:#b3261e;border-color:#e3b5b1;padding:0 12px;height:36px;min-height:36px;font-size:13px;box-sizing:border-box">Retirer</button></form></div>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php $nbAV = 0; foreach ($reservations as $rv) { if (($rv['validation_client'] ?? 'ok') === 'a_valider') { $nbAV++; } } ?>
<?php if ($nbAV > 0): ?><div class="alert alert-erreur"><?= $nbAV ?> réservation(s) à valider avec le client avant le départ (cas particuliers).</div><?php endif; ?>
<?php if ($tarifMode && empty($exp['tarif_achat_type'])): ?><div class="alert alert-erreur">Le tarif d'achat du transitaire n'est pas renseigné : les coûts sont à zéro. <a href="<?= $base ?>/modifier">Le renseigner</a></div><?php endif; ?>

<?php if ($peutEcrire && $ouverte && !($complet && $reservations)): ?>
<div class="card">
  <h2>Ajouter une réservation</h2>
  <?php include __DIR__ . '/_form_reservation.php'; ?>
</div>
<?php endif; ?>

<?php
$docsOk = \App\Models\ExpeditionDocument::disponible();
$transitaire = \App\Models\ExpeditionDocument::formalitesTransitaire($exp);
$nbManq = $docsOk && !$transitaire ? \App\Models\ExpeditionDocument::nbManquants($docs ?? []) : 0;
if ($docsOk): ?>
<div class="card" id="docs" style="<?= $transitaire ? 'padding-top:12px;padding-bottom:12px' : '' ?>">
  <?php if ($transitaire): ?>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between"><span><strong>Documents</strong> · <span class="hint">formalités gérées par le transitaire, aucun document demandé</span></span>
  <?php if ($peutEcrire): ?><form method="post" action="<?= $base ?>/formalites" style="margin:0"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><button class="btn btn-sm btn-secondary" type="submit">Suivre les documents ici</button></form><?php endif; ?></div>
  <?php else: ?>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between"><h2 style="margin:0">Documents à fournir</h2>
  <?php if ($peutEcrire): ?><form method="post" action="<?= $base ?>/formalites" style="margin:0"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="formalites_transitaire" value="1"><button class="btn btn-sm btn-secondary" type="submit">Confier au transitaire</button></form><?php endif; ?></div>
  <?php endif; ?>
  <?php if (!$transitaire): ?>
    <?php if ($nbManq > 0): ?><div class="alert alert-erreur" style="margin-top:10px"><?= $nbManq ?> document(s) manquant(s). Cela ne bloque pas le départ.</div><?php elseif ($reservations): ?><div class="alert alert-succes" style="margin-top:10px">Tous les documents attendus sont reçus.</div><?php endif; ?>
    <?php if (!$reservations): ?><div class="hint" style="margin-top:8px">Les documents attendus apparaissent dès qu'une réservation est ajoutée.</div><?php endif; ?>
    <?php foreach ($reservations as $r): $lignes = $docs[(int) $r['id']] ?? []; ?>
    <div style="border-top:1px solid #eee;margin-top:10px;padding-top:8px">
      <strong><?= View::e($r['client_nom'] ?: ($r['dossier_reference'] ?? 'Réservation')) ?></strong>
      <span class="hint"> · <?= View::e(($r['type_libelle'] ?? '') !== '' ? $r['type_libelle'] : ((Expedition::MARCHANDISES[$r['type_marchandise']] ?? '') . (!empty($r['cas_special']) ? ' · ' . (Expedition::CAS_SPECIAUX[$r['cas_special']] ?? '') : ''))) ?></span>
      <?php foreach ($lignes as $d): ?>
      <form method="post" action="<?= $base ?>/documents" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:6px 0">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="doc_id" value="<?= (int) $d['id'] ?>">
        <span style="flex:1;min-width:180px"><?= View::e($d['libelle']) ?><?php if ($d['statut'] === 'recu' && $d['recu_le']): ?> <span class="hint">reçu le <?= date('d/m/Y', strtotime($d['recu_le'])) ?></span><?php endif; ?></span>
        <?php if ($peutEcrire): ?>
        <select name="statut" aria-label="État" style="width:auto;flex:0 0 150px"><?php foreach (\App\Models\ExpeditionDocument::STATUTS as $k => $l): ?><option value="<?= $k ?>" <?= $d['statut'] === $k ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select>
        <input type="text" name="note" value="<?= View::e($d['note']) ?>" maxlength="200" placeholder="Note (n° du certificat…)" style="width:auto;flex:1 1 200px;min-width:160px" aria-label="Note">
        <button class="btn btn-sm btn-secondary" type="submit" style="width:auto">OK</button>
        <?php else: ?><span><?= View::e(\App\Models\ExpeditionDocument::STATUTS[$d['statut']] ?? '') ?></span><?php endif; ?>
      </form>
      <?php endforeach; ?>
      <?php if ($peutEcrire): ?>
      <form method="post" action="<?= $base ?>/documents" style="display:flex;gap:8px;margin:6px 0;flex-wrap:wrap">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="ajouter">
        <input type="text" name="libelle" maxlength="150" placeholder="Ajouter un document pour cette réservation" style="width:auto;flex:1 1 200px;min-width:200px" aria-label="Nouveau document">
        <button class="btn btn-sm" type="submit">Ajouter</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
