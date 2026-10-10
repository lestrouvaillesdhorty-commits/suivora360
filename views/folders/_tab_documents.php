<?php
use App\Core\Auth;
use App\Core\View;
use App\Models\DossierPieceJointe;

// [réécrit 06/10, étape 4 du découpage Dossiers] Onglet Documents conforme
// à la maquette Documents.dc.html : fichiers rangés par catégorie
// (DossierPieceJointe::CATEGORIES), avec visibilité Interne / Visible client
// (migrate_v19). La visibilité est une étiquette : aucun espace client ne la
// lit encore, "Interne" par défaut. Les pièces jointes antérieures à la
// migration tombent dans "Autres documents / Interne" et se reclassent ici.
$parCategorie = [];
foreach ($piecesJointes as $p) {
    $parCategorie[DossierPieceJointe::categorieValide($p['categorie'] ?? null)][] = $p;
}
$peutEcrire = Auth::canWrite();
?>
<?php if ($peutEcrire): ?>
<div class="card">
  <h2 style="margin-bottom:10px">Ajouter un document</h2>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="onglet_retour" value="documents">
    <div class="form-row" style="align-items:flex-end">
      <div class="form-group" style="flex:1.6"><label>Fichier ou photo</label><input type="file" name="fichier" required></div>
      <div class="form-group">
        <label>Catégorie</label>
        <select name="categorie">
          <?php foreach (DossierPieceJointe::CATEGORIES as $code => $label): ?>
            <option value="<?= $code ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Visibilité</label>
        <select name="visibilite">
          <?php foreach (DossierPieceJointe::VISIBILITES as $code => $label): ?>
            <option value="<?= $code ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:0 0 auto"><button type="submit" class="btn">+ Ajouter</button></div>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if (empty($piecesJointes)): ?>
  <div class="card"><div class="empty-state">Aucun document pour ce dossier.</div></div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
  <?php $premiere = true; foreach (DossierPieceJointe::CATEGORIES as $codeCat => $labelCat): ?>
    <?php if (empty($parCategorie[$codeCat])) { continue; } ?>
    <div class="doc-cat-head" <?= $premiere ? '' : 'style="border-top:1px solid var(--border);margin-top:4px"' ?>><?= View::e($labelCat) ?></div>
    <?php $premiere = false; ?>
    <div class="table-scroll">
    <table class="dtable">
      <thead><tr><th>Nom</th><th>Date</th><th>Auteur</th><th>Visibilité</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($parCategorie[$codeCat] as $p): ?>
        <tr>
          <td class="row-title"><?= View::e($p['nom_original']) ?> <span style="font-weight:400;color:#888;font-size:12px">· <?= number_format($p['taille'] / 1024, 0) ?> Ko</span></td>
          <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
          <td><?= View::e($p['uploaded_by_nom']) ?></td>
          <td><span class="visibility-tag <?= ($p['visibilite'] ?? 'interne') === 'client' ? 'client' : 'interne' ?>"><?= View::e(DossierPieceJointe::VISIBILITES[$p['visibilite'] ?? 'interne'] ?? 'Interne') ?></span></td>
          <td>
            <?php if (\App\Core\Storage::estPrevisualisable($p['type_mime'])): ?>
              <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/telecharger&apercu=1" target="_blank" class="btn btn-sm btn-secondary"<?= \App\Core\Storage::attrsPhoto($p) ?>>Aperçu</a>
            <?php endif; ?>
            <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/telecharger" class="btn btn-sm btn-secondary">Télécharger</a>
            <?php if ($peutEcrire): ?>
            <details style="display:inline-block;vertical-align:top">
              <summary class="btn btn-sm btn-secondary" style="list-style:none;cursor:pointer">Classer</summary>
              <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/classer" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <select name="categorie">
                  <?php foreach (DossierPieceJointe::CATEGORIES as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $codeCat === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="visibilite">
                  <?php foreach (DossierPieceJointe::VISIBILITES as $code => $label): ?>
                    <option value="<?= $code ?>" <?= ($p['visibilite'] ?? 'interne') === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm">Enregistrer</button>
              </form>
            </details>
            <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/supprimer" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <button type="submit" class="btn btn-sm btn-secondary">Supprimer</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="hint" style="margin-top:10px">La séparation interne / visible client est préservée à chaque étape — un document marqué « Interne » n'apparaît jamais côté client.</div>
