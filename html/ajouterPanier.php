<?php
session_start();

require_once("loginBdd.php");
$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET search_path TO sae");


$id_produit = $_POST['id_produit'];
$qtt_produit = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;


$sql_details = "
        SELECT
            P.nom_produit, P.description_prod, P.prix_ht, P.dep_origine, P.ville_origine, P.pays_origine, P.nb_ventes,
            I.nom_fichier AS image_nom, I.chemin AS image_chemin, I.extension AS image_ext, I.alt AS image_alt,
            C.nom_categ as nom_categ,
            V.id_vendeur, V.nom_entreprise, V.description_vendeur,
            S.quantite_dispo,
            T.taux_tva,
            R.taux_remise,
            PR.nom_promotion,
            COALESCE(avg_a.moyenne, 0) AS moyenne,
            COALESCE(count_a.nb_avis, 0) AS nbAvis
        FROM
            sae._produit P
        JOIN sae._image I ON P.id_image = I.id_image
        JOIN sae._categorieProduit C ON P.id_categ = C.id_categ
        JOIN sae._vendeur V ON P.id_vendeur = V.id_vendeur
        LEFT JOIN sae._stock S ON P.id_produit = S.id_produit AND P.id_vendeur = V.id_vendeur
        LEFT JOIN sae._tva T ON P.id_produit = T.id_produit
        LEFT JOIN sae._remise R ON P.id_produit = R.id_produit
        LEFT JOIN sae._promotion PR ON P.id_produit = PR.id_produit
        LEFT JOIN (SELECT id_produit, AVG(note) AS moyenne FROM sae._avis GROUP BY id_produit) avg_a
            ON avg_a.id_produit = p.id_produit
        LEFT JOIN (SELECT id_produit, COUNT(*) AS nb_avis FROM sae._avis GROUP BY id_produit) count_a
            ON count_a.id_produit = p.id_produit
        WHERE
            P.id_produit = :id_produit;
    ";

    $stmt = $pdo->prepare($sql_details);
    $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
    $stmt->execute();
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$produit) die("Produit introuvable");

    $rawTva = !empty($produit['taux_tva']) ? floatval($produit['taux_tva']) : 20.0;
    $tauxTva = ($rawTva > 1) ? $rawTva / 100 : $rawTva; 


    $rawRemise = !empty($produit['taux_remise']) ? floatval($produit['taux_remise']) : 0;
    $tauxRemise = ($rawRemise > 1) ? $rawRemise / 100 : $rawRemise; 


    $prix_ht = floatval($produit['prix_ht']);
    $prix_ttc_base = $prix_ht * (1 + $tauxTva);
    $prix_ttc_final = $prix_ttc_base * (1 - $tauxRemise); 


// Récupérer le stock disponible
$stock_disponible = intval($produit['quantite_dispo'] ?? 0);
$stock_limite_atteinte = false;

