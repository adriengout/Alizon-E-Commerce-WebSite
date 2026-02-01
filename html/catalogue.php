<?php
// Démarre la session si elle n'est pas déjà démarrée 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("loginBdd.php");
$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cat = $_GET['cat'] ?? null;

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="catalogueStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">

</head>

<body>
    <?php include "header.php"; ?>
    <main class="catalogue-main">
        <div class="catalogue-wrap">
            <aside class="catalogue-sidebar">
                <ul>
                    <li>
                        <a href="?">Tout les produits</a>
                    </li>

                    <li class="<?= $cat === 'alimentation' ? 'active' : '' ?>">
                        <a href="?cat=alimentation">Alimentation & Boissons</a>
                    </li>

                    <li class="<?= $cat === 'artisanat' ? 'active' : '' ?>">
                        <a href="?cat=artisanat">Artisanat & Décoration</a>
                    </li>

                    <li class="<?= $cat === 'mode' ? 'active' : '' ?>">
                        <a href="?cat=mode">Mode & Accessoires</a>
                    </li>

                    <li class="<?= $cat === 'culture' ? 'active' : '' ?>">
                        <a href="?cat=culture">Produits Culturels</a>
                    </li>

                    <li class="<?= $cat === 'derives' ? 'active' : '' ?>">
                        <a href="?cat=derives">Produits Dérivés</a>
                    </li>

                    
                </ul>
            </aside>

        <div class="catalogue-content">
                <div class="filter-controls-container">
                    <div class="filter-row">
                        <span>Prix :</span>
                        <input type="number" id="prix-min" placeholder="Min" min="0">
                        <span>-</span>
                        <input type="number" id="prix-max" placeholder="Max" min="0">
                        <button class="clear-btn" onclick="clearInput(['prix-min', 'prix-max'])">Supprimer</button>
                    </div>

                    <div class="filter-row">
                        <span>Notes :</span>
                        <input type="number" id="note-min" placeholder="Min" min="0" max="5" step="0.5">
                        <span>-</span>
                        <input type="number" id="note-max" placeholder="Max" min="0" max="5" step="0.5">
                        <button class="clear-btn" onclick="clearInput(['note-min', 'note-max'])">Supprimer</button>
                    </div>

                    <div class="filter-row">
                        <span>Trier par :</span>
                        <select id="tri">
                            <option value="ID">Pertinence</option>
                            <option value="PRIX_ASC">Prix croissant</option>
                            <option value="PRIX_DESC">Prix décroissant</option>
                            <option value="NOTE">Meilleures notes</option>
                            <option value="POPULAIRE">Nombre d'avis</option>
                        </select>
                        <button class="clear-btn" onclick="document.getElementById('tri').value='ID'; chargerCatalogue();">Supprimer</button>
                    </div>

                    <button id="btn-tout-effacer" onclick="toutEffacer()">Tout effacer </button>
                </div>

                <div class="produits-grid">
                    </div>
            </div>
        </div>
        
    </main>

    <?php include "footer.php" ?>
    <script src="filtres.js"></script>
</body>
</html>