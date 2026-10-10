<?php
use App\Core\Telephone;
use App\Core\View;
use App\Models\Client;

/** Onglet Contacts et adresses. Formulaires d'ajout/modification via <details>. */
$paysListe = Telephone::pays();
$defIndicatif = Telephone::indicatifPourPays($client['pays'] ?? '');
$formContact = function (?array $c) use ($client, $base, $csrfToken, $defIndicatif) {
    [$ind, $num] = $c ? Telephone::decouper($c['telephone']) : [$defIndicatif, ''];
    $tphIndicatif = $ind; $tphNumero = $num; $tphId = 'tel_c' . ($c['id'] ?? 'new');
    ?>
    <form method="post" action="<?= $base ?>/contacts" style="margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <?php if ($c): ?><input type="hidden" name="contact_id" value="<?= (int) $c['id'] ?>"><?php endif; ?>
      <div class="form-row">
        <div class="form-group"><label>Prénom</label><input type="text" name="prenom" value="<?= View::e($c['prenom'] ?? '') ?>"></div>
        <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= View::e($c['nom'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Fonction</label><input type="text" name="fonction" value="<?= View::e($c['fonction'] ?? '') ?>"></div>
        <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= View::e($c['email'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>Téléphone</label><?php include __DIR__ . '/_telephone.php'; ?></div>
      <button type="submit" class="btn btn-sm"><?= $c ? 'Enregistrer' : 'Ajouter le contact' ?></button>
    </form>
    <?php
};
$formAdresse = function (?array $a) use ($base, $csrfToken, $paysListe, $client) {
    ?>
    <form method="post" action="<?= $base ?>/adresses" style="margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <?php if ($a): ?><input type="hidden" name="adresse_id" value="<?= (int) $a['id'] ?>"><?php endif; ?>
      <div class="form-row">
        <div class="form-group">
          <label>Type</label>
          <select name="type"><?php foreach (Client::TYPES_ADRESSE as $code => $lib): ?><option value="<?= $code ?>" <?= ($a['type'] ?? 'livraison') === $code ? 'selected' : '' ?>><?= $lib ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Libellé (ex. Entrepôt Douala)</label><input type="text" name="libelle" value="<?= View::e($a['libelle'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= View::e($a['adresse'] ?? '') ?>"></div>
      <div class="form-row">
        <div class="form-group"><label>Code postal</label><input type="text" name="code_postal" value="<?= View::e($a['code_postal'] ?? '') ?>"></div>
        <div class="form-group"><label>Ville</label><input type="text" name="ville" value="<?= View::e($a['ville'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label>Pays</label><input type="text" name="pays" list="listePaysAdr" value="<?= View::e($a['pays'] ?? ($client['pays'] ?? '')) ?>"></div>
      <button type="submit" class="btn btn-sm"><?= $a ? 'Enregistrer' : 'Ajouter l’adresse' ?></button>
    </form>
    <?php
};
$postAction = function (string $url, string $lib, string $classe = 'btn btn-sm btn-secondary') use ($csrfToken) {
    return '<form method="post" action="' . View::e($url) . '" style="display:inline"><input type="hidden" name="csrf_token" value="' . View::e($csrfToken) . '"><button type="submit" class="' . $classe . '">' . View::e($lib) . '</button></form>';
};
$adressePrincipale = trim(implode(', ', array_filter([$client['adresse'] ?? '', trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')), $client['pays'] ?? ''])));
?>
<datalist id="listePaysAdr"><?php foreach ($paysListe as $p): ?><option value="<?= View::e($p) ?>"><?php endforeach; ?></datalist>
<?php if (!$schemaPret): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e">Contacts multiples et adresses supplémentaires : disponibles après la mise à jour de la base (migration V21).</div>
<?php endif; ?>
<div class="detail-grid">
  <div class="card">
    <h2>Contacts</h2>
    <div class="info-row" style="display:block">
      <strong><?= View::e($contactPrincipal !== '' ? $contactPrincipal : ($client['nom'] . ' (contact non renseigné)')) ?></strong>
      <span class="badge badge-blue">Contact principal</span><br>
      <span style="font-size:13px;color:#666"><?= View::e($client['fonction_contact'] ?? '') ?></span>
      <div style="font-size:13px"><?= View::e($client['email'] ?? '') ?><?= (!empty($client['email']) && !empty($client['telephone'])) ? ' · ' : '' ?><?= View::e($client['telephone'] ?? '') ?></div>
      <div style="margin-top:6px"><?php $bcEmail = $client['email'] ?? ''; $bcTel = $client['telephone'] ?? ''; include __DIR__ . '/_boutons_contact.php'; ?>
        <?php if ($peutEcrire): ?><a class="btn btn-sm btn-secondary" href="<?= $base ?>/modifier">Modifier</a><?php endif; ?></div>
    </div>
    <?php foreach ($contacts as $c): $cNom = trim($c['prenom'] . ' ' . $c['nom']); $cActif = (int) $c['actif'] === 1; ?>
      <div class="info-row" style="display:block;<?= $cActif ? '' : 'opacity:.6' ?>">
        <strong><?= View::e($cNom) ?></strong>
        <?php if (!$cActif): ?><span class="badge badge-gray">Désactivé</span><?php endif; ?><br>
        <span style="font-size:13px;color:#666"><?= View::e($c['fonction']) ?></span>
        <div style="font-size:13px"><?= View::e($c['email']) ?><?= ($c['email'] !== '' && $c['telephone'] !== '') ? ' · ' : '' ?><?= View::e($c['telephone']) ?></div>
        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
          <?php if ($cActif) { $bcEmail = $c['email']; $bcTel = $c['telephone']; include __DIR__ . '/_boutons_contact.php'; } ?>
          <?php if ($peutEcrire): ?>
            <?php if ($cActif): ?>
              <?= $postAction($base . '/contacts/' . (int) $c['id'] . '/principal', 'Définir comme principal') ?>
              <?= $postAction($base . '/contacts/' . (int) $c['id'] . '/desactiver', 'Désactiver') ?>
            <?php else: ?>
              <?= $postAction($base . '/contacts/' . (int) $c['id'] . '/reactiver', 'Réactiver') ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($peutEcrire && $cActif): ?>
          <details style="margin-top:6px"><summary style="cursor:pointer;font-size:13px;color:var(--primary)">Modifier</summary><?php $formContact($c); ?></details>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($peutEcrire && $schemaPret): ?>
      <details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--primary)">+ Ajouter un contact</summary><?php $formContact(null); ?></details>
    <?php endif; ?>
    <div style="font-size:12px;color:#888;margin-top:10px">Un contact désactivé reste dans l’historique. Les boutons E-mail et WhatsApp ouvrent votre messagerie : aucun envoi n’est enregistré.</div>
  </div>

  <div class="card">
    <h2>Adresses</h2>
    <div class="info-row" style="display:block">
      <strong>Adresse principale</strong> <span class="badge badge-blue">Facturation par défaut</span><br>
      <span style="font-size:13px"><?= View::e($adressePrincipale) ?: '<span style="color:#999">Non renseignée</span>' ?></span>
      <?php if ($peutEcrire): ?><div style="margin-top:6px"><a class="btn btn-sm btn-secondary" href="<?= $base ?>/modifier">Modifier</a></div><?php endif; ?>
    </div>
    <?php if (!empty($client['adresse_livraison'])): ?>
      <div class="info-row" style="display:block"><strong>Livraison habituelle</strong><br><span style="font-size:13px"><?= View::e($client['adresse_livraison']) ?></span></div>
    <?php endif; ?>
    <?php foreach ($adresses as $a): $aActif = (int) $a['actif'] === 1;
        $texte = trim(implode(', ', array_filter([$a['adresse'], trim($a['code_postal'] . ' ' . $a['ville']), $a['pays']]))); ?>
      <div class="info-row" style="display:block;<?= $aActif ? '' : 'opacity:.6' ?>">
        <strong><?= View::e($a['libelle'] !== '' ? $a['libelle'] : (Client::TYPES_ADRESSE[$a['type']] ?? $a['type'])) ?></strong>
        <span class="badge badge-gray"><?= View::e(Client::TYPES_ADRESSE[$a['type']] ?? $a['type']) ?></span>
        <?php if ((int) $a['par_defaut'] === 1 && $aActif): ?><span class="badge badge-green">Par défaut</span><?php endif; ?>
        <?php if (!$aActif): ?><span class="badge badge-gray">Désactivée</span><?php endif; ?><br>
        <span style="font-size:13px"><?= View::e($texte) ?></span>
        <?php if ($peutEcrire): ?>
        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
          <?php if ($aActif): ?>
            <?php if (!((int) $a['par_defaut'] === 1 && $a['type'] === 'livraison')): ?><?= $postAction($base . '/adresses/' . (int) $a['id'] . '/defaut', $a['type'] === 'facturation' ? 'Définir comme facturation par défaut' : 'Définir comme livraison par défaut') ?><?php endif; ?>
            <?= $postAction($base . '/adresses/' . (int) $a['id'] . '/desactiver', 'Désactiver') ?>
          <?php else: ?>
            <?= $postAction($base . '/adresses/' . (int) $a['id'] . '/reactiver', 'Réactiver') ?>
          <?php endif; ?>
        </div>
        <?php if ($aActif): ?><details style="margin-top:6px"><summary style="cursor:pointer;font-size:13px;color:var(--primary)">Modifier</summary><?php $formAdresse($a); ?></details><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($peutEcrire && $schemaPret): ?>
      <details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--primary)">+ Ajouter une adresse (livraison ou facturation)</summary><?php $formAdresse(null); ?></details>
    <?php endif; ?>
  </div>
</div>
