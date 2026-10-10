<?php
use App\Core\Storage;
use App\Core\View;
use App\Models\FournisseurPieceJointe;

$tailleLisible = function (int $o): string {
    if ($o >= 1048576) { return number_format($o / 1048576, 1, ',', ' ') . ' Mo'; }
    return max(1, (int) round($o / 1024)) . ' Ko';
};
$parCategorie = [];
foreach ($pieces as $p) { $parCategorie[$p['categorie']][] = $p; }
?>
<?php if ($peutEcrire): ?>
<div class="card">
  <h2>Ajouter un document</h2>
  <form method="post" action="<?= $base ?>/pieces" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group" style="margin:0;flex:2;min-width:220px"><label for="fichier">Fichier</label><input type="file" id="fichier" name="fichier" required></div>
    <div class="form-group" style="margin:0;flex:1;min-width:170px">
      <label for="categorie">Catégorie</label>
      <select id="categorie" name="categorie"><?php foreach (FournisseurPieceJointe::CATEGORIES as $code => $lib): ?><option value="<?= $code ?>"><?= View::e($lib) ?></option><?php endforeach; ?></select>
    </div>
    <?php if ($schemaPret): ?>
    <div class="form-group" style="margin:0;min-width:170px"><label for="expire_le">Échéance (si applicable)</label><input type="date" id="expire_le" name="expire_le"></div>
    <?php endif; ?>
    <button type="submit" class="btn">Ajouter</button>
  </form>
  <div style="font-size:12px;color:#888;margin-top:8px">Formats : <?= View::e(implode(', ', FournisseurPieceJointe::EXTENSIONS_AUTORISEES)) ?> — 10 Mo maximum. Une échéance (assurance, certification…) déclenche une alerte 30 jours avant ; sans échéance, aucune alerte. Documents réservés à l’équipe de votre entreprise.</div>
</div>
<?php endif; ?>

<div class="card">
  <h2>Documents du fournisseur</h2>
  <?php if (empty($pieces)): ?>
    <div class="empty-state">Aucun document pour ce fournisseur.</div>
  <?php else: foreach (FournisseurPieceJointe::CATEGORIES as $code => $lib): if (empty($parCategorie[$code])) { continue; } ?>
    <h3 style="font-size:14px;margin:14px 0 6px"><?= View::e($lib) ?></h3>
    <?php foreach ($parCategorie[$code] as $p): $url = $base . '/pieces/' . (int) $p['id'] . '/telecharger'; [$etat, $texteEtat] = FournisseurPieceJointe::etatEcheance($p['expire_le'] ?? null); ?>
      <div class="info-row" style="flex-wrap:wrap;gap:8px">
        <span><strong><?= View::e($p['nom_original']) ?></strong>
          <?php if ($etat !== 'aucune'): ?> <span class="badge <?= $etat === 'expire' ? 'badge-red' : ($etat === 'bientot' ? 'badge-orange' : 'badge-green') ?>"><?= View::e($texteEtat) ?></span><?php endif; ?><br>
          <span style="font-size:12px;color:#888">Ajouté le <?= date('d/m/Y', strtotime($p['created_at'])) ?> · <?= $tailleLisible((int) $p['taille']) ?><?= !empty($p['uploaded_by_nom']) ? ' · ' . View::e($p['uploaded_by_nom']) : '' ?></span></span>
        <span style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?php if (Storage::estPrevisualisable($p['type_mime'])): ?><a class="btn btn-sm btn-secondary" target="_blank" rel="noopener" href="<?= $url ?>&apercu=1"<?= \App\Core\Storage::attrsPhoto($p) ?>>Aperçu</a><?php endif; ?>
          <a class="btn btn-sm btn-secondary" href="<?= $url ?>">Télécharger</a>
          <?php if ($peutEcrire): ?>
          <details><summary class="btn btn-sm btn-secondary" style="list-style:none;cursor:pointer">Supprimer</summary>
            <form method="post" action="<?= $base ?>/pieces/<?= (int) $p['id'] ?>/supprimer" style="margin-top:6px">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <button type="submit" class="btn btn-sm" style="background:#b42318">Confirmer la suppression</button>
            </form>
          </details>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endforeach; endif; ?>
</div>
