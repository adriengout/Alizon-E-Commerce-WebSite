<?php
$footer_vendeur_class = '';
if (isset($is_vendeur) && $is_vendeur === true) {
    $footer_vendeur_class = ' footer-vendeur';
    // Fermer les divs vendeur avant le footer
    echo '</div><!-- Fin vendeur-main-content -->';
    echo '</div><!-- Fin vendeur-layout -->';
}
?>
<footer class="footer<?php echo $footer_vendeur_class; ?>">

    <section class="logo">
        <h2><img src="media/universel/logo-header.png" alt="Logo Alizon"></h2>
        <nav>
            <ul>
                <li><a href="#">Accueil</a></li>
                <li><a href="#">Boutique</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
    </section>

    <hr>
    
    <section class="support">
        <h3>Aide & Support</h3>
        <ul>
            <li><a href="#">FAQ</a></li>
            <li><a href="#">Suivi de commande</a></li>
            <li><a href="#">Retours & échanges</a></li>
            <li><a href="#">Livraison & paiement</a></li>
            <li><a href="#">Politique de confidentialité</a></li>
            <li><a href="CGV.php">Conditions générales de vente</a></li>
            <li><a href="CGU.php">Conditions générales d'utilisation</a></li>
        </ul>
    </section>

    <hr>

    <section class="contact">
        <h3>Contact</h3>
        <div class="contact-item">
            <img src="media/header&footer/adresse.png" alt="Localisation">
            <span>Lannion, 22300</span>
        </div>
        <div class="contact-item">
            <img src="media/header&footer/tel.png" alt="Téléphone">
            <span>0690000000</span>
        </div>
        <div class="contact-item">
            <img src="media/header&footer/mail.png" alt="Email">
            <a href="mailto:alizon.supportclient@gmail.com">alizon.supportclient@gmail.com</a>
        </div>

        <nav class="socials">
            <a href="#"><img src="media/header&footer/x.png" alt="X"></a>
            <a href="#"><img src="media/header&footer/insta.png" alt="Instagram"></a>
            <a href="#"><img src="media/header&footer/faceb.png" alt="Facebook"></a>
        </nav>
    </section>
    
    <hr>

    <section class="payment">
        <h3>Paiements sécurisés</h3>
        <ul class="payment-logos">
            <li><img src="media/header&footer/visa.png" alt="Visa"></li>
            <li><img src="media/header&footer/mastercard.png" alt="Mastercard"></li>
            <li><img src="media/header&footer/paypal.png" alt="PayPal"></li>
            <li><img src="media/header&footer/gpay.png" alt="Google Pay"></li>
            <li><img src="media/header&footer/apple pay.png" alt="Apple Pay"></li>
            <li><img src="media/header&footer/klarna.png" alt="Klarna"></li>
            <li><img src="media/header&footer/bitcoin.png" alt="Bitcoin"></li>
        </ul>
    </section>

    <div class="footer-bottom">
        <p>© 2025 Alizon - Tous droits réservés</p>
        <p>
            <a href="mentionsLegales.php">Mentions légales</a> |
            <a href="#">Politique de confidentialité</a> |
            <a href="#">Cookies</a>
        </p>
    </div>
</footer>

<?php if(isset($_SESSION['id_compte']) && !(isset($is_vendeur) && $is_vendeur)){ ?>
    <nav class="mobile-footer">
        <a href="index.php"><img src="media/header&footer/Home.png" alt="Accueil"></a>
        <a href="catalogue.php"><img src="media/header&footer/magnifying-glass-regular-full.svg" alt="Recherche"></a>
        <a href="panier.php"><img src="media/header&footer/basket-shopping-solid-full (1).svg" alt="Panier"></a>
        <a href="modifProfil.php"><img src="media/header&footer/user-regular-full (1).svg" alt="Profil"></a>
    </nav>
<?php }elseif(!isset($_SESSION['id_compte'])){ ?>
    <nav class="mobile-footer">
        <a href="index.php"><img src="media/header&footer/Home.png" alt="Accueil"></a>
        <a href="catalogue.php"><img src="media/header&footer/magnifying-glass-regular-full.svg" alt="Recherche"></a>
        <a href="panier.php"><img src="media/header&footer/basket-shopping-solid-full (1).svg" alt="Panier"></a>
        <a href="connexion.php"><img src="media/header&footer/user-regular-full (1).svg" alt="Profil"></a>
    </nav>
<?php }?>

