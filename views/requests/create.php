<?php
use App\Core\Icon;
use App\Core\Storage;
use App\Core\View;
use App\Models\Demande;

/**
 * [réécrit 04/10, refonte du module Demandes] Formulaire unique partagé par
 * la création ET la modification (écran 2 du cahier des charges) — $demande
 * est null en création. Sections A-E, 2 colonnes sur ordinateur (2/3 + 1/3,
 * .detail-grid existant), onglets Formulaire/Documents sur mobile (voir
 * .form-tabs dans app.css).
 */
$estModification = $demande !== null;
$activiteListe = Demande::ACTIVITES;
$actionUrl = $estModification ? "/index.php?r=demandes/{$demande['id']}/modifier" : '/index.php?r=demandes';
$retour = '/index.php?' . ($retourListeQuery ?: 'r=demandes');
?>
<a href="<?= $retour ?>" style="font-size:13px;color:#666">&larr; Retour aux demandes</a>

<h1 style="margin-top:8px"><?= $estModification ? 'Modifier la demande ' . View::e($demande['reference']) : 'Nouvelle demande' ?></h1>
<div class="subtitle"><?= $estModification ? 'Corriger les informations de la demande' : 'Enregistrer une nouvelle demande client' ?></div>

<?php if ($estModification && $dossierExistant): ?>
<div class="alert" style="background:#eef0f4;color:#333;margin-top:12px">
  Un dossier (<?= View::e($dossierExistant['reference']) ?>) a déjà été créé à partir de cette demande — les
  champs modifiés ici ne seront pas reportés automatiquement sur le dossier.
</div>
<?php endif; ?>

<div class="form-tabs" id="formTabs">
  <button type="button" class="ft-btn active" data-target="formulaire">Formulaire</button>
  <button type="button" class="ft-btn" data-target="documents">Documents &amp; affectation</button>
</div>

