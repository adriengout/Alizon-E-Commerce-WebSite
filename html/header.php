<?php
require_once("loginBdd.php");
$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$is_vendeur = false;
$id_compte = $_SESSION['id_compte'] ?? null;

if (isset($_POST['deconnexion']) && $_POST['deconnexion'] === 'deco') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

if ($id_compte && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sae._vendeur WHERE id_vendeur = :id_compte");
    $stmt->execute(['id_compte' => $id_compte]);
    if ($stmt->fetchColumn() > 0) {
        $is_vendeur = true;
    }
}


if ($id_compte && isset($pdo)) {
    $stmt = $pdo->prepare("select total_nb_prod from sae._commande where id_client = :id_client and statut_commande = 'En attente de paiement'");
    $stmt->execute(['id_client' => $id_compte]);
    $accProduit = $stmt->fetchColumn();
}

$total_articles = 0;
if (!isset($_SESSION['id_compte']) && isset($_SESSION['tmp_panier'])) {
    foreach ($_SESSION['tmp_panier'] as $id => $produitPanier) {
        if (isset($produitPanier['qtt_panier'])) {
            $total_articles += $produitPanier["qtt_panier"];
        }
    }
}


if (!$is_vendeur) { ?>
    <div id="toastSucces" class="toast"></div>
    <div id="toastFailed" class="toast"></div>
<?php }



