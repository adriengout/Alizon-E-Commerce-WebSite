<?php
session_start();
// Connexion à la base de données
require_once("loginBdd.php");

try {
  $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  die("Erreur connexion : " . $e->getMessage());
}

function calculerPrixInfos($prixHT, $rawTva, $rawRemise) {
    $tva = (!empty($rawTva)) ? floatval($rawTva) : 20.0;
    $tva = ($tva > 1) ? $tva / 100 : $tva;
    
    $remise = (!empty($rawRemise)) ? floatval($rawRemise) : 0;
    $remise = ($remise > 1) ? $remise / 100 : $remise;

    $ttcBase = $prixHT * (1 + $tva);
    $ttcFinal = $ttcBase * (1 - $remise);
    
    return [
        'base' => $ttcBase,
        'final' => $ttcFinal,
        'has_promo' => ($remise > 0)
    ];
}

$total_general = 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mon Panier - Alizon</title>
  <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
  <link rel="stylesheet" href="footer.css">
  <link rel="stylesheet" href="panierStyle.css">
  <link rel="stylesheet" href="header.css">
</head>

<body>
  <?php include "header.php"; ?>
  <main>

    <?php 
    if (isset($_SESSION['id_compte']) && !isset($_SESSION['tmp_panier'])) { // Utilisateur connecté
      $id_client = $_SESSION['id_compte'];
      
      $stmt = $pdo->prepare("
        SELECT
            LC.id_produit,
            LC.quantite_prod,
            P.nom_produit, P.prix_ht, P.description_prod,
            V.nom_entreprise,
            I.chemin, I.nom_fichier, I.extension,
            R.taux_remise,
            PR.nom_promotion,
            T.taux_tva
        FROM sae._ligneCommande LC
        JOIN sae._commande C ON LC.num_commande = C.num_commande
        JOIN sae._produit P ON LC.id_produit = P.id_produit
        JOIN sae._vendeur V ON P.id_vendeur = V.id_vendeur
        JOIN sae._image I ON P.id_image = I.id_image
        LEFT JOIN sae._remise R ON P.id_produit = R.id_produit
        LEFT JOIN sae._promotion PR ON P.id_produit = PR.id_produit
        LEFT JOIN sae._tva T ON P.id_produit = T.id_produit
        WHERE C.id_client = :id_client AND C.statut_commande = 'En attente de paiement'
      ");
      $stmt->execute(['id_client' => $id_client]);
      $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $panier_groupe = [];
      foreach ($produits as $ligne) {
        $panier_groupe[$ligne['nom_entreprise']][] = $ligne;
      }
      
      $total_articles = array_sum(array_column($produits, 'quantite_prod')); 
      ?>

      <a href="catalogue.php">← Reprendre mes achats</a>
      <div class="panier-header">
        <h1>Mon panier <?php if ($total_articles > 0): ?>(<?= $total_articles ?> article<?= $total_articles > 1 ? 's' : '' ?>)<?php endif; ?></h1>
        <?php if ($produits) { ?>
          <form id="form_vider_panier" action="supprimer_panier.php" method="post" style="margin: 0;">
            <button type="submit" name="action" value="vider_panier" class="btn-vider-panier" id="btnViderPanier">Vider le panier</button>
          </form>
        <?php } ?>
      </div>

      <?php if (!$produits): ?>
        <div class="panier-vide">
          <div class="panier-vide-icon"></div>
          <h2>Votre panier est vide</h2>
          <p>Vous n'avez pas encore ajouté de produits.</p>
          <a href="catalogue.php" class="btn-continuer">Découvrir nos produits</a>
        </div>
      <?php else: ?>

        <?php foreach ($panier_groupe as $nom_vendeur => $articles): ?>
          <div class="vendeur-section">
              <h2 class="vendeur-nom"><?= htmlspecialchars($nom_vendeur) ?></h2>
          </div>

          <?php foreach ($articles as $pd): 
              $prixInfos = calculerPrixInfos($pd['prix_ht'], $pd['taux_tva'], $pd['taux_remise']);
              
              $prixUnitaireArrondi = round($prixInfos['final'], 2);
              
              $total_ligne = $prixUnitaireArrondi * $pd['quantite_prod'];
              $total_general += $total_ligne;
          ?>
          <div class="produit">
            <a href="descriptionProduitClient.php?id_produit=<?= $pd['id_produit'] ?>">
                <img src="<?= $pd["chemin"] . $pd["nom_fichier"] . $pd["extension"] ?>" alt="<?= htmlspecialchars($pd["nom_produit"]) ?>" class="img-produit">
            </a>
            <div class="info">
              <h2><?= htmlspecialchars($pd["nom_produit"]) ?></h2>
              
              <p class="note">Prix unitaire : 
                  <?php if ($prixInfos['has_promo']): ?>
                      <span style="color: #e74c3c; font-weight: bold;"><?= number_format($prixUnitaireArrondi, 2) ?> €</span>
                      <s style="color: gray; font-size: 0.9em; margin-left:5px;"><?= number_format($prixInfos['base'], 2) ?> €</s>
                  <?php else: ?>
                      <?= number_format($prixUnitaireArrondi, 2) ?> €
                  <?php endif; ?>
              </p>
              <p class="desc"><?= htmlspecialchars($pd["description_prod"]) ?></p>
            </div>

            <div class="prix-actions">
              <h2><?= number_format($total_ligne, 2) ?> €</h2>
              <span>Quantité : <?= $pd['quantite_prod'] ?></span>

              <form method="post" action="supprimer_panier.php">
                <input type="hidden" name="id_produit" value="<?= $pd['id_produit'] ?>">
                <button type="submit" name="action" value="supp_ligne" class="btn-supprimer">Supprimer</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="total">
          <p>Total à régler : <strong><?= number_format($total_general, 2) ?> €</strong></p>
          <a href="paiement.php" class="btn-paiement">Passer au paiement </a>
        </div>
      <?php endif; ?>

    <?php 
    } else { 
      if (!isset($_SESSION['tmp_panier']) || !is_array($_SESSION['tmp_panier'])) {
        $_SESSION['tmp_panier'] = [];
      }
      $panier_temp = $_SESSION['tmp_panier'];
      
      $total_articles = 0;
      foreach ($panier_temp as $p) $total_articles += $p['qtt_panier'] ?? 0;
    ?>
      <a href="catalogue.php">← Reprendre mes achats</a>
      <div class="panier-header">
        <h1>Mon panier <?php if ($total_articles > 0): ?>(<?= $total_articles ?> article<?= $total_articles > 1 ? 's' : '' ?>)<?php endif; ?></h1>
        <?php if (!empty($panier_temp)): ?>
          <form id="form_vider_panier" action="supprimer_panier.php" method="post" style="margin: 0;">
            <button type="submit" name="action" value="vider_panier" class="btn-vider-panier" id="btnViderPanier">Vider le panier</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if (empty($panier_temp)): ?>
        <div class="panier-vide">
          <div class="panier-vide-icon"></div>
          <h2>Votre panier est vide</h2>
          <p>Vous n'avez pas encore ajouté de produits.</p>
          <a href="catalogue.php" class="btn-continuer">Découvrir nos produits</a>
        </div>
      <?php else: ?>
        <?php foreach ($panier_temp as $id_produit => $pd): 
            if (!isset($pd['qtt_panier'])) continue;

            $prixInfos = calculerPrixInfos($pd['prix_ht'], $pd['taux_tva'] ?? 0, $pd['taux_remise'] ?? 0);
            
            $prixUnitaireArrondi = round($prixInfos['final'], 2);
            
            $total_ligne = $prixUnitaireArrondi * $pd['qtt_panier'];
            $total_general += $total_ligne;
        ?>
          <div class="produit">
            <a href="descriptionProduitClient.php?id_produit=<?= $id_produit ?>">
                <img src="<?= $pd['image_chemin'] . $pd['image_nom'] . $pd['image_ext'] ?>" alt="<?= htmlspecialchars($pd['nom_produit']) ?>" class="img-produit">
            </a>
            <div class="info">
              <h2><?= htmlspecialchars($pd['nom_produit']) ?></h2>
              
              <p class="note">Prix unitaire : 
                  <?php if ($prixInfos['has_promo']): ?>
                      <span style="color: #e74c3c; font-weight: bold;"><?= number_format($prixUnitaireArrondi, 2) ?> €</span>
                      <s style="color: gray; font-size: 0.9em; margin-left:5px;"><?= number_format($prixInfos['base'], 2) ?> €</s>
                  <?php else: ?>
                      <?= number_format($prixUnitaireArrondi, 2) ?> €
                  <?php endif; ?>
              </p>
              
              <p class="desc"><?= htmlspecialchars($pd['description_prod'] ?? '') ?></p>
            </div>
            <div class="prix-actions">
              <p class="prix"><?= number_format($total_ligne, 2) ?> €</p>
              <span>Quantité : <?= $pd['qtt_panier'] ?></span>
              <form method="post" action="supprimer_panier.php">
                <input type="hidden" name="id_produit" value="<?= $id_produit ?>">
                <input type="hidden" name="action" value="supp_temp_panier">
                <button type="submit" class="btn-supprimer">Supprimer</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="total">
          <p>Total à régler : <strong><?= number_format($total_general, 2) ?> €</strong></p>
          <a href="paiement.php" class="btn-paiement">Passer au paiement</a>
        </div>
      <?php endif; ?>
    <?php } ?>

<script src="scriptLibrary/confirmDialog.js"></script>
<script>
  const dialog = new ConfirmDialog({
    lang: 'french',
    message: 'Vider votre panier ?',
    okText: 'Vider',
    cancelText: 'Retour'
  });
  const formViderPanier = document.getElementById('form_vider_panier');
  function verifViderPanier(event){
    event.preventDefault();
    dialog.show().then(result => {
      if (result.ok) {
        const inputAction = document.createElement('input'); 
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'vider_panier';
        formViderPanier.appendChild(inputAction);
        formViderPanier.submit();
      }
    });
  }
  if (formViderPanier) {
    formViderPanier.addEventListener('submit', verifViderPanier);
  }
</script>
</main>
<?php include "footer.php" ?>
</body>
</html>