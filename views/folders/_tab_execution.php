<?php
use App\Core\View;
use App\Core\Storage;
use App\Models\Dossier;
use App\Models\DossierBudget;
use App\Core\Auth;

// [ajouté 06/10, complété 06/10 pour coller fidèlement à
// ExecutionPrestation.dc.html (maquette), demandé explicitement par Marie
// Laure] Onglet Exécution.
//
// 1) Pilotage de l'étape — voir note existante conservée ci-dessous.
//
// 2) Partie spécifique "Prestation entreprise" : carte "Évaluation du
//    besoin — visite terrain" reprise au complet (type de prestation,
//    visite terrain, délai estimé, site, technicien, constat, les 2
//    champs de mesure adaptatifs + un 3e champ "Contraintes" propre à
//    l'intervention — distinct des "contraintes" de qualification de la
//    Demande affichées sur l'onglet Besoin —, et des photos). Les 5
//    nouveaux champs texte (site/technicien/délai estimé/constat/
//    contraintes) n'existaient nulle part en base avant ce jour — voir
//    migrate_v16.php. Les photos réutilisent le mécanisme de pièce jointe
//    déjà existant (DossierPieceJointe), filtré sur les images, plutôt
//    qu'un nouveau système dédié.
//
//    [06/10, étape 5, migrate_v20.php] Carte "Budget — prévisionnel vs
//    réalisé" ajoutée (confirmé par Marie Laure) : 6 catégories fixes,
//    montants éditables, total prévisionnel / réalisé. La carte "Aperçu —
//    5 variantes" de la maquette (utile en maquette statique, redondante ici
//    puisque le sélecteur réel est déjà vivant) reste volontairement non
//    codée — choix délibéré, pas un oubli.
?>
<div class="card">
  <h2>Étape et suivi</h2>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/etape">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <label>Étape</label>
    <select name="etape" class="stage-select" onchange="this.form.submit()">
      <?php foreach (Dossier::ETAPES as $etape): ?>
        <option value="<?= $etape ?>" <?= $dossier['etape'] === $etape ? 'selected' : '' ?>><?= Dossier::ETAPES_LABELS[$etape] ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($typeDossier === 'prestation_entreprise'): ?>
<div class="card">
  <h2>Évaluation du besoin — visite terrain</h2>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/prestation">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
      <div class="form-group">
        <label>Type de prestation</label>
        <select name="type_prestation" id="type-prestation-select" onchange="this.form.submit()">
          <option value="">— Choisir —</option>
          <?php foreach (Dossier::TYPES_PRESTATION as $tp): ?>
            <option value="<?= $tp ?>" <?= ($dossier['type_prestation'] ?? '') === $tp ? 'selected' : '' ?>><?= Dossier::TYPES_PRESTATION_LABELS[$tp] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Visite terrain</label>
        <div class="checkbox-field">
          <input type="checkbox" name="visite_terrain_necessaire" value="1" <?= empty($dossier['visite_terrain_necessaire']) ? '' : 'checked' ?>>
          <span>Nécessaire pour ce type de prestation</span>
        </div>
      </div>
      <div class="form-group"><label>Délai estimé</label><input type="text" name="prestation_delai_estime" value="<?= View::e($dossier['prestation_delai_estime'] ?? '') ?>" placeholder="ex. 3 jours d'intervention"></div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Site</label><input type="text" name="prestation_site" value="<?= View::e($dossier['prestation_site'] ?? '') ?>"></div>
      <div class="form-group"><label>Technicien</label><input type="text" name="prestation_technicien" value="<?= View::e($dossier['prestation_technicien'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Constat</label><textarea name="prestation_constat" rows="2"><?= View::e($dossier['prestation_constat'] ?? '') ?></textarea></div>

    <label style="display:block;font-size:13px;font-weight:600;color:#333;margin:10px 0 6px">Mesures spécifiques au type de prestation</label>
    <?php
      $champs = Dossier::TYPES_PRESTATION_CHAMPS[$dossier['type_prestation'] ?? ''] ?? null;
    ?>
    <?php if ($champs): ?>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
        <div class="form-group">
          <label><?= View::e($champs[0]) ?></label>
          <input type="text" name="prestation_mesure_1" value="<?= View::e($dossier['prestation_mesure_1'] ?? '') ?>">
        </div>
        <?php if ($champs[1] !== null): ?>
          <div class="form-group">
            <label><?= View::e($champs[1]) ?></label>
            <input type="text" name="prestation_mesure_2" value="<?= View::e($dossier['prestation_mesure_2'] ?? '') ?>">
          </div>
        <?php endif; ?>
        <div class="form-group"><label>Contraintes</label><input type="text" name="prestation_contraintes" value="<?= View::e($dossier['prestation_contraintes'] ?? '') ?>" placeholder="ex. intervention hors horaires d'exploitation"></div>
      </div>
    <?php else: ?>
      <div class="empty-state" style="margin-bottom:16px">Choisissez un type de prestation pour voir les champs de mesure correspondants.</div>
    <?php endif; ?>

    <div class="subtitle" style="margin-bottom:12px">Le type de prestation n'influence que les 2 champs de mesure ci-dessus — la case "Visite terrain" reste toujours à cocher manuellement, quel que soit le type choisi.</div>

    <button type="submit" class="btn btn-sm">Enregistrer</button>
  </form>

  <?php
    $photos = array_values(array_filter($piecesJointes, fn($p) => str_starts_with($p['type_mime'], 'image/')));
  ?>
  <label style="display:block;font-size:13px;font-weight:600;color:#333;margin:18px 0 6px">Photos</label>
  <div class="photo-grid">
    <?php foreach (array_slice($photos, 0, 6) as $p): ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/telecharger&apercu=1" target="_blank">
        <img class="photo-thumb" src="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/telecharger&apercu=1" alt="<?= View::e($p['nom_original']) ?>">
      </a>
    <?php endforeach; ?>
    <?php if (empty($photos)): ?>
      <div class="photo-ph"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
    <?php endif; ?>
  </div>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces" enctype="multipart/form-data" style="margin-top:10px">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="onglet_retour" value="execution">
    <input type="file" name="fichier" accept="image/*" required>
    <button type="submit" class="btn btn-sm" style="margin-top:8px">Ajouter une photo</button>
  </form>
