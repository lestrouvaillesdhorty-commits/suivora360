<?php
use App\Core\View;
use App\Models\Client;
use App\Models\Dossier;

$ligne = fn(string $lib, ?string $v) => '<div class="info-row"><span class="label">' . View::e($lib) . '</span><span>' . (($v !== null && $v !== '') ? View::e($v) : '—') . '</span></div>';
$adresseComplete = trim(implode(', ', array_filter([$client['adresse'] ?? '', trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')), $client['pays'] ?? ''])));
$recents = array_slice(array_values(array_filter($operations, fn($o) => !empty($o['dossier_id']))), 0, 5);
$aujourdhui = date('Y-m-d');
$prochaines = array_values(array_filter($operations, fn($o) => !empty($o['dossier_id']) && ($o['dossier_statut'] ?? '') === 'actif' && !empty($o['dossier_echeance'])));
usort($prochaines, fn($a, $b) => strcmp($a['dossier_echeance'], $b['dossier_echeance']));
$prochaines = array_slice($prochaines, 0, 5);
$sansDossier = array_slice(array_values(array_filter($operations, fn($o) => empty($o['dossier_id']) && in_array($o['demande_statut'], ['a_qualifier', 'en_attente_info', 'qualifiee'], true))), 0, 5);
?>
<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Contact principal</h2>
      <?= $ligne('Nom', $contactPrincipal) ?>
      <?= $ligne('Fonction', $client['fonction_contact'] ?? '') ?>
      <?= $ligne('E-mail', $client['email'] ?? '') ?>
      <?= $ligne('Téléphone', $client['telephone'] ?? '') ?>
      <div style="margin-top:10px"><?php $bcEmail = $client['email'] ?? ''; $bcTel = $client['telephone'] ?? ''; include __DIR__ . '/_boutons_contact.php'; ?></div>
      <?php if (empty($client['email']) && \App\Core\Telephone::pourWhatsApp($client['telephone'] ?? '') === ''): ?>
        <div style="font-size:12px;color:#888">Aucune coordonnée exploitable pour contacter ce client (e-mail ou téléphone international manquant).</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Coordonnées et gestion</h2>
      <?= $ligne('Adresse', $adresseComplete) ?>
      <?= $ligne('Filiale', $filiale['nom'] ?? '') ?>
      <?php if ($schemaPret): ?>
        <?= $ligne('Responsable commercial', $responsableNom) ?>
        <?= $ligne('Devise préférée', $client['devise_preferee'] ?? '') ?>
      <?php endif; ?>
      <?php if (!empty($client['siret'])): ?><?= $ligne('N° d’immatriculation', $client['siret']) ?><?php endif; ?>
      <?php if (!empty($client['tva'])): ?><?= $ligne('Identifiant fiscal / TVA', $client['tva']) ?><?php endif; ?>
      <?php if (!empty($client['secteur'])): ?><?= $ligne('Secteur', $client['secteur']) ?><?php endif; ?>
      <?php if (!empty($client['adresse_livraison'])): ?><?= $ligne('Livraison habituelle', $client['adresse_livraison']) ?><?php endif; ?>
      <?php if (!empty($client['incoterm_habituel'])): ?><?= $ligne('Incoterm habituel', \App\Models\Demande::INCOTERMS[$client['incoterm_habituel']] ?? $client['incoterm_habituel']) ?><?php endif; ?>
      <?php if (!empty($client['mode_transport_habituel'])): ?><?= $ligne('Transport habituel', Client::MODES_TRANSPORT[$client['mode_transport_habituel']] ?? $client['mode_transport_habituel']) ?><?php endif; ?>
      <?php if (!empty($client['conditions_paiement'])): ?><?= $ligne('Conditions de paiement', Client::CONDITIONS_PAIEMENT[$client['conditions_paiement']] ?? $client['conditions_paiement']) ?><?php endif; ?>
    </div>

    <?php if (!empty($client['notes'])): ?>
    <div class="card">
      <h2>Notes internes</h2>
      <div><?= nl2br(View::e($client['notes'])) ?></div>
      <div style="font-size:12px;color:#888;margin-top:8px">Réservées à l’équipe : jamais exposées au client.</div>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <?php if ($financesAutorisees): ?>
    <div class="card">
      <h2>Indicateurs financiers</h2>
      <?php $lienDetail = true; include __DIR__ . '/_finances_tableau.php'; ?>
      <div style="font-size:12px;color:#888;margin-top:8px">Toutes périodes. <a href="<?= $base ?>&onglet=finances">Détail, bases de calcul et documents sources</a></div>
    </div>
    <?php endif; ?>

    <?php if ($financesAutorisees && $peutEcrire): ?>
    <div class="card" id="espace-client">
      <h2>Espace client</h2>
      <?php if (empty($portailPret)): ?>
        <div class="empty-state">Migration V22 requise pour activer l’espace client.</div>
      <?php else: ?>
        <p style="font-size:13px;color:#666;margin:0 0 10px">Lien privé, sans compte ni mot de passe : le client suit ses dossiers, voit ses cotations et factures, envoie une demande et accepte ou refuse une cotation. Marges, prix d’achat et notes internes ne sont jamais visibles.</p>
        <?php if (!empty($client['is_active'])): ?>
        <form method="post" action="<?= $base ?>/portail" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <select name="jours" aria-label="Durée de validité">
            <?php foreach (\App\Models\PortailClient::DUREES_JOURS as $j): ?>
              <option value="<?= $j ?>" <?= $j === \App\Models\PortailClient::DUREE_DEFAUT ? 'selected' : '' ?>>Valable <?= $j ?> jours</option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm">Créer un lien</button>
        </form>
        <?php endif; ?>
        <?php if (empty($liensPortail)): ?>
          <div class="empty-state">Aucun lien créé pour ce client.</div>
        <?php endif; ?>
        <?php foreach ($liensPortail as $l):
          $revoque = !empty($l['revoque_le']);
          $expire = strtotime((string) $l['expire_le']) <= time();
          $url = ($urlBase ?? '') . '/index.php?r=espace-client/' . $l['token'];
        ?>
          <div style="border-top:1px solid #eee;padding:10px 0">
            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center">
              <span>
                <?php if ($revoque): ?><span class="badge badge-red">Révoqué</span>
                <?php elseif ($expire): ?><span class="badge badge-gray">Expiré</span>
                <?php else: ?><span class="badge badge-green">Actif</span><?php endif; ?>
                <span style="font-size:12px;color:#888">expire le <?= date('d/m/Y', strtotime((string) $l['expire_le'])) ?> · <?= (int) $l['nb_acces'] ?> visite(s)<?= !empty($l['dernier_acces']) ? ', dernière le ' . date('d/m/Y H:i', strtotime((string) $l['dernier_acces'])) : '' ?></span>
              </span>
              <?php if (!$revoque && !$expire): ?>
              <form method="post" action="<?= $base ?>/portail/<?= (int) $l['id'] ?>/revoquer" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Révoquer</button>
              </form>
              <?php endif; ?>
            </div>
            <?php if (!$revoque && !$expire): ?>
              <input type="text" readonly value="<?= View::e($url) ?>" onclick="this.select()" style="width:100%;margin-top:6px;font-size:12px" aria-label="Lien de l’espace client">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>Dossiers récents</h2>
      <?php if (empty($recents)): ?>
        <div class="empty-state">Aucun dossier pour ce client.</div>
      <?php else: foreach ($recents as $o): ?>
        <div class="info-row">
          <span><a href="/index.php?r=dossiers/<?= (int) $o['dossier_id'] ?>"><?= View::e($o['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($o['objet']) ?></span></span>
          <span><span class="badge badge-blue"><?= View::e(Dossier::ETAPES_LABELS[$o['etape']] ?? $o['etape']) ?></span></span>
        </div>
      <?php endforeach; endif; ?>
      <div style="margin-top:8px"><a href="<?= $base ?>&onglet=operations">Voir toutes les demandes et dossiers</a></div>
    </div>

    <div class="card">
      <h2>Prochaines actions</h2>
      <?php if (empty($prochaines) && empty($sansDossier)): ?>
        <div class="empty-state">Aucune échéance ni demande en attente.</div>
      <?php endif; ?>
      <?php foreach ($prochaines as $o): $retard = $o['dossier_echeance'] < $aujourdhui; ?>
        <div class="info-row">
          <span><a href="/index.php?r=dossiers/<?= (int) $o['dossier_id'] ?>"><?= View::e($o['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888">Échéance du dossier</span></span>
          <span style="<?= $retard ? 'color:#991b1b;font-weight:600' : '' ?>"><?= date('d/m/Y', strtotime($o['dossier_echeance'])) ?><?= $retard ? ' (dépassée)' : '' ?></span>
        </div>
      <?php endforeach; ?>
      <?php foreach ($sansDossier as $o): ?>
        <div class="info-row">
          <span><a href="/index.php?r=demandes/<?= (int) $o['demande_id'] ?>"><?= View::e($o['demande_reference']) ?></a><br><span style="font-size:12px;color:#888">Demande à traiter</span></span>
          <span><?= !empty($o['demande_echeance']) ? date('d/m/Y', strtotime($o['demande_echeance'])) : '—' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
