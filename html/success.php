<?php
session_start();

if (!isset($_SESSION['commande_success'])) {
    header("Location: panier.php");
    exit();
}

$commande = $_SESSION['commande_success'];
$items = $commande['cart_items'];
$total = $commande['cart_total'];
$nom = $commande['nom'];
$prenom = $commande['prenom'];
$email = $commande['email'];
$adresse = $commande['adresse'];
$num_command = $commande['num_commande'];
$id_facture = $commande['id_facture'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement réussi - Alizon</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="success.css">
    <link rel="stylesheet" href="footer.css">
</head>
<body>
    <main>
        <div class="container">
            <div class="success-box">
                <div class="success-header">
                    
                    <h1>Paiement réussi !</h1>
                    <p class="subtitle">Merci pour votre confiance</p>
                </div>

                <div class="info-section">
                    <div class="info-row">
                        <span class="info-label">Numéro de commande :</span>
                        <span class="info-value command-number">#<?= str_pad($num_command, 8, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Numéro de facture :</span>
                        <span class="info-value">#<?= str_pad($id_facture, 8, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Client :</span>
                        <span class="info-value"><?= htmlspecialchars($prenom . " " . $nom) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email :</span>
                        <span class="info-value"><?= htmlspecialchars($email) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Adresse de livraison :</span>
                        <span class="info-value"><?= htmlspecialchars($adresse) ?></span>
                    </div>
                </div>

            

                <h2>Détails de votre commande</h2>

                <?php foreach ($items as $item): ?>
                <div class="item">
                    <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
                    <span class="item-qty">Quantité : <?= $item['qty'] ?> × <?= number_format($item['price_ttc'], 2) ?> €</span>
                    <span class="item-price"><?= number_format($item['subtotal'], 2) ?> €</span>
                </div>
                <?php endforeach; ?>

                <div class="total-section">
                    <div class="total">
                        <span>Total TTC</span>
                        <span><?= number_format($total, 2) ?> €</span>
                    </div>
                </div>

                

                

                <div class="buttons">
                    <a href="catalogue.php" class="btn btn-primary">Continuer mes achats</a>
                    <a href="modifProfil.php?command=1" class="btn btn-secondary">Voir mes commandes</a>
                    
                </div>
            </div>
        </div>
    </main>
    <script>

    setTimeout(() => {
        fetch('clear_success_session.php')
            .catch(err => console.error('Erreur nettoyage session:', err));
    }, 5000);
    </script>

</body>
</html>
