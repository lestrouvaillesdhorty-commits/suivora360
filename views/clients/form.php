<?php
use App\Core\Telephone;
use App\Core\View;
use App\Models\Client;
use App\Models\Demande;

/**
 * Formulaire unique « Nouveau client » / « Modifier le client » (07/10).
 * Variables : $client (null en création), $old (saisie POST conservée en cas d'erreur), $erreurs, $doublons,
 * $filiales, $utilisateurs, $defauts, $schemaPret, $retour.
 */
$estModif = $client !== null;
$actionUrl = $estModif ? '/index.php?r=clients/' . (int) $client['id'] . '/modifier' : '/index.php?r=clients';
$aSaisie = !empty($old);
$val = function (string $k, string $def = '') use ($old, $client, $aSaisie) {
    if ($aSaisie && array_key_exists($k, $old)) { return (string) $old[$k]; }
    return (string) ($client[$k] ?? $def);
};
$paysDefaut = $estModif ? '' : ($defauts['pays'] ?? '');
$pays = $val('pays', $paysDefaut);
if ($aSaisie && array_key_exists('tel_indicatif', $old)) {
    $tphIndicatif = (string) $old['tel_indicatif'];
    $tphNumero = (string) ($old['tel_numero'] ?? '');
} elseif ($estModif) {
    [$tphIndicatif, $tphNumero] = Telephone::decouper($client['telephone'] ?? '');
} else {
    $tphIndicatif = Telephone::indicatifPourPays($pays);
    $tphNumero = '';
}
$tphId = 'tel';
$statut = $aSaisie ? ($old['statut'] ?? 'actif') : ($estModif ? ((int) $client['is_active'] === 1 ? 'actif' : 'inactif') : 'actif');
$relation = $val('relation', 'client');
$devise = $val('devise_preferee', $estModif ? '' : ($defauts['devise'] ?? ''));
$erreur = fn(string $k) => $erreurs[$k] ?? null;
$ouvrirCommercial = $estModif && ($client['secteur'] || $client['adresse_livraison'] || $client['incoterm_habituel'] || $client['mode_transport_habituel'] || $client['conditions_paiement']);
?>
<a href="<?= View::e($estModif ? '/index.php?r=clients/' . (int) $client['id'] : $retour) ?>" style="font-size:13px;color:#666">&larr; <?= $estModif ? 'Retour à la fiche' : 'Retour aux clients' ?></a>
<h1 style="margin-top:8px"><?= $estModif ? 'Modifier le client' : 'Nouveau client' ?></h1>
<div class="subtitle"><?= $estModif ? View::e($client['nom']) . ' — ' . View::e($client['code'] ?? '') : 'Enregistrer un client ou un prospect' ?></div>

<?php if (!empty($erreurs['_general'])): ?><div class="alert alert-erreur"><?= View::e($erreurs['_general']) ?></div><?php endif; ?>
<?php if (!empty($erreurs) && count(array_diff_key($erreurs, ['_general' => 1])) > 0): ?>
  <div class="alert alert-erreur">Merci de corriger les champs signalés — votre saisie est conservée.</div>
<?php endif; ?>

<?php if (!empty($doublons)): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e">
    <strong>Un client similaire existe peut-être déjà.</strong>
    <ul style="margin:6px 0 10px 18px">
      <?php foreach ($doublons as $d): ?>
        <li><a href="/index.php?r=clients/<?= (int) $d['id'] ?>" target="_blank"><?= View::e($d['nom']) ?></a> (<?= View::e($d['code'] ?? '') ?>)<?= $d['email'] ? ' — ' . View::e($d['email']) : '' ?><?= $d['telephone'] ? ' — ' . View::e($d['telephone']) : '' ?></li>
      <?php endforeach; ?>
    </ul>
    Aucune fusion automatique : vérifiez, puis modifiez la saisie ou confirmez.
    <div style="margin-top:10px"><button type="submit" form="clientForm" name="confirmer_doublon" value="1" class="btn btn-sm"><?= $estModif ? 'Enregistrer malgré tout' : 'Créer quand même' ?></button></div>
  </div>
<?php endif; ?>

