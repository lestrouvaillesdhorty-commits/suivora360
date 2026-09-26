<?php use App\Core\View; use App\Models\Demande; ?>
<a href="/index.php?r=demandes/<?= $demande['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la demande</a>

<h1>Qualifier la demande <?= View::e($demande['reference']) ?></h1>
<div class="subtitle"><?= View::e($demande['objet']) ?></div>

<div class="alert" style="background:#eef0f4;color:#333">
  Choisissez la voie qui correspond à cette demande. La nature n'est jamais devinée automatiquement : c'est vous qui décidez.
</div>

<div class="detail-grid" style="grid-template-columns:1fr">

  <div class="card">
    <h2>1. Nouvelle demande</h2>
    <div class="subtitle" style="margin-bottom:12px">Un nouveau besoin, pour un client connu ou un prospect.</div>
    <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/qualifier/nouvelle">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

      <div class="form-group">
        <label>Client</label>
        <select name="client_id">
          <option value="">— Prospect / non enregistré (<?= View::e($demande['expediteur_nom'] ?: 'sans nom') ?>) —</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>"><?= View::e($c['nom']) ?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:12px;color:#888;margin-top:4px">Pas encore enregistré ? <a href="/index.php?r=clients/nouveau">Créer un client</a> puis revenez ici.</div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Activité</label>
          <input type="text" name="activite" value="<?= View::e($demande['activite']) ?>" placeholder="Ex : Sourcing et approvisionnement">
        </div>
        <div class="form-group">
          <label>Pays de destination</label>
          <input type="text" name="destination_pays" placeholder="Ex : France">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Responsable</label>
          <select name="responsable_id">
            <option value="">— Non assigné —</option>
            <?php foreach ($utilisateurs as $u): ?>
              <option value="<?= $u['id'] ?>" <?= (int) $demande['responsable_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priorité</label>
          <select name="priorite">
            <option value="normale" <?= $demande['priorite'] === 'normale' ? 'selected' : '' ?>>Normale</option>
            <option value="haute" <?= $demande['priorite'] === 'haute' ? 'selected' : '' ?>>Haute</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Échéance</label>
        <input type="date" name="echeance" value="<?= View::e($demande['echeance']) ?>">
      </div>

      <div class="form-group">
        <label>Notes de qualification</label>
        <textarea name="notes" rows="2"></textarea>
      </div>

      <button type="submit" class="btn">Qualifier comme nouvelle demande</button>
    </form>
  </div>

  <div class="card">
    <h2>2. Complément à une demande existante</h2>
    <div class="subtitle" style="margin-bottom:12px">Cette demande concerne un dossier ou une demande déjà en cours. Aucun nouveau dossier ne sera créé.</div>

    <form method="get" action="/index.php" style="display:flex;gap:10px;margin-bottom:16px">
      <input type="hidden" name="r" value="demandes/<?= $demande['id'] ?>/qualifier">
      <input type="text" name="q" placeholder="Référence, objet, contact..." value="<?= View::e($termeRecherche) ?>" style="max-width:320px">
      <button type="submit" class="btn btn-secondary">Rechercher</button>
    </form>

    <?php if ($termeRecherche !== ''): ?>
      <?php if (empty($resultats)): ?>
        <div class="empty-state">Aucun résultat pour « <?= View::e($termeRecherche) ?> ».</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Type</th><th>Référence</th><th>Libellé</th><th>Statut</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($resultats as $r): ?>
            <tr>
              <td><span class="badge badge-gray"><?= $r['type'] === 'dossier' ? 'Dossier' : 'Demande' ?></span></td>
              <td><?= View::e($r['reference']) ?></td>
              <td><?= View::e($r['libelle']) ?></td>
              <td><?= View::e($r['statut']) ?></td>
              <td>
                <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/qualifier/complement" onsubmit="return confirm('Confirmer le rattachement à ce <?= $r['type'] ?> ?');">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <input type="hidden" name="type" value="<?= $r['type'] ?>">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <input type="hidden" name="notes" value="Rattachée depuis la recherche : <?= View::e($termeRecherche) ?>">
                  <button type="submit" class="btn btn-sm">Rattacher à ceci</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>3. Reprise hors Suivora</h2>
    <div class="subtitle" style="margin-bottom:12px">Ce dossier a déjà été démarré ailleurs (avant l'utilisation de Suivora360). On enregistre l'historique sans jamais inventer les étapes déjà passées.</div>
    <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/qualifier/reprise">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

      <div class="form-row">
        <div class="form-group">
          <label>Date réelle de début</label>
          <input type="date" name="original_started_at">
        </div>
        <div class="form-group">
          <label>Date d'enregistrement dans Suivora</label>
          <input type="date" name="registered_in_suivora_at" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Origine externe</label>
          <input type="text" name="external_source" placeholder="Ex : Excel partagé, autre outil...">
        </div>
        <div class="form-group">
          <label>Référence externe</label>
          <input type="text" name="external_reference" placeholder="Ex : numéro de suivi précédent">
        </div>
      </div>

      <div class="form-group">
        <label>Étape actuelle</label>
        <select name="takeover_stage">
          <?php foreach (Demande::TAKEOVER_STAGES as $val => $label): ?>
            <option value="<?= $val ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Responsable</label>
          <select name="responsable_id">
            <option value="">— Non assigné —</option>
            <?php foreach ($utilisateurs as $u): ?>
              <option value="<?= $u['id'] ?>"><?= View::e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priorité</label>
          <select name="priorite">
            <option value="normale">Normale</option>
            <option value="haute">Haute</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" rows="2"></textarea>
      </div>

      <button type="submit" class="btn">Enregistrer la reprise</button>
    </form>
  </div>

</div>
