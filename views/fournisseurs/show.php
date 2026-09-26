<?php use App\Core\View;
use App\Models\Demande;
use App\Models\Fournisseur;
use App\Models\FournisseurPieceJointe;

$waNumber = preg_replace('/[^0-9]/', '', $fournisseur['telephone'] ?? '');
$statutInfo = Fournisseur::STATUTS[$fournisseur['statut'] ?? 'a_qualifier'] ?? ucfirst($fournisseur['statut'] ?? '');
$statutBadge = Fournisseur::STATUT_BADGES[$fournisseur['statut'] ?? 'a_qualifier'] ?? 'badge-gray';
$noteGlobale = Fournisseur::noteGlobale($fournisseur);
$incotermsPratiques = !empty($fournisseur['incoterms_pratiques']) ? explode(',', $fournisseur['incoterms_pratiques']) : [];
?>
<a href="/index.php?r=fournisseurs" style="font-size:13px;color:#666">&larr; Retour aux fournisseurs</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($fournisseur['nom']) ?> <span class="badge <?= $statutBadge ?>"><?= $statutInfo ?></span></h1>
    <div class="subtitle">
      <?= View::e($fournisseur['code'] ?? '') ?>
      <?= $fournisseur['secteur'] ? ' — ' . View::e($fournisseur['secteur']) : '' ?><?= $fournisseur['devise'] ? ' — ' . View::e($fournisseur['devise']) : '' ?>
      <?= $noteGlobale !== null ? ' — Note : ' . $noteGlobale . '/5' : '' ?>
    </div>
  </div>
  <div style="white-space:nowrap">
    <a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/modifier" class="btn btn-secondary">Modifier</a>
    <?php if ((int) $fournisseur['is_active'] === 1): ?>
      <form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/desactiver" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-secondary">Désactiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/activer" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn">Réactiver</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div style="margin:12px 0">
  <?php if ($waNumber): ?>
    <a href="https://wa.me/<?= $waNumber ?>" target="_blank" class="btn btn-sm" style="background:#2E7D5B">WhatsApp</a>
  <?php endif; ?>
  <?php if (!empty($fournisseur['email'])): ?>
    <a href="mailto:<?= View::e($fournisseur['email']) ?>" class="btn btn-sm btn-secondary">E-mail</a>
  <?php endif; ?>
  <?php if (!empty($fournisseur['site_web'])): ?>
    <a href="<?= str_starts_with($fournisseur['site_web'], 'http') ? View::e($fournisseur['site_web']) : 'https://' . View::e($fournisseur['site_web']) ?>" target="_blank" class="btn btn-sm btn-secondary">Site web</a>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Coordonnées</h2>
  <div class="info-row"><span class="label">Fonction du contact</span><span><?= View::e($fournisseur['fonction_contact'] ?? '') ?: '—' ?></span></div>
  <div class="info-row"><span class="label">E-mail</span><span><?= View::e($fournisseur['email']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Téléphone</span><span><?= View::e($fournisseur['telephone']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Pays</span><span><?= View::e($fournisseur['pays']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Ville</span><span><?= View::e($fournisseur['ville']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Adresse</span><span><?= View::e($fournisseur['adresse']) ?: '—' ?></span></div>
</div>

<?php if (!empty($fournisseur['categories_produits']) || !empty($fournisseur['marques']) || !empty($fournisseur['pays_desservis']) || !empty($fournisseur['quantite_min']) || !empty($incotermsPratiques)): ?>
<div class="card">
  <h2>Offre commerciale</h2>
  <?php if (!empty($fournisseur['categories_produits'])): ?><div class="info-row"><span class="label">Catégories de produits</span><span><?= View::e($fournisseur['categories_produits']) ?></span></div><?php endif; ?>
  <?php if (!empty($fournisseur['marques'])): ?><div class="info-row"><span class="label">Marques</span><span><?= View::e($fournisseur['marques']) ?></span></div><?php endif; ?>
  <?php if (!empty($fournisseur['pays_desservis'])): ?><div class="info-row"><span class="label">Pays desservis</span><span><?= View::e($fournisseur['pays_desservis']) ?></span></div><?php endif; ?>
  <?php if (!empty($fournisseur['quantite_min'])): ?><div class="info-row"><span class="label">Quantité minimale</span><span><?= View::e($fournisseur['quantite_min']) ?></span></div><?php endif; ?>
  <?php if (!empty($incotermsPratiques)): ?><div class="info-row"><span class="label">Incoterms pratiqués</span><span><?= View::e(implode(', ', $incotermsPratiques)) ?></span></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($noteGlobale !== null || array_filter(Fournisseur::CRITERES_NOTE, fn($l, $c) => !empty($fournisseur[$c] ?? null), ARRAY_FILTER_USE_BOTH)): ?>
<div class="card">
  <h2>Notation <?= $noteGlobale !== null ? '— ' . $noteGlobale . '/5' : '' ?></h2>
  <?php foreach (Fournisseur::CRITERES_NOTE as $champ => $label): ?>
    <?php if (($fournisseur[$champ] ?? null) !== null && $fournisseur[$champ] !== ''): ?>
      <div class="info-row"><span class="label"><?= $label ?></span><span><?= (int) $fournisseur[$champ] ?>/5</span></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Pièces jointes</h2>
  <?php if (empty($piecesJointes)): ?>
    <div class="empty-state">Aucun document pour le moment.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Catégorie</th><th>Fichier</th><th>Ajouté par</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($piecesJointes as $p): ?>
        <tr>
          <td><?= View::e(FournisseurPieceJointe::CATEGORIES[$p['categorie']] ?? ucfirst($p['categorie'])) ?></td>
          <td><?= View::e($p['nom_original']) ?></td>
          <td><?= View::e($p['uploaded_by_nom'] ?? '—') ?></td>
          <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
          <td>
            <?php if (\App\Core\Storage::estPrevisualisable($p['type_mime'])): ?>
              <a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/pieces/<?= $p['id'] ?>/telecharger&apercu=1" target="_blank" class="btn btn-sm btn-secondary">Aperçu</a>
            <?php endif; ?>
            <a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/pieces/<?= $p['id'] ?>/telecharger" class="btn btn-sm btn-secondary">Télécharger</a>
            <form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/pieces/<?= $p['id'] ?>/supprimer" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <button type="submit" class="btn btn-sm btn-secondary">Supprimer</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/pieces" enctype="multipart/form-data" style="margin-top:14px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group" style="margin:0">
      <label style="font-size:12px">Catégorie</label>
      <select name="categorie">
        <?php foreach (FournisseurPieceJointe::CATEGORIES as $code => $label): ?>
          <option value="<?= $code ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label style="font-size:12px">Fichier</label>
      <input type="file" name="fichier" required>
    </div>
    <button type="submit" class="btn btn-sm">Ajouter</button>
  </form>
</div>

<div class="card">
  <h2>Historique des consultations</h2>
  <?php if (empty($historique)): ?>
    <div class="empty-state">Aucune consultation envoyée à ce fournisseur pour le moment.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Dossier</th><th>Statut</th><th>Offres reçues</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($historique as $h): ?>
        <tr onclick="window.location='/index.php?r=consultations/<?= $h['id'] ?>'" style="cursor:pointer">
          <td><?= View::e($h['dossier_reference']) ?></td>
          <td><?= View::e(ucfirst($h['statut'])) ?></td>
          <td><?= (int) $h['nb_offres'] ?></td>
          <td><?= date('d/m/Y', strtotime($h['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (!empty($fournisseur['notes'])): ?>
<div class="card">
  <h2>Notes</h2>
  <div><?= nl2br(View::e($fournisseur['notes'])) ?></div>
</div>
<?php endif; ?>
