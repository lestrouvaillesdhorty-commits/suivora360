<?php use App\Core\View; ?>
<h1>Sous-activités et catégories</h1>
<div class="subtitle">Ces listes alimentent les formulaires (demande, fournisseur). Désactiver une valeur la retire des listes sans toucher à l'existant. <a href="/index.php?r=parametres">← Paramètres</a></div>

<?php if (count($filiales) > 1): ?>
<form method="get" action="/index.php" style="margin:14px 0">
  <input type="hidden" name="r" value="parametres/listes">
  <div class="form-group" style="max-width:280px">
    <label>Filiale</label>
    <select name="filiale_id" onchange="this.form.submit()">
      <?php foreach ($filiales as $f): ?><option value="<?= $f['id'] ?>" <?= $filialeId === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?>
    </select>
  </div>
</form>
<?php endif; ?>

<?php if (!$disponible): ?>
  <div class="card"><div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v37.php</strong> (voir les instructions de mise à jour).</div></div>
<?php elseif (!$filialeId): ?>
  <div class="card"><div class="empty-state">Aucune filiale disponible.</div></div>
<?php else: ?>

<?php
$ligne = function (string $prefixe, array $r, string $libelle, int $usage) use ($filialeId, $csrfToken) { ?>
  <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-top:1px solid #eee;flex-wrap:wrap">
    <form method="post" action="/index.php?r=parametres/listes" style="display:flex;gap:8px;align-items:center;flex:1;min-width:240px;margin:0">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
      <input type="hidden" name="action" value="<?= $prefixe ?>_renommer">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <input type="text" name="nom" value="<?= View::e($libelle) ?>" maxlength="120" style="flex:1;<?= $r['actif'] ? '' : 'opacity:.55' ?>" aria-label="Nom">
      <button class="btn btn-sm btn-secondary" type="submit">Renommer</button>
    </form>
    <?php $msg = ($r['actif'] && $usage > 0) ? 'Attention : ' . $usage . ' demande' . ($usage > 1 ? 's utilisent' : ' utilise') . ' « ' . $libelle . ' ». Désactiver cette valeur la retire des formulaires et des filtres, mais ' . ($usage > 1 ? 'ces demandes gardent cette valeur et restent consultables' : 'cette demande garde cette valeur et reste consultable') . '. Vous pourrez la réactiver à tout moment. Continuer ?' : ''; ?>
    <form method="post" action="/index.php?r=parametres/listes" style="margin:0"<?= $msg !== '' ? ' onsubmit="return confirm(' . View::e(json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) . ')"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
      <input type="hidden" name="action" value="<?= $prefixe ?>_basculer">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="btn btn-sm btn-secondary" type="submit"><?= $r['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
    </form>
  </div>
<?php };
?>

<?php foreach ($sousActivites as $activite => $rows): ?>
<div class="card">
  <h2><?= View::e($activite) ?></h2>
  <?php if (!$rows): ?><div style="color:#888;font-size:14px;padding:6px 0">Aucune sous-activité.</div><?php endif; ?>
  <?php foreach ($rows as $r): $ligne('sa', $r, $r['nom'], (int) ($usages[$activite . '|' . $r['nom']] ?? 0)); endforeach; ?>
  <form method="post" action="/index.php?r=parametres/listes" style="display:flex;gap:8px;margin:12px 0 0;flex-wrap:wrap">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
    <input type="hidden" name="action" value="sa_ajouter">
    <input type="hidden" name="activite" value="<?= View::e($activite) ?>">
    <input type="text" name="nom" maxlength="120" placeholder="Nouvelle sous-activité" style="flex:1;min-width:200px" aria-label="Nouvelle sous-activité">
    <button class="btn btn-sm" type="submit">Ajouter</button>
  </form>
</div>
<?php endforeach; ?>

<div class="card">
  <h2>Catégories de fournisseur ajoutées</h2>
  <div style="font-size:13px;color:#666;margin-bottom:6px">Les six catégories d'origine (Fabricant, Distributeur, Prestataire, Transporteur, Transitaire, Autre) existent toujours. Une catégorie ajoutée n'impose aucun critère particulier.</div>
  <?php if (!$categories): ?><div style="color:#888;font-size:14px;padding:6px 0">Aucune catégorie ajoutée pour l'instant. On peut en créer une directement depuis la fiche d'un fournisseur.</div><?php endif; ?>
  <?php foreach ($categories as $r): $ligne('cat', $r, $r['libelle'], 0); endforeach; ?>
  <form method="post" action="/index.php?r=parametres/listes" style="display:flex;gap:8px;margin:12px 0 0;flex-wrap:wrap">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
    <input type="hidden" name="action" value="cat_ajouter">
    <input type="text" name="nom" maxlength="120" placeholder="Nouvelle catégorie (ex. Emballagiste)" style="flex:1;min-width:200px" aria-label="Nouvelle catégorie">
    <button class="btn btn-sm" type="submit">Ajouter</button>
  </form>
</div>

<div class="card">
  <h2>Motifs de perte</h2>
  <div style="font-size:13px;color:#666;margin-bottom:6px">Proposés quand un devis est refusé et quand un dossier est clôturé sans commande. Renommer change le libellé partout, y compris dans l'historique. Un motif déjà utilisé ne peut pas être supprimé : désactivez-le. « Autre (préciser) » reste toujours disponible.</div>
  <?php if (!$motifsDispo): ?>
    <div style="color:#888;font-size:14px;padding:6px 0">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v51.php</strong>.</div>
  <?php else: ?>
  <?php foreach ($motifs as $r): $estAutre = $r['code'] === 'autre'; $ajoute = str_starts_with((string) $r['code'], 'm'); ?>
  <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-top:1px solid #eee;flex-wrap:wrap">
    <form method="post" action="/index.php?r=parametres/listes" style="display:flex;gap:8px;align-items:center;flex:1;min-width:240px;margin:0">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
      <input type="hidden" name="action" value="mp_renommer">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <input type="text" name="nom" value="<?= View::e($r['libelle']) ?>" maxlength="120" style="flex:1;<?= $r['actif'] ? '' : 'opacity:.55' ?>" aria-label="Motif">
      <button class="btn btn-sm btn-secondary" type="submit">Renommer</button>
    </form>
    <?php if (!$estAutre): ?>
    <form method="post" action="/index.php?r=parametres/listes" style="margin:0">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
      <input type="hidden" name="action" value="mp_basculer">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="btn btn-sm btn-secondary" type="submit"><?= $r['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
    </form>
    <?php endif; ?>
    <?php if ($ajoute && (int) $r['usages'] === 0): ?>
    <form method="post" action="/index.php?r=parametres/listes" style="margin:0" onsubmit="return confirm('Supprimer ce motif ?')">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
      <input type="hidden" name="action" value="mp_supprimer">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="btn btn-sm btn-secondary" type="submit">Supprimer</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <form method="post" action="/index.php?r=parametres/listes" style="display:flex;gap:8px;margin:12px 0 0;flex-wrap:wrap">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
    <input type="hidden" name="action" value="mp_ajouter">
    <input type="text" name="nom" maxlength="120" placeholder="Nouveau motif (ex. Pas de fournisseur trouvé)" style="flex:1;min-width:200px" aria-label="Nouveau motif">
    <button class="btn btn-sm" type="submit">Ajouter</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
