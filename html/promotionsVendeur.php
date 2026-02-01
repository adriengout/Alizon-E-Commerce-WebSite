<?php
session_start();

if (!isset($_SESSION['id_compte'])) {
  header('Location: connexion.php');
  exit();
}

// Vérifier que l'utilisateur est bien un vendeur
if (!isset($_SESSION['is_vendeur']) || $_SESSION['is_vendeur'] !== true) {
  header('Location: index.php');
  exit();
}

$id_vendeur = $_SESSION['id_compte'];

require_once("loginBdd.php");

$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$requete = "SELECT id_produit, nom_produit, prix_ht, description_prod 
            FROM sae._produit 
            WHERE id_vendeur = :id_vendeur 
            ORDER BY nom_produit";
$stmt = $pdo->prepare($requete);
$stmt->execute(['id_vendeur' => $id_vendeur]);
$produits = $stmt->fetchAll();


$sqlRequest = "select id_produit, nom_produit from sae._produit where id_vendeur = :id_vendeur and id_produit not in (select id_produit from sae._promotion) order by nom_produit";
$stmt = $pdo->prepare($sqlRequest);
$stmt->execute(['id_vendeur' => $id_vendeur]);
$listeProduits = $stmt->fetchAll();


$sqlRequest = "select COUNT(*) from sae._promotion prom join sae._produit prod on prom.id_produit = prod.id_produit where id_vendeur = :id_vendeur";
$stmt = $pdo->prepare($sqlRequest);
$stmt->execute(['id_vendeur' => $id_vendeur]);
$nbPromoVendeur = $stmt->fetchColumn();


$sqlRequest = "SELECT DISTINCT ON (prom.id_promotion) 
                prom.*, 
                prod.nom_produit, 
                prod.prix_ht, 
                r.taux_remise
               FROM sae._promotion prom 
               JOIN sae._produit prod ON prom.id_produit = prod.id_produit 
               LEFT JOIN sae._remise r ON r.id_produit = prom.id_produit 
               WHERE prod.id_vendeur = :id_vendeur
               ORDER BY prom.id_promotion, r.id_remise DESC";
$stmt = $pdo->prepare($sqlRequest);
$stmt->execute(['id_vendeur' => $id_vendeur]);
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alizon</title>
  <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
  <link rel="stylesheet" href="promouvprod.css">
  <link rel="stylesheet" href="header.css">
</head>

<body>
  <?php include 'header.php'; ?>

  <main class="promo-container">
    <form class="promo-form" action="creationPromo.php" method="POST" enctype="multipart/form-data">
      <h2>Création d'une promotion</h2>

      <div class="promo-grid">
        <!-- Colonne gauche -->
        <div class="promo-left">
          <section class="descrip-promo">
            <div class="promo-box">

              <label>Valeur (%):</label>
              <input type="number" name="valeur" placeholder="Ex: 25%" min="0" maximum="100" step="1" />

              <label>Produits concernés :</label>
              <select name="produit_concerne" required>
                <option value="">-- Choisir un produit --</option>
                <?php foreach ($listeProduits as $produit): ?>
                  <option value="<?= $produit['id_produit'] ?>">
                    <?= htmlspecialchars($produit['nom_produit']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </section>

          <section class="descrip-promo">
            <div class="promo-box">
              <label>Date de fin :</label>
              <input type="date" name="date_fin" required />
            </div>
          </section>
        </div>


        <!-- Colonne droite -->
        <div class="promo-right">
          <section class="descrip-promo">
            <div class="promo-box">
              <label>Nom :</label>
              <input type="text" name="nom" placeholder="Ex: Fête de noël 2025" required />
            </div>

            <div class="promo-box">
              <label>Code :</label>
              <input type="text" name="code" placeholder="Ex: noel2025" maxlength="40" />
            </div>

            <div class="promo-box">
              <label>Description :</label>
              <textarea name="description" placeholder="Ex: offre -25% pour noël" maxlength="40" required></textarea>
            </div>
          </section>
        </div>
      </div>

      <div class="promo-actions">
        <button type="button" class="btn-cancel" onclick="window.history.back()">Annuler</button>
        <?php if ($nbPromoVendeur >= 2) { ?>
          <button type="submit" class="btn-save-error" disabled>Déjà deux promotions</button>
        <?php } else { ?>
          <button type="submit" class="btn-save">Publier</button>
        <?php } ?>
      </div>
    </form>

    <section class="promotions-list-container">
      <h2>Mes promotions en cours (<?= $nbPromoVendeur ?>/2)</h2>

      <div class="promotions-grid">
        <?php foreach ($promotions as $promo): ?>
          <div class="promo-card">
            <?php if (!empty($promo['taux_remise'])): ?>
              <div class="promo-badge">-<?= htmlspecialchars($promo['taux_remise'])*100 ?>%</div>
            <?php endif; ?>

            <div class="promo-card-image">
              <img src="media/produits/<?= htmlspecialchars($promo['banniere_promo']) ?>" alt="<?= htmlspecialchars($promo['nom_promotion']) ?>">
            </div>

            <div class="promo-card-content">
              <span class="product-category">Produit : <?= htmlspecialchars($promo['nom_produit']) ?></span>
              <h3><?= htmlspecialchars($promo['nom_promotion']) ?></h3>
              <p class="promo-description"><?= htmlspecialchars($promo['descrip_promotion']) ?></p>

              <div class="promo-footer">
                <div class="promo-dates">
                  <span>Fin le : <strong><?= date('d/m/Y', strtotime($promo['date_fin'])) ?></strong></span>
                </div>
                <div class="promo-price">
                  <span class="reduced-priced"><?= $promo['prix_ht'] - $promo['prix_ht']*($promo['taux_remise']) ?>€</span>
                  <span class="original-price"><?= number_format($promo['prix_ht'], 2) ?>€</span>
                </div>
              </div>

              <div class="promo-card-actions">
                <a href="#" class="btn-edit-small">Modifier</a>
                <a href="supprimerPromo.php?id=<?= $promo['id_produit'] ?>" class="btn-delete-small">Supprimer</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  </main>

</body>

</html>