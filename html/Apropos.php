<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alizon - À propos</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="Apropos.css">
    <link rel="stylesheet" href="footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

  <script>
    const menuToggle = document.getElementById('menu-toggle');
    const mobileDropdown = document.getElementById('mobile-dropdown');

    menuToggle.addEventListener('click', () => {
      mobileDropdown.style.display = mobileDropdown.style.display === 'flex' ? 'none' : 'flex';
    });
  </script>


    <main class="apropos">
        <section class="apropos-container">
            <h1>À propos d’Alizon</h1>
            <p>Bienvenue sur Alizon, votre boutique en ligne de confiance !</p>

            <h2>Notre mission</h2>
            <p>Proposer une large sélection de produits de qualité au meilleur prix tout en garantissant un service client irréprochable.</p>

            <h2>Nos valeurs</h2>
            <ul>
                <li>Transparence et confiance</li>
                <li>Livraison rapide et sécurisée</li>
                <li>Respect de l’environnement</li>
            </ul>

            <h2>Nous contacter</h2>
            <p><img src="media/contact/adresse bleu.png" alt="">Lannion, 22300 
            <p><img src="media/contact/mail bleu.png" alt=""> <a href="mailto:alizon.supportclient@gmail.com">alizon.supportclient@gmail.com</a></p>
            <p><img src="media/contact/tel bleu.png" alt=""> 06 90 00 00 00</p>

            <div class="socials">
                <a href="#"><img src="media/reseau/x bleu.png" alt="X"></a>
                <a href="#"><img src="media/reseau/insta bleu.png" alt="Instagram"></a>
                <a href="#"><img src="media/reseau/faceb bleu.png" alt="Facebook"></a>
            </div>

            <div class="payments">
                <img src="media/paiement/visa.png" alt="Visa">
                <img src="media/paiement/mastercard.png" alt="Mastercard">
                <img src="media/paiement/paypal.png" alt="PayPal">
                <img src="media/paiement/gpay.png" alt="Google Pay">
                <img src="media/paiement/apple pay.png" alt="Apple Pay">
                <img src="media/paiement/klarna.png" alt="Klarna">
                <img src="media/paiement/bitcoin.png" alt="Bitcoin">
            </div>

            <div class="mentions">
                <p>© 2025 Alizon - Tous droits réservés</p>
                <p><a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a> | <a href="#">Cookies</a></p>
            </div>
        </section>
    </main>
    <?php include "footer.php" ?>
</body>
</html>