<form method="post" action="<?= $actionUrl ?>" enctype="multipart/form-data" id="demandeForm"<?= $estModification ? '' : ' data-brouillon="demande" data-brouillon-ajout="btnAjouterLigne"' ?>>
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <div class="detail-grid form-tabs-wrap" id="formTabsWrap">
  <div class="ft-panel" data-panel="formulaire">

    <div class="card">
      <h2>Besoin</h2>
      <div class="form-group">
        <label>Activité *</label>
        <select name="activite" id="activiteSelect" required>
          <option value="">— Choisir —</option>
          <?php foreach ($activiteListe as $a): ?>
            <option <?= ($demande['activite'] ?? '') === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$estModification): ?><div style="font-size:12px;color:#888;margin-top:4px">Le formulaire s'adapte à l'activité : articles pour un achat, détails du transport, détails de la prestation.</div><?php endif; ?>
      </div>
      <div class="form-group">
        <label>Objet *</label>
        <input type="text" name="objet" required value="<?= View::e($demande['objet'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Message original</label>
        <textarea name="message" rows="3"><?= View::e($demande['message'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Canal</label>
          <select name="canal">
            <?php foreach (['Formulaire', 'WhatsApp', 'Email', 'Téléphone'] as $c): ?>
              <option <?= ($demande['canal'] ?? 'Formulaire') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Reçue le</label>
          <input type="date" name="recue_le" value="<?= View::e($demande['recue_le'] ?? date('Y-m-d')) ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Date souhaitée par le client</label>
        <input type="date" name="date_souhaitee_client" value="<?= View::e($demande['date_souhaitee_client'] ?? '') ?>">
        <div style="font-size:12px;color:#888;margin-top:4px">Distincte de l'échéance interne de traitement (section Affectation) — c'est le délai exprimé par le client lui-même.</div>
      </div>
    </div>

    <div class="card">
      <h2>Client et contact</h2>
      <div class="form-group">
        <label for="clientFiltre">Rechercher un client existant</label>
        <input type="text" id="clientFiltre" placeholder="Tapez pour filtrer la liste ci-dessous...">
      </div>
      <div class="form-group">
        <label for="clientSelect">Client</label>
        <select name="client_id" id="clientSelect">
          <option value="">— Prospect / contact non enregistré —</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>"
              data-filiale="<?= $c['filiale_id'] ?>"
              data-email="<?= View::e($c['email']) ?>"
              data-telephone="<?= View::e($c['telephone']) ?>"
              <?= (string) ($demande['client_id'] ?? ($prefill['client_id'] ?? '')) === (string) $c['id'] ? 'selected' : '' ?>>
              <?= View::e($c['nom']) ?><?= View::e($c['filiale_nom'] ?? '') !== '' ? ' — ' . View::e($c['filiale_nom']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:12px;color:#888;margin-top:4px">
          Pas encore enregistré ? <button type="button" class="btn-link" id="btnCreationRapide">Créer rapidement un client</button>
          ou <a href="/index.php?r=clients/nouveau" target="_blank">ouvrir la fiche complète</a>.
        </div>
      </div>

      <div id="creationRapideBox" class="card" style="display:none;background:#fafbfc;margin-top:10px">
        <div class="form-row">
          <div class="form-group"><label>Nom du client *</label><input type="text" id="crNom"></div>
          <div class="form-group"><label>Type</label>
            <select id="crType">
              <option value="">—</option>
              <?php foreach (\App\Models\Client::TYPES as $code => $label): ?>
                <option value="<?= $code ?>"><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>E-mail</label><input type="email" id="crEmail"></div>
          <div class="form-group"><label>Téléphone</label><input type="tel" id="crTelephone"></div>
        </div>
        <div id="crMessage" style="font-size:13px;margin-bottom:8px"></div>
        <button type="button" class="btn btn-sm" id="crValider">Créer ce client</button>
        <button type="button" class="btn btn-sm btn-secondary" id="crAnnuler">Annuler</button>
      </div>

      <div style="margin-top:14px;padding-top:14px;border-top:1px solid #eef0f4">
        <div style="font-size:12px;color:#888;margin-bottom:6px">Contact (si prospect / non enregistré) :</div>
        <div class="form-row">
          <div class="form-group"><label>Nom</label><input type="text" name="expediteur_nom" id="expNom" value="<?= View::e($demande['expediteur_nom'] ?? ($prefill['expediteur_nom'] ?? '')) ?>"></div>
          <div class="form-group"><label>Entreprise</label><input type="text" name="expediteur_entreprise" value="<?= View::e($demande['expediteur_entreprise'] ?? ($prefill['expediteur_entreprise'] ?? '')) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>E-mail</label><input type="email" name="expediteur_email" id="expEmail" value="<?= View::e($demande['expediteur_email'] ?? ($prefill['expediteur_email'] ?? '')) ?>"></div>
          <div class="form-group"><label>Téléphone</label><input type="tel" name="expediteur_telephone" id="expTelephone" value="<?= View::e($demande['expediteur_telephone'] ?? ($prefill['expediteur_telephone'] ?? '')) ?>"></div>
        </div>
      </div>
    </div>


    <?php if (!$estModification): ?>
    <div class="card" data-activite-bloc="transport" style="display:none">
      <h2>Détails du transport</h2>
      <div class="form-row">
        <div class="form-group"><label>Lieu d'enlèvement</label><input type="text" name="lieu_enlevement" placeholder="Adresse ou ville d'enlèvement"></div>
        <div class="form-group"><label>Destination</label><input type="text" name="transport_destination" placeholder="Ville / pays de livraison"></div>
      </div>
      <div class="form-group"><label>Marchandises à transporter</label><input type="text" name="marchandises" placeholder="Nature des marchandises"></div>
      <div class="form-row">
        <div class="form-group"><label>Poids estimé (kg)</label><input type="number" step="0.01" name="poids_estime"></div>
        <div class="form-group"><label>Volume estimé (m³)</label><input type="number" step="0.01" name="volume_estime"></div>
        <div class="form-group"><label>Mode souhaité</label>
          <select name="transport_mode"><option value="">À préciser</option><?php foreach (['Maritime', 'Aérien', 'Routier', 'Ferroviaire', 'Multimodal'] as $m): ?><option><?= $m ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div style="font-size:12px;color:#888">Toute donnée inconnue reste « À préciser » — ne rien inventer.</div>
    </div>

    <div class="card" data-activite-bloc="prestation" style="display:none">
      <h2>Détails de la prestation</h2>
      <div class="form-group"><label>Nature du service demandé</label><input type="text" name="nature_service" placeholder="Ex : entretien de climatisation, traitement phytosanitaire…"></div>
      <div class="form-row">
        <div class="form-group"><label>Type de prestation</label>
          <select name="type_prestation_souhaite"><option value="">À préciser</option><?php foreach (\App\Models\Dossier::TYPES_PRESTATION_LABELS as $lib): ?><option><?= View::e($lib) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Site d'intervention</label><input type="text" name="site_intervention" placeholder="Adresse ou site concerné"></div>
      </div>
      <label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="visite_necessaire" value="1" style="width:auto"> Visite préalable nécessaire</label>
    </div>
    <?php endif; ?>

    <div class="card" data-activite-bloc="articles">
      <h2>Articles / besoins</h2>
      <?php if (!$estModification): ?>
        <div style="font-size:12px;color:#888;margin-bottom:10px">Facultatif à ce stade — une demande de service encore peu détaillée peut être enregistrée sans aucune ligne.</div>
        <table id="articlesTable">
          <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Conditionnement / précision</th><th>Référence</th><th>Marque</th><th></th></tr></thead>
          <tbody id="articlesBody"></tbody>
        </table>
        <button type="button" class="btn btn-sm btn-secondary" id="btnAjouterLigne" style="margin-top:8px">+ Ajouter une ligne</button>
        <template id="articleRowTemplate">
          <tr>
            <td><input type="text" name="art_designation[]"></td>
            <td><input type="number" step="0.01" name="art_quantite[]" style="width:80px"></td>
            <td>
              <select name="art_unite[]">
                <option value=""></option>
                <?php foreach (['Pièce', 'Carton', 'Palette', 'Sac', 'Kg', 'Tonne', 'Litre', 'm³', 'Lot', 'Conteneur'] as $u): ?>
                  <option><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="art_conditionnement[]" placeholder="ex. sacs de 50 kg"></td>
            <td><input type="text" name="art_reference[]" style="width:100px"></td>
            <td><input type="text" name="art_marque[]" style="width:100px"></td>
            <td><button type="button" class="btn btn-sm btn-secondary btn-retirer-ligne">&times;</button></td>
          </tr>
        </template>
      <?php else: ?>
        <div style="font-size:12px;color:#888;margin-bottom:10px">La modification des lignes se fait directement depuis la fiche de la demande, pour être enregistrée immédiatement.</div>
        <a href="/index.php?r=demandes/<?= $demande['id'] ?>" class="btn btn-sm btn-secondary">Gérer les articles sur la fiche →</a>
      <?php endif; ?>
    </div>

  </div>

  <div class="ft-panel" data-panel="documents" style="display:none">

    <div class="card">
      <h2>Affectation</h2>
      <?php if (count($filiales) > 1 && !$estModification): ?>
      <div class="form-group">
        <label>Filiale *</label>
        <select name="filiale_id" required>
          <?php foreach ($filiales as $f): ?>
            <option value="<?= $f['id'] ?>" <?= (string) ($prefill['filiale_id'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($estModification): ?>
        <input type="hidden" name="filiale_id" value="<?= $demande['filiale_id'] ?>">
        <div class="form-group"><label>Filiale</label><div><?= View::e($demande['filiale_nom'] ?? \App\Models\Filiale::find((int) $demande['filiale_id'])['nom'] ?? '') ?> <span style="font-size:11px;color:#888">(non modifiable)</span></div></div>
      <?php elseif (count($filiales) === 1): ?>
        <input type="hidden" name="filiale_id" value="<?= $filiales[0]['id'] ?>">
      <?php else: ?>
        <div class="alert alert-erreur">Aucune filiale ne vous est assignée. Contactez votre administrateur.</div>
      <?php endif; ?>

      <div class="form-row">
        <div class="form-group">
          <label>Responsable</label>
          <select name="responsable_id">
            <option value="">— Non assigné —</option>
            <?php foreach ($utilisateurs as $u): ?>
              <option value="<?= $u['id'] ?>" <?= (string) ($demande['responsable_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Priorité</label>
          <select name="priorite">
            <?php foreach (Demande::PRIORITES as $code => $label): ?>
              <option value="<?= $code ?>" <?= ($demande['priorite'] ?? 'normale') === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Échéance interne</label>
        <input type="date" name="echeance" value="<?= View::e($demande['echeance'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Notes internes</label>
        <textarea name="notes_internes" rows="2"><?= View::e($demande['notes_internes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card">
      <h2>Documents</h2>
      <?php if ($estModification && !empty($piecesJointes)): ?>
        <table>
          <thead><tr><th>Fichier</th><th>Taille</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($piecesJointes as $p): ?>
            <tr>
              <td><?= View::e($p['nom_original']) ?></td>
              <td><?= number_format($p['taille'] / 1024, 0) ?> Ko</td>
              <td>
                <?php if (Storage::estPrevisualisable($p['type_mime'])): ?>
                  <a href="/index.php?r=demandes/<?= $demande['id'] ?>/pieces/<?= $p['id'] ?>/telecharger&apercu=1" target="_blank" class="btn btn-sm btn-secondary">Aperçu</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div style="font-size:12px;color:#888;margin:8px 0">Pour retirer une pièce jointe existante, utilisez la fiche de la demande.</div>
      <?php endif; ?>
      <div class="form-group">
        <label>Ajouter des fichiers (facultatif)</label>
        <input type="file" name="fichiers[]" multiple>
        <div style="font-size:12px;color:#888;margin-top:4px">PDF, images, Word, Excel, texte ou e-mail — 10 Mo max par fichier.</div>
      </div>
    </div>

  </div>
  </div>

  <div style="margin-top:24px">
    <button type="submit" class="btn"><?= $estModification ? 'Enregistrer les modifications' : 'Enregistrer à qualifier' ?></button>
    <a href="<?= $retour ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>

<script>
(function () {
  // Onglets Formulaire / Documents — purement visuels (desktop : les deux
  // colonnes sont toujours visibles côte à côte, voir .form-tabs-wrap dans
  // app.css) ; sur mobile, un seul panneau à la fois, aucune saisie perdue
  // puisque rien n'est retiré du DOM.
  var tabs = document.getElementById('formTabs');
  var wrap = document.getElementById('formTabsWrap');
  if (tabs && wrap) {
    function appliquerAffichagePanneaux() {
      var actif = tabs.querySelector('.ft-btn.active');
      var target = actif ? actif.getAttribute('data-target') : 'formulaire';
      wrap.querySelectorAll('.ft-panel').forEach(function (p) {
        p.style.display = (p.getAttribute('data-panel') === target || window.innerWidth > 900) ? '' : 'none';
      });
    }
    tabs.querySelectorAll('.ft-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabs.querySelectorAll('.ft-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        appliquerAffichagePanneaux();
      });
    });
    // [corrigé 04/10] Sans cet appel au chargement, le panneau "Documents &
    // affectation" (qui porte display:none en dur dans le HTML pour l'état
    // mobile par défaut) restait invisible sur ordinateur jusqu'à ce que
    // l'utilisateur clique un onglet lui-même masqué en desktop — rendant
    // Activité/Filiale/Responsable/Échéance/Documents inaccessibles et le
    // formulaire impossible à soumettre (champ requis cachée). On applique
    // donc l'affichage dès le chargement, et on le réévalue si la fenêtre
    // change de taille (bascule mobile/desktop en cours de saisie).
    appliquerAffichagePanneaux();
    window.addEventListener('resize', appliquerAffichagePanneaux);
  }

  // Filtre texte au-dessus du select client (aucune dépendance réseau).
  var filtre = document.getElementById('clientFiltre');
  var select = document.getElementById('clientSelect');
  if (filtre && select) {
    var optionsOriginales = Array.prototype.slice.call(select.options);
    filtre.addEventListener('input', function () {
      var terme = filtre.value.trim().toLowerCase();
      select.innerHTML = '';
      optionsOriginales.forEach(function (opt) {
        if (opt.value === '' || opt.text.toLowerCase().indexOf(terme) !== -1) {
          select.appendChild(opt);
        }
      });
    });
    // Sélection d'un client connu : pré-remplit le contact si les champs
    // sont encore vides (ne vient jamais écraser une saisie déjà faite).
    select.addEventListener('change', function () {
      var opt = select.options[select.selectedIndex];
      var expEmail = document.getElementById('expEmail');
      var expTel = document.getElementById('expTelephone');
      if (opt && opt.value) {
        if (expEmail && !expEmail.value) { expEmail.value = opt.getAttribute('data-email') || ''; }
        if (expTel && !expTel.value) { expTel.value = opt.getAttribute('data-telephone') || ''; }
      }
    });
  }

  // Création rapide de client (section 2.B) — AJAX pour ne jamais perdre la
  // saisie déjà faite sur le reste du formulaire de demande.
  var btnOuvrir = document.getElementById('btnCreationRapide');
  var box = document.getElementById('creationRapideBox');
  var btnAnnuler = document.getElementById('crAnnuler');
  var btnValider = document.getElementById('crValider');
  if (btnOuvrir && box) {
    btnOuvrir.addEventListener('click', function () { box.style.display = 'block'; });
    btnAnnuler.addEventListener('click', function () { box.style.display = 'none'; document.getElementById('crMessage').textContent = ''; });
  }
  function validerCreationClient(forcer) {
      forcer = forcer === true;
      var nom = document.getElementById('crNom').value.trim();
      var msg = document.getElementById('crMessage');
      if (!nom) { msg.textContent = 'Le nom du client est obligatoire.'; msg.style.color = '#b42318'; return; }
      var filialeInput = document.querySelector('[name="filiale_id"]');
      var fd = new FormData();
      fd.append('csrf_token', <?= json_encode($csrfToken) ?>);
      fd.append('filiale_id', filialeInput ? filialeInput.value : '');
      fd.append('nom', nom);
      fd.append('type', document.getElementById('crType').value);
      fd.append('email', document.getElementById('crEmail').value.trim());
      fd.append('telephone', document.getElementById('crTelephone').value.trim());
      if (forcer) { fd.append('force', '1'); }
      msg.textContent = 'Création en cours...';
      msg.style.color = '#666';
      fetch('/index.php?r=clients/creation-rapide', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.erreur) { msg.textContent = data.erreur; msg.style.color = '#b42318'; return; }
          if (data.doublon) {
            msg.innerHTML = 'Un client "' + data.existant.nom + '" existe déjà. ';
            var btnUtiliser = document.createElement('button');
            btnUtiliser.type = 'button';
            btnUtiliser.className = 'btn btn-sm btn-secondary';
            btnUtiliser.textContent = 'Utiliser celui-ci';
            btnUtiliser.onclick = function () {
              var opt = document.createElement('option');
              opt.value = data.existant.id; opt.textContent = data.existant.nom;
              select.appendChild(opt); select.value = data.existant.id;
              select.dispatchEvent(new Event('change'));
              box.style.display = 'none';
            };
            var btnForcer = document.createElement('button');
            btnForcer.type = 'button';
            btnForcer.className = 'btn btn-sm';
            btnForcer.style.marginLeft = '6px';
            btnForcer.textContent = 'Créer malgré tout';
            btnForcer.onclick = function () { validerCreationClient(true); };
            msg.appendChild(btnUtiliser);
            msg.appendChild(btnForcer);
            return;
          }
          var opt = document.createElement('option');
          opt.value = data.id; opt.textContent = data.nom;
          opt.setAttribute('data-email', data.email || '');
          opt.setAttribute('data-telephone', data.telephone || '');
          select.appendChild(opt);
          select.value = data.id;
          select.dispatchEvent(new Event('change'));
          box.style.display = 'none';
          msg.textContent = '';
        })
        .catch(function () { msg.textContent = 'Erreur réseau — réessayez.'; msg.style.color = '#b42318'; });
  }
  if (btnValider) {
    btnValider.addEventListener('click', function () { validerCreationClient(false); });
  }

  // Lignes d'articles ajoutées à la création (voir le template ci-dessus).
  var btnAjouterLigne = document.getElementById('btnAjouterLigne');
  var articlesBody = document.getElementById('articlesBody');
  var template = document.getElementById('articleRowTemplate');
  if (btnAjouterLigne && articlesBody && template) {
    function ajouterLigne() {
      var clone = template.content.cloneNode(true);
      clone.querySelector('.btn-retirer-ligne').addEventListener('click', function (e) {
        e.target.closest('tr').remove();
      });
      articlesBody.appendChild(clone);
    }
    btnAjouterLigne.addEventListener('click', ajouterLigne);
  }

  // [09/10] Le formulaire s'adapte à l'activité choisie (transport, prestation, sinon articles).
  var actSel = document.getElementById('activiteSelect');
  if (actSel) {
    var GROUPES = {
      'Transport et logistique': 'transport', 'Dédouanement et transit': 'transport',
      'Représentation commerciale': 'prestation', 'Prestation de service': 'prestation'
    };
    function adapterActivite() {
      var groupe = GROUPES[actSel.value] || 'articles';
      document.querySelectorAll('[data-activite-bloc]').forEach(function (bloc) {
        var visible = bloc.getAttribute('data-activite-bloc') === groupe;
        bloc.style.display = visible ? '' : 'none';
        bloc.querySelectorAll('input, select, textarea, button').forEach(function (el) { el.disabled = !visible; });
      });
    }
    actSel.addEventListener('change', adapterActivite);
    adapterActivite();
  }
})();
</script>