if (!isset($_SESSION['login']) && !isset($_SESSION['id_compte'])) { ?>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <header class="header">
        <div class="header-top">
            <div class="header-left">
                <a href="index.php"><img src="media/universel/logo-header.png" alt="Alizon" class="logo"></a>
            </div>

            <button class="burger-btn" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="header-right" id="mobile-menu-content">
                <div class="search-container">
                    <form class="search-form" method="get" action="catalogue.php">
                        <input type="search" name="recherche" class="search-input" placeholder="Rechercher..." required>
                        <button type="submit" class="search-btn">
                            <img src="media/header&footer/magnifying-glass-solid-full.svg" alt="Recherche" class="icon-search">
                        </button>
                    </form>
                </div>
                <a href="connexion.php"><button class="btn btn-secondary">Se connecter</button></a>
                <a href="creationCompteClient.php"><button class="btn btn-primary">Créer un compte</button></a>
                <div class="icon-box">
                    <span><?php if ($total_articles !== 0) {
                                echo $total_articles;
                            } ?></span>
                    <a href="panier.php">
                        <button class="icon-btn">
                            <img src="media/header&footer/panier-header.svg" alt="Panier" class="icon">
                        </button>
                    </a>
                </div>
            </div>
        </div>

        <nav class="nav-liens" id="mobile-nav-links">
            <div class="nav-left">
                <a href="catalogue.php?cat=alimentation">Alimentation & Boissons</a>
                <a href="catalogue.php?cat=artisanat">Artisanat & Décoration</a>
                <a href="catalogue.php?cat=mode">Mode & Accessoires</a>
                <a href="catalogue.php?cat=culture">Produits Culturels</a>
                <a href="catalogue.php?cat=derives">Produits Dérivés</a>
            </div>
            <div class="nav-separator"></div>
            <div class="nav-right">
                <a href="catalogue.php?promo=1" class="promo-link">
                    Jusqu'a -50% de réduction sur les promotions du moment
                </a>
            </div>
        </nav>
    </header>
<?php
} elseif ($is_vendeur) { ?>

    <header class="header header-vendeur">
        <div class="header-vendeur-top">
            <div class="vendeur-leftSide">
                <img src="media/universel/logo-header.png" alt="logo_alizon">
                <p>Espace Vendeur</p>
            </div>

            <button class="burger-btn" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="vendeur-rightSide" id="mobile-menu-content">
                <a href="notifications.php">
                    <img src="media/header&footer/Doorbell.png" alt="doorbell">
                </a>
                <a href="modifProfil.php" class="bouton">Mon Compte</a>
                <form action="logout.php" method="post" style="display:inline;">
                    <button type="submit">Se Déconnecter</button>
                </form>
            </div>
        </div>
    </header>

    <div class="vendeur-layout">
        <nav class="sidebar-vendeur" id="mobile-nav-links">
            <a href="accueilVendeur.php">Tableau de Bord</a>
            <a href="stockVendeur.php">Mes produits</a>
            <a href="reassortVendeur.php">Mes Réassorts</a>
            <a href="commandesVendeur.php">Mes commandes</a>
            <a href="gestionAvisVendeur.php">Gestion des Avis</a>
            <a href="promotionsVendeur.php">Promotions et remise</a>
            <a href="#">Statistiques</a>
            <a href="#">Paramètres</a>
        </nav>
        <div class="vendeur-main-content">
<?php
} else { ?>
    <header class="header">
        <div class="header-top">
            <div class="header-left">
                <a href="index.php"><img src="media/universel/logo-header.png" alt="Alizon" class="logo"></a>
            </div>

            <button class="burger-btn" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="header-right" id="mobile-menu-content">
                <div class="search-container">
                    <form class="search-form" method="get" action="catalogue.php">
                        <input type="search" name="recherche" class="search-input" placeholder="Rechercher..." required>
                        <button type="submit" class="search-btn">
                            <img src="media/header&footer/magnifying-glass-solid-full.svg" alt="Recherche" class="icon-search">
                        </button>
                    </form>
                </div>

                <div class="icon-box">
                    <button class="icon-btn">
                        <a href="notifications.php">
                            <img src="media/header&footer/Doorbell.png" alt="Notifications" class="icon">
                        </a>
                        
                    </button>
                </div>

                <a href="modifProfil.php">
                    <button class="btn btn-primary">Mon compte</button>
                </a>
                <div class="icon-box">
                    <span><?php if ($accProduit) {
                                echo $accProduit;
                            } ?></span>
                    <a href="panier.php">
                        <button class="icon-btn">
                            <img src="media/header&footer/panier-header.svg" alt="Panier" class="icon">
                        </button>
                    </a>
                </div>
            </div>
        </div>
        <nav class="nav-liens" id="mobile-nav-links">
            <div class="nav-left">
                <a href="catalogue.php?cat=alimentation">Alimentation & Boissons</a>
                <a href="catalogue.php?cat=artisanat">Artisanat & Décoration</a>
                <a href="catalogue.php?cat=mode">Mode & Accessoires</a>
                <a href="catalogue.php?cat=culture">Produits Culturels</a>
                <a href="catalogue.php?cat=derives">Produits Dérivés</a>
            </div>
            <div class="nav-separator"></div>
            <div class="nav-right">
                <a href="catalogue.php?promo=1" class="promo-link">
                Jusqu'a -50% de réduction sur les promotions du moment
            </a>
            </div>
        </nav>
    </header>
<?php } ?>

<script>
    function toggleMenu() {
        var menuContent = document.getElementById("mobile-menu-content");
        var navLinks = document.getElementById("mobile-nav-links");

        if (menuContent) menuContent.classList.toggle("active");
        if (navLinks) navLinks.classList.toggle("active");
    }


    function showToast(message, type) {
        const toastId = type === "ajoute" ? "toastSucces" : "toastFailed";
        const toast = document.getElementById(toastId);
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add("show");
        setTimeout(() => {
            toast.classList.remove("show");
        }, 1000);
    }



    // Si la session contient un message
    <?php if (isset($_SESSION['toast_message']) && isset($_SESSION['toast_type'])): ?>
        document.addEventListener("DOMContentLoaded", function() {
            showToast("<?= $_SESSION['toast_message'] ?>", "<?= $_SESSION['toast_type'] ?>");
        });
    <?php unset($_SESSION['toast_message'], $_SESSION['toast_type']);
    endif; ?>
</script>