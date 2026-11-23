<?php use App\Core\View; use App\Models\Commande; use App\Models\Facture; use App\Models\Dossier; ?>
<?php $typeDossier = $dossier['type_dossier'] ?? 'autre'; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($commande['reference']) ?></h1>
    <div class="subtitle">Suivi opérationnel — Dossier <?= View::e($dossier['reference']) ?></div>
  </div>
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/factures/nouvelle" class="btn">+ Ajouter une facture</a>
</div>

<div class="detail-grid">
  <div>
    <?php
    /* Étapes de suivi : frise + étape en cours ouverte, les autres sur une ligne (« Modifier » les déplie). */
    $courte = ['paiement' => 'Paiement', 'expedition' => 'Expédition', 'transitaire' => 'Transitaire', 'douane' => 'Dédouanement', 'livraison' => 'Livraison', 'solde' => 'Solde'];
    $groupes = ['DÉPART' => ['paiement', 'expedition'], 'ACHEMINEMENT' => ['transitaire', 'douane', 'livraison'], 'CLÔTURE' => ['solde']];
    $jm = fn($d) => !empty($d) && strtotime((string) $d) ? date('d/m', strtotime((string) $d)) : '';
    $jmy = fn($d) => !empty($d) && strtotime((string) $d) ? date('d/m/Y', strtotime((string) $d)) : '';
    $stepsParCode = [];
    $numero = [];
    foreach ($steps as $i => $st) { $stepsParCode[$st['libelle']] = $st; $numero[$st['libelle']] = $i + 1; }
    $codeCourant = null;
    foreach ($steps as $st) {
        if ($st['statut'] !== 'termine' && $st['statut'] !== Commande::STATUT_INCLUS) { $codeCourant = $st['libelle']; break; }
    }
    $classeEtape = function (array $st) use ($codeCourant) {
        if ($st['statut'] === Commande::STATUT_INCLUS) { return 'opt'; }
        if ($st['statut'] === 'termine') { return 'done'; }
        return $st['libelle'] === $codeCourant ? 'cur' : '';
    };
    $transitDispo = Commande::transitaireDispo();
    $sousTitre = function (array $st) use ($jm, $jmy, $transitDispo) {
        $c = $st['libelle'];
        if ($st['statut'] === Commande::STATUT_INCLUS) { return $c === 'transitaire' ? 'Envoi direct : rien à suivre' : 'Inclus dans le transport'; }
        if ($c === 'transitaire' && $transitDispo) {
            $bits = [];
            if (!empty($st['transitaire_nom'])) { $bits[] = $st['transitaire_nom']; }
            if (!empty($st['date_depart'])) { $bits[] = 'repartie le ' . $jmy($st['date_depart']); }
            elseif (!empty($st['date_arrivee'])) { $bits[] = 'reçue le ' . $jmy($st['date_arrivee']); }
            if ($bits) { return implode(' · ', $bits); }
        }
        if (!empty($st['date_reelle'])) { return Commande::libelleStatutEtape($c, $st['statut']) . ' le ' . $jmy($st['date_reelle']); }
        return Commande::libelleStatutEtape($c, $st['statut']);
    };
    $prochaine = [
        'paiement' => 'confirmer le paiement',
        'expedition' => "attendre le départ de la marchandise",
        'transitaire' => 'suivre la marchandise chez le transitaire',
        'douane' => 'suivre le dédouanement',
        'livraison' => 'suivre la livraison au client',
        'solde' => 'émettre la facture de solde',
    ];
    $badgeStyle = ['termine' => 'background:#DCFCE7;color:#166534', 'en_cours' => 'background:#DBEAFE;color:#1E40AF', 'a_faire' => 'background:#E5E7EB;color:#374151', 'inclus' => 'background:#EEF6F7;color:#0F3F46'];
    $formEtape = function (array $st) use ($commande, $csrfToken, $transitaires, $transitDispo) {
        $c = $st['libelle'];
        ob_start(); ?>
        <form method="post" action="/index.php?r=commandes/<?= (int) $commande['id'] ?>/etapes/<?= (int) $st['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <?php if ($c === 'transitaire' && $transitDispo): ?>
          <div class="form-row">
            <div class="form-group" style="margin-bottom:8px"><label>Transitaire</label>
              <select name="transitaire_id">
                <option value="0">— Choisir dans la liste —</option>
                <?php foreach ($transitaires as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($st['transitaire_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= View::e($t['nom']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:8px"><label>Ou un autre transitaire (pas dans la liste)</label>
              <input type="text" name="transitaire_nom" value="<?= View::e(empty($st['transitaire_id']) ? ($st['transitaire_nom'] ?? '') : '') ?>" placeholder="Nom du transitaire">
            </div>
          </div>
          <?php endif; ?>
          <div class="form-row">
            <div class="form-group" style="margin-bottom:8px"><label>Où en est cette étape ?</label>
              <select name="statut">
                <?php foreach (Commande::STEP_STATUTS as $code => $label): ?>
                  <option value="<?= $code ?>" <?= $st['statut'] === $code ? 'selected' : '' ?>><?= Commande::libelleStatutEtape($c, $code) ?></option>
                <?php endforeach; ?>
                <?php if (in_array($c, Commande::ETAPES_FACULTATIVES, true)): ?><option value="inclus" <?= $st['statut'] === 'inclus' ? 'selected' : '' ?>><?= $c === 'transitaire' ? 'Sans transitaire (envoi direct)' : 'Inclus dans le transport (rien à faire)' ?></option><?php endif; ?>
              </select>
            </div>
            <?php if ($c === 'transitaire' && $transitDispo): ?>
            <div class="form-group" style="margin-bottom:8px"><label>Reçue chez lui le</label><input type="date" name="date_arrivee" value="<?= View::e($st['date_arrivee'] ?? '') ?>"></div>
            <div class="form-group" style="margin-bottom:8px"><label>Repartie vers la destination le</label><input type="date" name="date_depart" value="<?= View::e($st['date_depart'] ?? '') ?>"></div>
            <?php else: ?>
            <div class="form-group" style="margin-bottom:8px"><label>Date réelle</label><input type="date" name="date_reelle" value="<?= View::e($st['date_reelle'] ?? '') ?>"></div>
            <?php endif; ?>
          </div>
          <input type="text" name="notes" value="<?= View::e($st['notes'] ?? '') ?>" placeholder="Note sur cette étape<?= $c === 'transitaire' ? ' (ex. attente de regroupement)' : '' ?>" style="margin-bottom:8px">
          <button type="submit" class="btn btn-sm">Enregistrer</button>
          <?php if (in_array($c, Commande::ETAPES_FACULTATIVES, true) && $st['statut'] !== 'inclus'): ?>
            <button type="submit" name="statut" value="inclus" class="btn btn-sm btn-secondary"><?= $c === 'transitaire' ? 'Sans transitaire (envoi direct)' : 'Inclus dans le transport' ?></button>
          <?php endif; ?>
        </form>
        <?php
        return ob_get_clean();
    };
    ?>
    <style>
      .stp{display:flex;align-items:flex-start;margin:8px 0 6px}
      .stn{flex:1;text-align:center;position:relative;font-size:12px;color:#5B6B7B;min-width:0;padding:0 2px}
      .stn:before{content:"";position:absolute;top:13px;left:-50%;width:100%;height:3px;background:#DDE4EC;z-index:0}
      .stn:first-child:before{display:none}
      .stn.done:before,.stn.cur:before{background:#16a34a}
      .stn .dot{width:28px;height:28px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;position:relative;z-index:1;background:#fff;border:2px solid #C9D3DD;color:#8A98A5;box-sizing:border-box}
      .stn.done .dot{background:#16a34a;border-color:#16a34a;color:#fff}
      .stn.cur .dot{background:#0B7C8A;border-color:#0B7C8A;color:#fff;box-shadow:0 0 0 5px #D6EEF1}
      .stn.opt .dot{border-style:dashed;background:#F5F7FA}
      .stn b{display:block;color:#1B2B3A;font-size:12.5px;overflow:hidden;text-overflow:ellipsis}
      .stn.cur b{color:#0B7C8A}
      .stn small{display:block;font-size:11.5px;margin-top:1px;overflow:hidden;text-overflow:ellipsis}
      .etp-ph{font-size:11px;font-weight:700;color:#5B6B7B;padding:12px 4px 4px;letter-spacing:.2px}
      .etp-ln{display:flex;align-items:center;gap:10px;padding:10px 4px;border-bottom:1px solid #EEF1F5;list-style:none;cursor:pointer}
      .etp-ln::-webkit-details-marker{display:none}
      .etp-ic{width:22px;height:22px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;font-size:12px;color:#fff;font-weight:700}
      .etp-nm{flex:1;min-width:0}.etp-nm b{font-size:13.5px}.etp-nm span{display:block;font-size:12px;color:#5B6B7B}
      .etp-b{border-radius:99px;padding:2px 10px;font-size:12px;font-weight:600;white-space:nowrap}
      .etp-md{font-size:12.5px;color:#0B7C8A;border:1px solid #BFE0E5;border-radius:8px;padding:3px 10px;flex:none}
      .etp-opt{border:1px dashed #C9D3DD;background:#FAFBFC;border-radius:10px;margin:4px 0}
      .etp-cur{border:2px solid #0B7C8A;background:#F2FAFB;border-radius:12px;padding:14px;margin:8px 0}
      .etp-body{padding:10px 4px 12px}
      .detail-grid > div{min-width:0}
      @media (max-width:640px){.etp-ln{flex-wrap:wrap}.etp-nm{flex:1 1 calc(100% - 40px)}.etp-b{white-space:normal}.etp-cur{padding:12px}.stp{flex-direction:column;align-items:stretch;padding-left:34px;position:relative}.stn{text-align:left;padding:0 0 14px;display:block}.stn:before{top:26px;left:-21px;width:3px;height:calc(100% - 20px)}.stn .dot{position:absolute;left:-34px;top:0;margin:0;width:24px;height:24px;font-size:12px}.stn:first-child:before{display:block}.stn:last-child:before{display:none}}
    </style>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px">
        <h2 style="margin:0">Étapes de suivi</h2>
        <strong style="font-size:14px"><?= $progression ?>%</strong>
      </div>
      <div style="background:#f1f2f5;border-radius:6px;height:8px;overflow:hidden;margin-bottom:8px">
        <div style="background:<?= $progression === 100 ? '#16a34a' : '#0B7C8A' ?>;height:100%;width:<?= $progression ?>%"></div>
      </div>
      <div class="stp">
        <?php foreach ($steps as $st): $cl = $classeEtape($st); ?>
          <div class="stn <?= $cl ?>"><div class="dot"><?= $cl === 'done' ? '✓' : ($cl === 'opt' ? '–' : $numero[$st['libelle']]) ?></div>
            <b><?= View::e($courte[$st['libelle']] ?? $st['libelle']) ?></b>
            <small><?php
              if ($cl === 'opt') { echo 'sans objet'; }
              elseif ($cl === 'done') { $dd = $st['libelle'] === 'transitaire' ? ($st['date_depart'] ?: ($st['date_arrivee'] ?: $st['date_reelle'])) : $st['date_reelle']; echo $dd ? $jm($dd) : 'terminé'; }
              elseif ($cl === 'cur') { echo $st['libelle'] === 'transitaire' && !empty($st['date_arrivee']) && $st['statut'] === 'en_cours' ? 'reçue le ' . $jm($st['date_arrivee']) : ($st['statut'] === 'en_cours' ? 'en cours' : 'à faire'); }
              else { echo 'à venir'; }
            ?></small></div>
        <?php endforeach; ?>
      </div>
      <div style="font-size:13px;margin:4px 0 2px"><?= $codeCourant ? 'Prochaine action : <b>' . View::e($prochaine[$codeCourant] ?? 'suivre l\'étape en cours') . '</b>' : '<b>Toutes les étapes sont terminées.</b>' ?></div>

      <?php foreach ($groupes as $titreGroupe => $codes):
        $dansGroupe = array_values(array_filter($codes, fn($c) => isset($stepsParCode[$c])));
        if (!$dansGroupe) { continue; } ?>
        <div class="etp-ph"><?= $titreGroupe ?></div>
        <?php foreach ($dansGroupe as $c): $st = $stepsParCode[$c]; $cl = $classeEtape($st);
          $nomEtape = Commande::ETAPES_STEPS[$c] ?? $c;
          $couleur = ['done' => '#16a34a', 'cur' => '#0B7C8A', 'opt' => '#C9D3DD', '' => '#C9D3DD'][$cl];
          $marque = $cl === 'done' ? '✓' : ($cl === 'opt' ? '–' : $numero[$c]);
          if ($cl === 'cur'): ?>
            <div class="etp-cur">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                <div><b style="font-size:15px"><?= View::e($nomEtape) ?></b><div style="font-size:12.5px;color:#5B6B7B;margin-top:2px">Étape en cours · <?= View::e($sousTitre($st)) ?></div></div>
                <span class="etp-b" style="<?= $badgeStyle[$st['statut']] ?? $badgeStyle['a_faire'] ?>"><?= Commande::libelleStatutEtape($c, $st['statut']) ?></span>
              </div>
              <?= $formEtape($st) ?>
            </div>
          <?php else: ?>
            <details class="<?= $cl === 'opt' ? 'etp-opt' : '' ?>">
              <summary class="etp-ln">
                <span class="etp-ic" style="background:<?= $couleur ?>"><?= $marque ?></span>
                <span class="etp-nm"><b><?= View::e($nomEtape) ?></b><span><?= View::e($sousTitre($st)) ?></span></span>
                <span class="etp-b" style="<?= $badgeStyle[$st['statut']] ?? $badgeStyle['a_faire'] ?>"><?= Commande::libelleStatutEtape($c, $st['statut']) ?></span>
                <span class="etp-md">Modifier</span>
              </summary>
              <div class="etp-body"><?= $formEtape($st) ?></div>
            </details>
          <?php endif;
        endforeach;
      endforeach; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Commande</h2>
      <div class="info-row"><span class="label">Statut</span><span><?= $commande['statut'] === 'terminee' ? 'Terminée' : 'En cours' ?></span></div>
      <div class="info-row"><span class="label">Étape courante</span><span><?= Commande::ETAPES_STEPS[$commande['etape']] ?? ($commande['etape'] === 'terminee' ? 'Terminée' : $commande['etape']) ?></span></div>
    </div>

    <div class="card">
      <h2>Suivi &amp; relance</h2>
      <?php if (Commande::estEnRetard($commande)): ?>
        <div class="alert alert-erreur">À relancer : la date prévue (<?= date('d/m/Y', strtotime($commande['date_relance'])) ?>) est dépassée.</div>
      <?php endif; ?>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/suivi">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><label>Prochaine action</label><input type="text" name="prochaine_action" value="<?= View::e($commande['prochaine_action'] ?? '') ?>" placeholder="ex: relancer le fournisseur pour la date d'expédition"></div>
        <div class="form-group"><label>Date de relance</label><input type="date" name="date_relance" value="<?= View::e($commande['date_relance'] ?? '') ?>"></div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>
      </form>
    </div>

    <?php if ($typeDossier !== 'prestation_entreprise' && \App\Core\Schema::colonne('commandes', 'port_depart')): ?>
    <div class="card">
      <h2>Trajet de la marchandise</h2>
      <p class="hint" style="margin-top:0">Alimente le bloc « Vos flux en cours » du tableau de bord.</p>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/logistique">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><label>Mode de transport</label>
          <select name="mode_transport"><option value="">— Selon l'offre retenue —</option>
          <?php foreach (Commande::MODES_TRAJET as $k => $l): ?><option value="<?= $k ?>" <?= ($commande['mode_transport'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-row">
          <div class="form-group"><label>Départ (port, aéroport, ville)</label><input type="text" name="port_depart" placeholder="ex : Le Havre" value="<?= View::e($commande['port_depart'] ?? '') ?>"></div>
          <div class="form-group"><label>Arrivée</label><input type="text" name="port_arrivee" placeholder="ex : Douala" value="<?= View::e($commande['port_arrivee'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>Arrivée prévue</label><input type="date" name="date_arrivee_prevue" value="<?= View::e($commande['date_arrivee_prevue'] ?? '') ?>"></div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer le trajet</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($typeDossier === 'transport_logistique'): ?>
    <div class="card">
      <h2>Transport</h2>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/logistique">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><label>Numéro de tracking</label><input type="text" name="tracking_numero" value="<?= View::e($commande['tracking_numero'] ?? '') ?>"></div>
        <div class="form-row">
          <div class="form-group"><label>Début de transit</label><input type="date" name="date_transit_debut" value="<?= View::e($commande['date_transit_debut'] ?? '') ?>"></div>
          <div class="form-group"><label>Fin de transit</label><input type="date" name="date_transit_fin" value="<?= View::e($commande['date_transit_fin'] ?? '') ?>"></div>
        </div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>
      </form>
    </div>
    <?php elseif ($typeDossier === 'prestation_entreprise'): ?>
    <div class="card">
      <h2>Livrables</h2>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/livrables">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><textarea name="livrables" rows="3" placeholder="ex: Rapport de prospection remis, 3 rendez-vous pris..."><?= View::e($commande['livrables'] ?? '') ?></textarea></div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>Factures</h2>
      <?php if (empty($factures)): ?>
        <div class="empty-state">Aucune facture pour ce dossier.</div>
      <?php else: ?>
        <?php foreach ($factures as $f): ?>
          <div style="padding:10px 0;border-bottom:1px solid #f1f2f5">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div>
                <strong><?= View::e($f['reference']) ?></strong>
                <div style="font-size:12px;color:#666"><?= Facture::TYPES[$f['type']] ?? $f['type'] ?> — <?= number_format((float) $f['montant'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></div>
                <?php if (!empty($f['numero_officiel']) || !empty($f['fichier'])): ?><div style="font-size:12px;color:#666"><?= !empty($f['numero_officiel']) ? 'Facture officielle n° ' . View::e($f['numero_officiel']) : '' ?><?= !empty($f['numero_officiel']) && !empty($f['fichier']) ? ' · ' : '' ?><?php if (!empty($f['fichier'])): ?><a href="/index.php?r=factures/<?= (int) $f['id'] ?>/fichier">📎 <?= View::e($f['fichier_nom'] ?: 'Pièce jointe') ?></a><?php endif; ?></div><?php endif; ?>
              </div>
              <span class="badge <?= $f['statut'] === 'payee' ? 'badge-green' : ($f['statut'] === 'annulee' ? 'badge-red' : 'badge-blue') ?>"><?= Facture::STATUTS[$f['statut']] ?? $f['statut'] ?></span>
            </div>
            <?php if ($f['statut'] === 'emise'): ?>
            <form method="post" action="/index.php?r=factures/<?= $f['id'] ?>/statut" style="margin-top:8px">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <input type="hidden" name="statut" value="payee">
              <button type="submit" class="btn btn-sm btn-secondary">Marquer payée</button>
            </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