<form method="post" action="<?= View::e($actionUrl) ?>" id="clientForm" novalidate<?= $estModif ? '' : ' data-brouillon="client"' ?>>
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <div class="detail-grid">
    <div>
      <div class="card">
        <h2>Identification</h2>
        <div class="form-group">
          <label for="nom">Nom / raison sociale *</label>
          <input type="text" id="nom" name="nom" required value="<?= View::e($val('nom')) ?>" <?= $erreur('nom') ? 'style="border-color:#b42318"' : '' ?>>
          <?php if ($erreur('nom')): ?><div style="color:#b42318;font-size:12px;margin-top:4px"><?= View::e($erreur('nom')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type">
              <option value="">—</option>
              <?php foreach (Client::TYPES as $code => $lib): ?><option value="<?= $code ?>" <?= $val('type') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Référence</label>
            <input type="text" value="<?= $estModif ? View::e($client['code'] ?? '') : 'Générée à l’enregistrement' ?>" disabled>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="pays">Pays</label>
            <input type="text" id="pays" name="pays" list="listePays" value="<?= View::e($pays) ?>" autocomplete="off">
            <datalist id="listePays"><?php foreach (Telephone::pays() as $p): ?><option value="<?= View::e($p) ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="form-group"><label for="ville">Ville</label><input type="text" id="ville" name="ville" value="<?= View::e($val('ville')) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="adresse">Adresse</label><input type="text" id="adresse" name="adresse" value="<?= View::e($val('adresse')) ?>"></div>
          <div class="form-group"><label for="code_postal">Code postal</label><input type="text" id="code_postal" name="code_postal" value="<?= View::e($val('code_postal')) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="siret" id="labelSiret">Numéro d’immatriculation (facultatif)</label><input type="text" id="siret" name="siret" value="<?= View::e($val('siret')) ?>"></div>
          <div class="form-group"><label for="tva" id="labelTva">Identifiant fiscal / TVA (facultatif)</label><input type="text" id="tva" name="tva" value="<?= View::e($val('tva')) ?>"></div>
        </div>
      </div>

      <div class="card">
        <h2>Contact principal</h2>
        <div class="form-row">
          <div class="form-group"><label for="contact_prenom">Prénom</label><input type="text" id="contact_prenom" name="contact_prenom" value="<?= View::e($val('contact_prenom')) ?>"></div>
          <div class="form-group"><label for="contact_nom">Nom</label><input type="text" id="contact_nom" name="contact_nom" value="<?= View::e($val('contact_nom')) ?>"></div>
        </div>
        <div class="form-group"><label for="fonction_contact">Fonction</label><input type="text" id="fonction_contact" name="fonction_contact" value="<?= View::e($val('fonction_contact')) ?>"></div>
        <div class="form-row">
          <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= View::e($val('email')) ?>" <?= $erreur('email') ? 'style="border-color:#b42318"' : '' ?>>
            <?php if ($erreur('email')): ?><div style="color:#b42318;font-size:12px;margin-top:4px"><?= View::e($erreur('email')) ?></div><?php endif; ?>
          </div>
          <div class="form-group"><label>Téléphone</label><?php include __DIR__ . '/_telephone.php'; ?></div>
        </div>
        <div style="font-size:12px;color:#888">D’autres contacts (comptabilité, logistique…) peuvent être ajoutés depuis la fiche, onglet « Contacts et adresses ».</div>
      </div>

      <details class="card" <?= ($ouvrirCommercial || $aSaisie) ? 'open' : '' ?>>
        <summary style="cursor:pointer;font-weight:600">Informations commerciales (facultatif)</summary>
        <div style="margin-top:12px">
          <div class="form-row">
            <div class="form-group"><label for="secteur">Secteur d’activité</label><input type="text" id="secteur" name="secteur" value="<?= View::e($val('secteur')) ?>"></div>
            <div class="form-group"><label for="adresse_livraison">Adresse de livraison habituelle</label><input type="text" id="adresse_livraison" name="adresse_livraison" value="<?= View::e($val('adresse_livraison')) ?>"></div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="incoterm_habituel">Incoterm habituel</label>
              <select id="incoterm_habituel" name="incoterm_habituel"><option value="">—</option>
                <?php foreach (Demande::INCOTERMS as $code => $lib): ?><option value="<?= View::e($code) ?>" <?= $val('incoterm_habituel') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="mode_transport_habituel">Transport habituel</label>
              <select id="mode_transport_habituel" name="mode_transport_habituel"><option value="">—</option>
                <?php foreach (Client::MODES_TRANSPORT as $code => $lib): ?><option value="<?= $code ?>" <?= $val('mode_transport_habituel') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="conditions_paiement">Conditions de paiement</label>
            <select id="conditions_paiement" name="conditions_paiement"><option value="">—</option>
              <?php foreach (Client::CONDITIONS_PAIEMENT as $code => $lib): ?><option value="<?= $code ?>" <?= $val('conditions_paiement') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </details>
    </div>

    <div>
      <div class="card">
        <h2>Gestion</h2>
        <div class="form-group">
          <label for="filiale_id">Filiale *</label>
          <?php if ($estModif): ?>
            <input type="text" value="<?= View::e(array_values(array_filter($filiales, fn($f) => (int) $f['id'] === (int) $client['filiale_id']))[0]['nom'] ?? '—') ?>" disabled>
            <div style="font-size:12px;color:#888;margin-top:4px">Le rattachement à une filiale ne change pas après la création (il protège l’historique des dossiers).</div>
          <?php elseif (count($filiales) > 1): ?>
            <select id="filiale_id" name="filiale_id" required>
              <option value="">— Choisir —</option>
              <?php foreach ($filiales as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (string) $val('filiale_id') === (string) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($erreur('filiale_id')): ?><div style="color:#b42318;font-size:12px;margin-top:4px"><?= View::e($erreur('filiale_id')) ?></div><?php endif; ?>
          <?php elseif (count($filiales) === 1): ?>
            <input type="hidden" name="filiale_id" value="<?= (int) $filiales[0]['id'] ?>">
            <input type="text" value="<?= View::e($filiales[0]['nom']) ?>" disabled>
          <?php else: ?>
            <div class="alert alert-erreur">Aucune filiale ne vous est assignée.</div>
          <?php endif; ?>
        </div>
        <?php if ($schemaPret): ?>
        <div class="form-group">
          <label for="responsable_id">Responsable commercial</label>
          <select id="responsable_id" name="responsable_id">
            <option value="">— Aucun —</option>
            <?php foreach ($utilisateurs as $u): if ((int) ($u['is_active'] ?? $u['actif'] ?? 1) !== 1 && (string) $val('responsable_id') !== (string) $u['id']) { continue; } ?>
              <option value="<?= (int) $u['id'] ?>" <?= (string) $val('responsable_id') === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="relation">Relation commerciale</label>
          <select id="relation" name="relation">
            <?php foreach (Client::RELATIONS as $code => $lib): ?><option value="<?= $code ?>" <?= $relation === $code ? 'selected' : '' ?>><?= $lib ?></option><?php endforeach; ?>
          </select>
          <div style="font-size:12px;color:#888;margin-top:4px">Prospect : pas encore de commande. Client : relation commerciale établie.</div>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label for="statut">Statut</label>
          <select id="statut" name="statut">
            <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
            <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
          </select>
          <div style="font-size:12px;color:#888;margin-top:4px">Un client inactif reste consultable avec tout son historique.</div>
        </div>
        <?php if ($schemaPret): ?>
        <div class="form-group">
          <label for="devise_preferee">Devise préférée</label>
          <select id="devise_preferee" name="devise_preferee">
            <option value="">— Aucune —</option>
            <?php foreach (Client::DEVISES as $dv): ?><option <?= $devise === $dv ? 'selected' : '' ?>><?= $dv ?></option><?php endforeach; ?>
          </select>
          <div style="font-size:12px;color:#888;margin-top:4px">Proposée par défaut sur les nouveaux documents ; ne modifie pas les documents existants.</div>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Notes internes</h2>
        <textarea name="notes" rows="5" placeholder="Visibles uniquement par l’équipe."><?= View::e($val('notes')) ?></textarea>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="submit" class="btn"><?= $estModif ? 'Enregistrer les modifications' : 'Créer le client' ?></button>
        <a class="btn btn-secondary" href="<?= View::e($estModif ? '/index.php?r=clients/' . (int) $client['id'] : $retour) ?>">Annuler</a>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var indicatifs = <?= json_encode(Telephone::PAYS_INDICATIFS, JSON_UNESCAPED_UNICODE) ?>;
  var libelles = {
    'Cameroun': ['N° RCCM', 'N° d’identifiant unique (NIU)'],
    'France': ['SIREN / SIRET', 'N° de TVA intracommunautaire'],
    'Sénégal': ['NINEA / RCCM', 'Identifiant fiscal'],
    "Côte d'Ivoire": ['N° RCCM', 'Numéro de compte contribuable'],
    'Gabon': ['N° RCCM', 'N° d’identification fiscale'],
    'Maroc': ['N° RC / ICE', 'Identifiant fiscal (IF)']
  };
  var pays = document.getElementById('pays');
  var ind = document.getElementById('tel_ind');
  var touche = <?= $estModif ? 'true' : 'false' ?>;
  ind.addEventListener('change', function () { touche = true; });
  function maj() {
    var l = libelles[pays.value.trim()] || ['Numéro d’immatriculation', 'Identifiant fiscal / TVA'];
    document.getElementById('labelSiret').textContent = l[0] + ' (facultatif)';
    document.getElementById('labelTva').textContent = l[1] + ' (facultatif)';
    if (!touche && indicatifs[pays.value.trim()]) { ind.value = indicatifs[pays.value.trim()]; }
  }
  pays.addEventListener('input', maj);
  pays.addEventListener('change', maj);
  maj();
})();
</script>
