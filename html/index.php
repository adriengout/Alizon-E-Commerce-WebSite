<?php
session_start();

require_once("loginBdd.php");
$pdo = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);

$stmt = $pdo->prepare("SELECT * 
                       from 
                            sae._promotion promo 
                            join sae._produit prod on promo.id_produit = prod.id_produit
                            LEFT JOIN (SELECT id_produit, AVG(note) AS moyenne FROM sae._avis GROUP BY id_produit) avg_a
                            ON avg_a.id_produit = prod.id_produit
                            LEFT JOIN (SELECT id_produit, COUNT(*) AS nb_avis FROM sae._avis GROUP BY id_produit) count_a
                            ON count_a.id_produit = prod.id_produit
                            left join sae._remise reduc on reduc.id_produit = prod.id_produit
                            LEFT JOIN sae._tva tva ON prod.id_produit = tva.id_produit"
                    );



$stmt->execute();
$prodsEnPromo = $stmt->fetchAll(PDO::FETCH_ASSOC);


$cheminImageDefault = "media/produits/";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="accueilStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <title>Alizon</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <script src="accueilScript.js" async></script>
</head>
<body>
    <?php include 'header.php'; ?>
    <main>
        <section>
            <div class="carousel">
                <div class="carousel-slide">
                    <div class="slide">
                        <div class="text-content">
                            <h3>CONSOMMER LOCAL</h3>
                            <h2>Produits alimentaires variés !</h2>
                            <p>Profitez d’une gamme de produits alimentaires variés. Des produits locaux pour soutenir nos marchands.</p>
                        </div>
                        <img src="media/accueilClient/composition-d-elements-marins-avec-fond-a-gauche 1 (1).png" alt="Produits alimentaires bretons">
                    </div>
                    
                    <div class="slide">
                        <img src="media/accueilClient/astuces-conseils-caramel-beurre-sale-1024x683 2.png" alt="Décorations bretonnes">
                        <div class="text-content">
                            <h3>ARTISANAT BRETON</h3>
                            <h2>Décorations bretonnes uniques !</h2>
                            <p>Découvrez une sélection de décorations bretonnes alliant authenticité, élégance et esprit marin pour apporter chaleur et caractère à votre intérieur.</p>
                        </div>
                    </div>
                </div>

                <button class="arrow prev">&#10094;</button>
                <button class="arrow next">&#10095;</button>
            </div>
        </section>

        <?php if ($prodsEnPromo){ ?>
            <section class="produits-une">
                <h2>Produits à la une</h2>
                <div class="promotions">
                    <?php 
                    $topProds = array_slice($prodsEnPromo, 0, 4);
                    foreach($topProds as $produit){                      
                        $prixHT = floatval($produit['prix_ht']);
                        
                        $rawTva = !empty($produit['taux_tva']) ? floatval($produit['taux_tva']) : 20.0;
                        $tva = ($rawTva > 1) ? $rawTva / 100 : $rawTva; 

                        $prixTTCBase = $prixHT * (1 + $tva);

                        $rawRemise = !empty($produit['taux_remise']) ? floatval($produit['taux_remise']) : 0;
                        $tauxRemise = ($rawRemise > 1) ? $rawRemise / 100 : $rawRemise; 

                        $montantReduction = $prixTTCBase * $tauxRemise;
                        $prixFinal = $prixTTCBase - $montantReduction;
                        
                        $pourcentageAffiche = round($tauxRemise * 100);
                    ?>
                        <div class="card-promotion">
                            <a href="descriptionProduitClient.php?id_produit=<?php echo $produit['id_produit']; ?>" class="image-link">
                                <div class="zone-image">
                                    <?php if ($produit['id_remise']){ ?>
                                        <span class="badge-promo">-<?php echo $pourcentageAffiche; ?>%</span>
                                    <?php } ?>
                                    <img class="img-promo" src="<?php echo $cheminImageDefault . $produit['banniere_promo'] ?>" alt="banniere promo">
                                </div>
                            </a>

                            <h3><?php echo htmlspecialchars($produit['nom_produit']) ?></h3>
                            
                            <div class="avis">
                                <div class="etoiles">
                                    <?php
                                    if ($produit['moyenne'] == NULL){
                                        $etoiles_pleines = 0;
                                    }else{
                                        $etoiles_pleines = floor($produit['moyenne']);
                                    }
                                    for ($i = 1; $i <= 5; $i++) {
                                        $starImg = ($i <= $etoiles_pleines) ? "etoile_pleine.png" : "etoile_vide.png";
                                        echo '<img src="media/universel/'.$starImg.'" alt="*">';
                                    }
                                    ?>
                                </div>
                                <span class="nb-avis">(<?php if($produit['nb_avis'] == NULL){echo 0;}else{echo $produit['nb_avis'];} ?>)</span>
                            </div>

                            <div class="prix-container">
                                <?php if ($produit['id_remise']){ ?>
                                    <p class="prix-ancien"><s><?php echo number_format($prixTTCBase, 2); ?> €</s></p>
                                    <p class="prix-actuel" style="color: #e74c3c; font-weight: bold;"><?php echo number_format($prixFinal, 2); ?> €</p>
                                <?php } else { ?>
                                    <p class="prix-actuel" style="font-weight: bold;"><?php echo number_format($prixHT, 2); ?> €</p>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="actions-une">
                    <a href="catalogue.php?promo=1" class="btn-voir-plus">Voir toutes les promotions</a>
                </div>
            </section>
        <?php } ?>
        
        

        <section class="prod-alimentation">
            <p>Gourmandises Bretonnes</p>
            <div class="produits">
                <a href="catalogue.php"><img src="media/accueilClient/article1 (1).png" alt="article1"></a>
                <a href="catalogue.php"><img src="media/accueilClient/article2.png" alt="article2"></a>
                <a href="catalogue.php"><img src="media/accueilClient/article3.png" alt="article3"></a>
                <a href="catalogue.php"><img src="media/accueilClient/article4.png" alt="article4"></a>
            </div>
            <a class="bouton" href="catalogue.php">Voir les produits</a>
        </section>

        <section class="prod-decoration">
            <p>Produits & décorations <br>issus d’artisants locaux !</p>
            <a class="bouton" href="catalogue.php?cat=artisanat">Voir les produits</a>
        </section>

        <section class="signature">
            <div class="titre">
                <img src="media/accueilClient/logo_alizon_petit.png" alt="">
                <h2>La Signature Alizon</h2>
            </div>
            <div class="signature-grid">
                <div class="signature-item">
                    <img src="media/accueilClient/phare_image.png" alt="phare">
                    <h3>L’authenticité Bretonne</h3>
                    <p>
                        Retrouvez les incontournables de la gastronomie bretonne : biscuits artisanaux,
                        caramels au beurre salé, cidres, bières locales et spécialités marines.
                        Chaque produit est choisi pour vous offrir un véritable voyage au cœur de la Bretagne.
                    </p>
                </div>
                <div class="signature-item">
                    <img src="media/accueilClient/moulin_image.png" alt="moulin">
                    <h3>Un savoir-faire préservé</h3>
                    <p>
                        Nous collaborons avec des artisans passionnés qui perpétuent des recettes familiales
                        et traditionnelles. Leur exigence et leur attachement au terroir garantissent des
                        saveurs uniques et une qualité irréprochable.
                    </p>
                </div>
                <div class="signature-item">
                    <img src="media/accueilClient/feuille_image.png" alt="feuille">
                    <h3>Des pratiques responsables</h3>
                    <p>
                        Nos partenaires privilégient les circuits courts, des matières premières locales
                        et des emballages respectueux de l’environnement. Une manière de soutenir une
                        consommation durable, tout en valorisant le patrimoine breton.
                    </p>
                </div>
                <div class="signature-item">
                    <img src="media/accueilClient/cage_image.png" alt="cage">
                    <h3>La liberté de choisir</h3>
                    <p>
                        Que ce soit pour offrir ou pour se faire plaisir, composez votre panier selon vos envies.
                        Panier garni, coffrets cadeaux ou gourmandises à l’unité : à vous de créer l’expérience
                        bretonne qui vous ressemble.
                    </p>
                </div>
            </div>
        </section>

        <section class="prod-culturels">
            <p>Des miliers de produits culturels à porté de click !</p>
            <a class="bouton" href="catalogue.php?cat=culture">Explorer la culture Bretonne</a>
        </section>
    </main>
    <?php include "footer.php" ?>
</body>
</html>