</div>

<?php
  $icones = [
    'main_oeuvre' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
    'consommables' => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>',
    'materiel_disponible' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    'materiel_achat_location' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    'sous_traitance' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>',
    'deplacement_transport' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
  ];
  $bl = $budgetLignes ?? [];
  $devB = 'FCFA';
  foreach ($bl as $l) { $devB = $l['devise']; break; }
  $totB = DossierBudget::totaux($bl);
  $fmtB = fn($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ') . ' ' . $devB;
  $editB = Auth::canWrite();
?>
<div class="card">
  <h2>Budget — prévisionnel vs réalisé</h2>
  <?php if ($editB): ?>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/budget">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <?php endif; ?>
  <?php foreach (DossierBudget::CATEGORIES as $cat => $label): $l = $bl[$cat] ?? null; ?>
    <div class="budget-row">
      <div class="bl"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $icones[$cat] ?></svg> <?= View::e($label) ?></div>
      <?php if ($editB): ?>
        <div class="bv">Prévisionnel<input type="text" inputmode="decimal" name="previsionnel[<?= $cat ?>]" value="<?= $l ? (float) $l['montant_previsionnel'] + 0 : '' ?>" placeholder="0"></div>
        <div class="bv">Réalisé<input type="text" inputmode="decimal" name="realise[<?= $cat ?>]" value="<?= ($l && $l['montant_realise'] !== null) ? (float) $l['montant_realise'] + 0 : '' ?>" placeholder="—"></div>
        <div class="bv"><input type="text" name="detail[<?= $cat ?>]" maxlength="255" value="<?= View::e($l['detail'] ?? '') ?>" placeholder="Précisions"></div>
      <?php else: ?>
        <div class="bv">Prévisionnel<strong><?= $fmtB($l ? $l['montant_previsionnel'] : 0) ?></strong></div>
        <div class="bv">Réalisé<strong><?= $fmtB($l['montant_realise'] ?? null) ?></strong></div>
        <div class="bv"><?= View::e($l['detail'] ?? '') ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="budget-total"><span>Total prévisionnel</span><span><?= $fmtB($totB['previsionnel']) ?></span></div>
  <?php if ($totB['realise'] !== null): ?>
    <div class="budget-total" style="border-top:none;margin-top:0;padding-top:4px"><span>Total réalisé</span><span><?= $fmtB($totB['realise']) ?></span></div>
  <?php endif; ?>
  <?php if ($editB): ?>
    <div style="display:flex;gap:10px;align-items:center;margin-top:10px">
      <select name="devise" style="width:auto">
        <?php foreach (DossierBudget::DEVISES as $dv): ?><option value="<?= $dv ?>" <?= $devB === $dv ? 'selected' : '' ?>><?= $dv ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm">Enregistrer le budget</button>
    </div>
  </form>
  <?php endif; ?>
  <div class="hint">L'achat de matériel ou de consommables intervient après l'accord du client ; avant l'accord, seules des consultations fournisseurs et des estimations sont possibles.</div>
</div>

<div class="card">
  <h2>Intervention</h2>
  <?php if ($cotation && $cotation['statut'] === 'acceptee'): ?>
    <div class="empty-state">Intervention à planifier — cotation acceptée par le client.</div>
  <?php else: ?>
    <div class="empty-state">Intervention non encore planifiée — en attente de l'accord client sur la cotation.</div>
  <?php endif; ?>
</div>
<?php endif; ?>
