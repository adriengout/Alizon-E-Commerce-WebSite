<?php
session_start();
include('loginBdd.php');

require_once 'verif.php';
$id = $_SESSION['id_compte'];

if (isset($_POST['deconnexion']) && $_POST['deconnexion'] === 'deco') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

if (estClient($dbh, $id)) {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['send'])) {
        $toutEstValide = true;

        if (!validerEmail($_POST['mail'])['valide'] ||
            !validerLogin($_POST['login'])['valide'] ||
            !validerDateNaissance($_POST['date_naissance'])['valide'] ||
            !validerNomPrenom($_POST['nom'])['valide'] ||
            !validerNomPrenom($_POST['prenom'])['valide'] ||
            !validerTelephone($_POST['tel'])['valide']) {
            $toutEstValide = false;
        }

        if ($toutEstValide) {
            $_SESSION['data'] = array_merge($_POST);
            header("Location: modifProfilBdd.php");
            exit();
        } else {
            header("Location: modifProfil.php?modif=1");
            exit();
        }
    }

    try {
        $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $dbh->prepare('SELECT * FROM sae._client 
    inner join sae._compte on _client.id_client = _compte.id_compte 
    inner join sae._image on _compte.id_image = _image.id_image
    WHERE sae._client.id_client = :id');
        $stmt->execute([':id' => $id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $info) {
            $nom = $info['nom'];
            $prenom = $info['prenom'];
            $dateNaissance = $info['date_naissance'];
            $identifiant = $info['id_client'];
            $login = $info['login'];
            $mdp = $info['mot_de_passe'];
            $mail = $info['mail'];
            $tel = $info['tel'];
            $nomFichierImgProfil = $info['nom_fichier'];
            $cheminFichierImgProfil = $info['chemin'];
            $extImgProfil = $info['extension'];
            $altImgProfil = $info['alt'];
        }


        $stmt_commandes_lignes = $dbh->prepare('
        SELECT 
            c.*, 
            lc.id_produit, lc.quantite_prod, lc.total_ligne_commande_ttc,
            p.nom_produit,
            v.id_vendeur, v.nom_entreprise,
            i.chemin AS produit_chemin,
            i.nom_fichier AS produit_nom_fichier,
            i.alt AS produit_alt,
            i.extension
        FROM sae._commande c
        INNER JOIN sae._ligneCommande lc ON c.num_commande = lc.num_commande
        INNER JOIN sae._produit p ON lc.id_produit = p.id_produit
        INNER JOIN sae._vendeur v ON p.id_vendeur = v.id_vendeur
        INNER JOIN sae._image i ON p.id_image = i.id_image
        WHERE c.id_client = :id
        ORDER BY c.num_commande DESC, c.num_commande, p.nom_produit
        ');
        $stmt_commandes_lignes->execute([':id' => $id]);
        $resultats_plats = $stmt_commandes_lignes->fetchAll(PDO::FETCH_ASSOC);

        $toutes_les_commandes = [];

        foreach ($resultats_plats as $ligne_resultat) {
            $num_commande = $ligne_resultat['num_commande'];

            if (!isset($toutes_les_commandes[$num_commande])) {
                $toutes_les_commandes[$num_commande] = [
                    'commande_info' => [
                        'bordereau' => $ligne_resultat['bordereau'],
                        'num_commande' => $num_commande,
                        'frais_livraison' => $ligne_resultat['frais_livraison'],
                        'montant_total_ttc' => $ligne_resultat['montant_total_ttc'],
                        'statut_commande' => $ligne_resultat['statut_commande'],
                        'date_commande' => $ligne_resultat['date_commande'],
                        'total_nb_prod' => $ligne_resultat['total_nb_prod'],
                    ],
                    'lignes' => [],
                    'repartition_vendeur' => [] // Nouveau tableau structuré
                ];
            }

            $toutes_les_commandes[$num_commande]['lignes'][] = [
                'quantite_prod' => $ligne_resultat['quantite_prod'],
                'total_ligne_commande_ttc' => $ligne_resultat['total_ligne_commande_ttc'],
                'nom_produit' => $ligne_resultat['nom_produit'],
                'nom_entreprise' => $ligne_resultat['nom_entreprise'], // Ajout du nom vendeur ici aussi
                'produit_chemin' => $ligne_resultat['produit_chemin'],
                'produit_nom_fichier' => $ligne_resultat['produit_nom_fichier'],
                'produit_alt' => $ligne_resultat['produit_alt'],
                'extImage' => $ligne_resultat['extension']
            ];

            // LOGIQUE DE REGROUPEMENT POUR LES FACTURES
            $idVendeur = $ligne_resultat['id_vendeur'];

            if (!isset($toutes_les_commandes[$num_commande]['repartition_vendeur'][$idVendeur])) {
                $toutes_les_commandes[$num_commande]['repartition_vendeur'][$idVendeur] = [
                    'nom_entreprise' => $ligne_resultat['nom_entreprise'],
                    'liste_produits' => []
                ];
            }
            // On ajoute le nom du produit à ce vendeur
            $toutes_les_commandes[$num_commande]['repartition_vendeur'][$idVendeur]['liste_produits'][] = $ligne_resultat['nom_produit'];
        }
        

        $toutes_les_commandes = array_values($toutes_les_commandes);
        $derniere_commande = $toutes_les_commandes[0] ?? null;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
?>

    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Alizon - Mon Compte</title>
        <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
        <link rel="stylesheet" href="modifProfilStyle.css">
        <link rel="stylesheet" href="footer.css">
        <link rel="stylesheet" href="header.css">
    </head>

    <body>
        <?php include "header.php" ?>
        
        <main style="flex: 1;">

        <section class="top-section">
            <article class="profile-card">
                <header class="profile-header">
                    <figure class="profile-avatar">
                        <img src="<?php echo $cheminFichierImgProfil . $nomFichierImgProfil . $extImgProfil ?>">
                    </figure>
                    <aside class="profile-info">
                        <p class="profile-greeting">Bonjour, <?php echo $prenom ?></p>
                        <address class="profile-email-box">
                            <p class="profile-email-label">Votre adresse mail est :</p>
                            <p class="profile-email"><?php echo $mail ?></p>
                        </address>
                    </aside>
                </header>
                <p class="profile-name"><?php echo $prenom . " " . $nom ?></p>
                <button onclick="window.location.href='modifProfil.php'" class="btn-modify-info">Modifier information</button>
            </article>

            <article class="last-order-card">
                <header class="last-order-header">
                    <h2 class="last-order-title">Dernière commande (N° <?php echo $derniere_commande['commande_info']['num_commande'] ?? 'N/A' ?>)</h2>
                    <aside class="last-order-total">
                        <?php if ($derniere_commande): ?>
                            <p class="total-label">Total : <?php echo number_format($derniere_commande['commande_info']['montant_total_ttc'], 2, ',', ' ') ?> €</p>
                            <p class="article-count"><?php echo $derniere_commande['commande_info']['total_nb_prod'] ?> articles</p>
                        <?php else: ?>
                            <p class="total-label">Aucune commande</p>
                            <p class="article-count">0 articles</p>
                        <?php endif; ?>
                    </aside>
                </header>

                <section class="order-products">
                    <?php
                    $lignes_derniere_commande = $derniere_commande['lignes'] ?? [];
                    $compteur_produit = 0;
                    ?>
                    <?php if (count($lignes_derniere_commande) > 0): ?>
                        <?php foreach ($lignes_derniere_commande as $ligne): ?>
                            <?php if ($compteur_produit >= 3) { break; } ?>
                            <article class="product-item">
                                <figure class="product-image">
                                    <img src="<?php echo $ligne['produit_chemin'] . $ligne['produit_nom_fichier'] . $ligne['extImage'] ?>"
                                        alt="<?php echo $ligne['produit_alt']; ?>">
                                </figure>
                                <p class="product-name">
                                    <?php echo $ligne['nom_produit'] ?> (x<?php echo $ligne['quantite_prod'] ?>)
                                    <span class="product-price">
                                        <?php echo number_format($ligne['total_ligne_commande_ttc'], 2, ',', ' ') ?> €
                                    </span>
                                </p>
                            </article>
                            <?php $compteur_produit++; ?>
                        <?php endforeach; ?>
                        <?php if (count($lignes_derniere_commande) > 3): ?>
                            <p class="more-items-info">... et <?php echo count($lignes_derniere_commande) - 3 ?> autres articles. Voir les détails dans l'historique.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Détails des articles non disponibles.</p>
                    <?php endif; ?>
                </section>
            </article>
        </section>

        
        <?php if (!isset($_GET['command'])) { ?>
            <section class="content-section">
                <nav class="sidebar-menu" aria-label="Menu du compte">
                    <button class="menu-item active" onclick="window.location.href='modifProfil.php';">Mes infos</button>
                    <button class="menu-item" onclick="window.location.href='modifProfil.php?command=1';">Mes commandes</button>
                    <button class="menu-item">Mes retours</button>
                    <button class="menu-item">Mes adresses</button>
                    <button class="menu-item">Paiement</button>
                    <button class="menu-item">Sécurité</button>
                </nav>

                <article class="main-content">
                    <section class="details-section">
                        <aside class="avatar-section">
                            <figure class="large-avatar">
                                <img src="<?php echo $cheminFichierImgProfil . $nomFichierImgProfil . $extImgProfil ?>">
                            </figure>

                            <form action="upload.php" method="post" enctype="multipart/form-data" id="photoForm">
                                <label for="file" class="btn-change-photo">
                                    Changer la photo de profil
                                </label>
                                <input type="file" id="file" name="file" accept="image/*" onchange="document.getElementById('photoForm').submit()" style="display: none;">
                            </form>
                        </aside>

                        <?php if (isset($_GET['modif'])) { ?>
                            <form class="info-form" action="modifProfil.php?send=1" method="post" enctype="multipart/form-data">
                                <fieldset class="form-row">
                                    <legend class="sr-only">Identification</legend>
                                    <label class="form-field">
                                        <span class="form-label">Nom :</span>
                                        <input type="text" id="nom" name="nom" class="form-input" value="<?php echo htmlspecialchars($nom) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Informations personnelles</legend>
                                    <label class="form-field">
                                        <span class="form-label">Prénom :</span>
                                        <input type="text" id="prenom" name="prenom" class="form-input" value="<?php echo htmlspecialchars($prenom) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Date et connexion</legend>
                                    <label class="form-field">
                                        <span class="form-label">Né(e) le :</span>
                                        <input type="date" id="date_naissance" name="date_naissance" class="form-input" value="<?php echo htmlspecialchars($dateNaissance) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Identifiants de connexion</legend>
                                    <label class="form-field">
                                        <span class="form-label">Login :</span>
                                        <input type="text" id="login" name="login" class="form-input" value="<?php echo htmlspecialchars($login) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Contact</legend>
                                    <label class="form-field">
                                        <span class="form-label">Mail :</span>
                                        <input type="email" id="mail" name="mail" class="form-input" value="<?php echo htmlspecialchars($mail) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Téléphone</legend>
                                    <label class="form-field">
                                        <span class="form-label">Téléphone :</span>
                                        <input type="tel" id="tel" name="tel" class="form-input" value="<?php echo htmlspecialchars($tel) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <nav class="action-buttons">
                                    <button type="button" class="btn-cancel" onclick="window.location.href='modifProfil.php';"> Annuler</button>
                                    <button type="submit" class="btn-modify">Valider</button>
                                </nav>
                            </form>
                        <?php } else { ?>
                            <form class="info-form" action="modifProfil.php?modif=1" method="post">
                                <fieldset class="form-row">
                                    <legend class="sr-only">Identification</legend>
                                    <label class="form-field">
                                        <span class="form-label">Nom :</span>
                                        <input type="text" class="form-input" value="<?php echo $nom ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Informations personnelles</legend>
                                    <label class="form-field">
                                        <span class="form-label">Prénom :</span>
                                        <input type="text" class="form-input" value="<?php echo $prenom ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Date et connexion</legend>
                                    <label class="form-field">
                                        <span class="form-label">Né(e) le :</span>
                                        <input type="date" class="form-input" value="<?php echo $dateNaissance ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Identifiants de connexion</legend>
                                    <label class="form-field">
                                        <span class="form-label">Login :</span>
                                        <input type="text" class="form-input" value="<?php echo $login ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Contact</legend>
                                    <label class="form-field">
                                        <span class="form-label">Mail :</span>
                                        <input type="email" class="form-input" value="<?php echo $mail ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Téléphone</legend>
                                    <label class="form-field">
                                        <span class="form-label">Téléphone :</span>
                                        <input type="tel" class="form-input" value="<?php echo $tel ?>" disabled>
                                    </label>
                                </fieldset>

                                <nav class="action-buttons">
                                    <button type="submit" class="btn-modify">Modifier</button>
                                </nav>
                            </form>
                        <?php } ?>
                    </section>
                </article>
            </section>

            <section class="bottom-section">
                <button class="change-password-card" onclick="window.location.href='changementMdp.php';" aria-label="Changer le mot de passe">
                    Changer mot de passe
                </button>
                <form method="post">
                    <button type='submit' name="deconnexion" value="deco" class="logout-card" aria-label="Se déconnecter">
                        Déconnexion
                    </button>
                </form>
            </section>
            
        <?php } elseif (isset($_GET['command'])) { ?>
            <section class="content-section">
                <nav class="sidebar-menu" aria-label="Menu du compte">
                    <button class="menu-item" onclick="window.location.href='modifProfil.php';">Mes infos</button>
                    <button class="menu-item active" onclick="window.location.href='modifProfil.php?command=1';">Mes commandes</button>
                    <button class="menu-item">Mes retours</button>
                    <button class="menu-item">Mes adresses</button>
                    <button class="menu-item">Paiement</button>
                    <button class="menu-item">Sécurité</button>
                </nav>

                <article class="main-content">
                    <section class="order-history-section">
                        <h2 class="history-title">Historique de vos commandes</h2>
                        <?php if (count($toutes_les_commandes) > 0): ?>
                            <?php foreach ($toutes_les_commandes as $commande): ?>
                                <article class="historical-order-item">
                                    <header class="order-item-header">
                                        <h3>Commande N° <?php echo $commande['commande_info']['num_commande'] ?></h3>
                                        <div class="order-meta-info">
                                            <p class="order-date">Date :
                                                <?php
                                                $dateObj = new DateTime($commande['commande_info']['date_commande']);
                                                echo $dateObj->format('d/m/Y');
                                                ?>
                                            </p>
                                            <p class="order-status">Statut :
                                                <strong class="<?php echo strtolower(str_replace(' ', '-', $commande['commande_info']['statut_commande'])) ?>">
                                                    <?php echo $commande['commande_info']['statut_commande'] ?>
                                                </strong>
                                            </p>
                                        </div>
                                    </header>

                                    <div class="order-summary-details">
                                        <aside class="total-summary">
                                            <p class="total-label-history">Total : <strong><?php echo number_format($commande['commande_info']['montant_total_ttc'], 2, ',', ' ') ?> €</strong></p>
                                            <p class="article-count-history"><?php echo $commande['commande_info']['total_nb_prod'] ?> articles</p>
                                            <?php if (isset($commande['commande_info']['bordereau'])){?>
                                                <a href="suivreColis.php?bord=<?= trim($commande['commande_info']['bordereau']) ?>">Suivre mon colis</a>
                                            <?php }?>
                                            <?php if ($commande['commande_info']['statut_commande'] != "En attente de paiement"){?> 
                                                <a href="facture.php?num_commande=<?php echo $commande['commande_info']['num_commande']; ?>" >
                                                    Télécharger la facture
                                                </a>
                                            <?php } ?>
                                        </aside>

                                        <details class="order-lines-details">
                                            <summary>Voir les articles (<?php echo count($commande['lignes']) ?>)</summary>
                                            <ul class="line-items-list">
                                                <?php foreach ($commande['lignes'] as $ligne): ?>
                                                    <li class="line-item">
                                                        <span class="product-qty">x<?php echo $ligne['quantite_prod'] ?></span>
                                                        <span class="product-name-list"><?php echo $ligne['nom_produit'] ?></span>
                                                        <span class="product-price-list"><?php echo number_format($ligne['total_ligne_commande_ttc'], 2, ',', ' ') ?> €</span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Vous n'avez passé aucune commande pour le moment.</p>
                        <?php endif; ?>
                    </section>
                </article>
            </section>
        <?php }
    } else {
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['send'])) {
            $toutEstValide = true;

            if (!validerEmail($_POST['mail'])['valide'] ||
                !validerLogin($_POST['login'])['valide'] ||
                !validerNomEntreprise($_POST['nom_entreprise'])['valide'] ||
                !validerSiret($_POST['siret'])['valide'] ||
                !validerTelephone($_POST['tel'])['valide']) {
                $toutEstValide = false;
            }

            if ($toutEstValide) {
                $_SESSION['data'] = array_merge($_POST);
                header("Location: modifProfilBdd.php?vendeur=1");
                exit();
            } else {
                header("Location: modifProfil.php?vendeur=1&modif=1");
                exit();
            }
        }

        try {
            $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $dbh->prepare('SELECT * FROM sae._vendeur 
                inner join sae._compte on _vendeur.id_vendeur = _compte.id_compte 
                inner join sae._image on _compte.id_image = _image.id_image
                WHERE sae._vendeur.id_vendeur = :id');
            $stmt->execute([':id' => $id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $info) {
                $nomEntreprise = $info['nom_entreprise'];
                $siret = $info['siret'];
                $descriptionVendeur = $info['description_vendeur'];
                $identifiant = $info['id_vendeur'];
                $login = $info['login'];
                $mdp = $info['mot_de_passe'];
                $mail = $info['mail'];
                $tel = $info['tel'];
                $nomFichierImgProfil = $info['nom_fichier'];
                $cheminFichierImgProfil = $info['chemin'];
                $extImgProfil = $info['extension'];
                $altImgProfil = $info['alt'];
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        } ?>

        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Alizon - Mon Compte Vendeur</title>
            <link rel="stylesheet" href="header.css">
            <link rel="stylesheet" href="footer.css">
            <link rel="stylesheet" href="modifProfilStyle.css">
        </head>

        <body>
            <?php include "header.php";?>

            <main style="flex: 1;">

            <section class="content-section">
                <article class="main-content">
                    <section class="details-section">
                        <aside class="avatar-section">
                            <figure class="large-avatar">
                                <img src="<?php echo $cheminFichierImgProfil . $nomFichierImgProfil . $extImgProfil ?>" alt="Photo de profil">
                            </figure>

                            <form action="upload.php" method="post" enctype="multipart/form-data" id="photoForm">
                                <label for="file" class="btn-change-photo">
                                    Changer la photo de profil
                                </label>
                                <input type="file" id="file" name="file" accept="image/*" onchange="document.getElementById('photoForm').submit()" style="display: none;">
                            </form>
                        </aside>

                        <?php if (isset($_GET['modif'])) { ?>
                            <form class="info-form" action="modifProfil.php?vendeur=1&send=1" method="post" enctype="multipart/form-data">
                                <fieldset class="form-row">
                                    <legend class="sr-only">Nom de l'entreprise</legend>
                                    <label class="form-field">
                                        <span class="form-label">Nom Entreprise :</span>
                                        <input type="text" id="nom_entreprise" name="nom_entreprise" class="form-input" value="<?php echo htmlspecialchars($nomEntreprise) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Numéro SIRET</legend>
                                    <label class="form-field">
                                        <span class="form-label">SIRET :</span>
                                        <input type="text" id="siret" name="siret" class="form-input" value="<?php echo htmlspecialchars($siret) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Description</legend>
                                    <label class="form-field">
                                        <span class="form-label">Description :</span>
                                        <textarea id="description_vendeur" name="description_vendeur" rows="4" maxlength="500" placeholder="Décrivez votre activité..."><?php echo htmlspecialchars($descriptionVendeur ?? ''); ?></textarea>
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Identifiant de connexion</legend>
                                    <label class="form-field">
                                        <span class="form-label">Login :</span>
                                        <input type="text" id="login" name="login" class="form-input" value="<?php echo htmlspecialchars($login) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Adresse email</legend>
                                    <label class="form-field">
                                        <span class="form-label">Mail :</span>
                                        <input type="email" id="mail" name="mail" class="form-input" value="<?php echo htmlspecialchars($mail) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Numéro de téléphone</legend>
                                    <label class="form-field">
                                        <span class="form-label">Téléphone :</span>
                                        <input type="tel" id="tel" name="tel" class="form-input" value="<?php echo htmlspecialchars($tel) ?>">
                                        <span class="erreur-message"></span>
                                    </label>
                                </fieldset>

                                <nav class="action-buttons">
                                    <button type="button" class="btn-cancel" onclick="window.location.href='modifProfil.php?vendeur=1';">Annuler</button>
                                    <button type="submit" class="btn-modify">Valider</button>
                                </nav>
                            </form>
                        <?php } else { ?>
                            <form class="info-form" action="modifProfil.php?vendeur=1&modif=1" method="post">
                                <fieldset class="form-row">
                                    <legend class="sr-only">Nom de l'entreprise</legend>
                                    <label class="form-field">
                                        <span class="form-label">Nom Entreprise :</span>
                                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($nomEntreprise) ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Numéro SIRET</legend>
                                    <label class="form-field">
                                        <span class="form-label">SIRET :</span>
                                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($siret) ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Description</legend>
                                    <label class="form-field">
                                        <span class="form-label">Description :</span>
                                        <textarea id="description_vendeur" name="description_vendeur" rows="4" maxlength="500" disabled><?php echo htmlspecialchars($descriptionVendeur ?? ''); ?></textarea>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Identifiant de connexion</legend>
                                    <label class="form-field">
                                        <span class="form-label">Login :</span>
                                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($login) ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Adresse email</legend>
                                    <label class="form-field">
                                        <span class="form-label">Mail :</span>
                                        <input type="email" class="form-input" value="<?php echo htmlspecialchars($mail) ?>" disabled>
                                    </label>
                                </fieldset>

                                <fieldset class="form-row">
                                    <legend class="sr-only">Numéro de téléphone</legend>
                                    <label class="form-field">
                                        <span class="form-label">Téléphone :</span>
                                        <input type="tel" class="form-input" value="<?php echo htmlspecialchars($tel) ?>" disabled>
                                    </label>
                                </fieldset>

                                <nav class="action-buttons">
                                    <button type="submit" class="btn-modify">Modifier</button>
                                </nav>
                            </form>
                        <?php } ?>
                    </section>
                </article>
            </section>

            <section class="bottom-section">
                <button class="change-password-card" onclick="window.location.href='changementMdp.php';" aria-label="Changer le mot de passe">
                    Changer mot de passe
                </button>
                <form method="post">
                    <button type="submit" name="deconnexion" value="deco" class="logout-card" aria-label="Se déconnecter">
                        Déconnexion
                    </button>
                </form>
            </section>
        <?php } ?>
        
        </main>

        <?php include "footer.php";?>
        <script src="validation.js"></script>
    </body>
</html>