if (isset($_SESSION['id_compte'])) {
    $id_client = $_SESSION['id_compte'];

    $stmt = $pdo->prepare("SELECT num_commande from _commande where id_client = :id_client and statut_commande = 'En attente de paiement'");
    $stmt->execute(['id_client' => $id_client]);
    $num_commande = $stmt->fetchColumn();

    if(!$num_commande){
        $stmt = $pdo->prepare("INSERT into _commande (frais_livraison, montant_total_ht, statut_commande, date_commande, total_nb_prod, montant_total_ttc, id_client)
                                values (0.00, 0.00, 'En attente de paiement', CURRENT_DATE, 0, 0.00, :id_client)
                                RETURNING num_commande");
        $stmt->execute(['id_client' => $id_client]);
        $num_commande = $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT quantite_prod FROM _ligneCommande WHERE num_commande = :num_commande AND id_produit = :id_produit");
    $stmt->execute([':num_commande' => $num_commande, ':id_produit' => $id_produit]);
    $quantite_existante = $stmt->fetchColumn();
    $produit_existe_dans_panier = ($quantite_existante !== false);

    $nouvelleQuantite = ($produit_existe_dans_panier ? intval($quantite_existante) : 0) + $qtt_produit;

    // Vérifier que la quantité ne dépasse pas le stock disponible
    if ($nouvelleQuantite > $stock_disponible) {
        $nouvelleQuantite = $stock_disponible;
        $stock_limite_atteinte = true;
    }

    $total_ttc_ligne = round($nouvelleQuantite * $prix_ttc_final, 2); 
    $total_ht_ligne = round($total_ttc_ligne / (1 + $tauxTva), 2);  

    if($produit_existe_dans_panier){
        $stmt = $pdo->prepare("
            UPDATE _ligneCommande
            SET quantite_prod = :nouvelle_quantite,
                total_ttc = :total_ttc_ligne,
                total_ligne_commande_ht = :total_ht_ligne,
                total_ligne_commande_ttc = :total_ttc_ligne
            WHERE num_commande = :num_commande AND id_produit = :id_produit
        ");
        $stmt->execute([
            'nouvelle_quantite' => $nouvelleQuantite,
            'total_ht_ligne' => $total_ht_ligne,
            'total_ttc_ligne' => $total_ttc_ligne,
            'num_commande' => $num_commande,
            'id_produit' => $id_produit
        ]);
    }else{
        $stmt = $pdo->prepare("
            INSERT INTO _ligneCommande (id_produit, num_commande, quantite_prod, total_ttc, total_ligne_commande_ht, total_ligne_commande_ttc)
            VALUES (:id_produit, :num_commande, :quantite, :total_ttc_ligne, :total_ht_ligne, :total_ttc_ligne)
        ");
        $stmt->execute([
                ':id_produit' => $id_produit,
                ':num_commande' => $num_commande,
                ':quantite' => $nouvelleQuantite,
                ':total_ttc_ligne' => $total_ttc_ligne,
                ':total_ht_ligne' => $total_ht_ligne
            ]);
    }

    $stmt_totaux = $pdo->prepare("
            UPDATE _commande
            SET montant_total_ht = (SELECT COALESCE(SUM(total_ligne_commande_ht), 0) FROM _ligneCommande WHERE num_commande = :num_commande),
                montant_total_ttc = (SELECT COALESCE(SUM(total_ligne_commande_ttc), 0) FROM _ligneCommande WHERE num_commande = :num_commande),
                total_nb_prod = (SELECT COALESCE(SUM(quantite_prod), 0) FROM _ligneCommande WHERE num_commande = :num_commande)
            WHERE num_commande = :num_commande
        ");
        $stmt_totaux->execute([':num_commande' => $num_commande]);
}else{
    if (!isset($_SESSION['tmp_panier']) || !is_array($_SESSION['tmp_panier'])) {
        $_SESSION['tmp_panier'] = [];
    }

    if (isset($_SESSION['tmp_panier'][$id_produit])) {
        $nouvelleQuantite = $_SESSION['tmp_panier'][$id_produit]['qtt_panier'] + $qtt_produit;

        // Vérifier que la quantité ne dépasse pas le stock disponible
        if ($nouvelleQuantite > $stock_disponible) {
            $nouvelleQuantite = $stock_disponible;
            $stock_limite_atteinte = true;
        }

        $_SESSION['tmp_panier'][$id_produit]['qtt_panier'] = $nouvelleQuantite;
    } else {
        // Vérifier que la quantité initiale ne dépasse pas le stock
        if ($qtt_produit > $stock_disponible) {
            $qtt_produit = $stock_disponible;
            $stock_limite_atteinte = true;
        }

        $_SESSION['tmp_panier'][$id_produit] = $produit;
        $_SESSION['tmp_panier'][$id_produit]['qtt_panier'] = $qtt_produit;
    }
}


// Définir le message approprié
if ($stock_limite_atteinte) {
    $_SESSION['toast_message'] = "Quantité limitée au stock disponible ($stock_disponible)";
    $_SESSION['toast_type'] = "warning";
} else {
    $_SESSION['toast_message'] = "Produit ajouté au panier";
    $_SESSION['toast_type'] = "ajoute";
}

$redirection = $_POST["redirect_url"];
header("Location: $redirection");
exit;
?>
