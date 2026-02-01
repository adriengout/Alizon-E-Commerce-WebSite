<?php
session_start();
require_once("loginBdd.php");
$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('SELECT * FROM sae._compte WHERE mail = :email');
$stmt->execute(['email' => $_SESSION['email']]);

$compte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compte || md5($_POST['password']) !== $compte['mot_de_passe']) {
    header("Location: connexion.php?error=1");
    exit;
}

$_SESSION['id_compte'] = $compte['id_compte'];
$_SESSION['login'] = $compte['login'];

$id = $compte['id_compte'];

$stmt = $pdo->prepare('SELECT EXISTS (
                        SELECT 1 FROM sae._vendeur WHERE id_vendeur = :id_compte
                        ) AS is_vendeur;');
$stmt->execute(['id_compte' => $id]);
$estVendeur = (bool) $stmt->fetchColumn();

$_SESSION['is_vendeur'] = $estVendeur;

if (isset($_SESSION['id_compte']) && isset($_SESSION['tmp_panier']) && !$estVendeur) {
    $id_client = $_SESSION['id_compte'];

    $stmt = $pdo->prepare("SELECT num_commande FROM sae._commande WHERE id_client = :id_client AND statut_commande = 'En attente de paiement'");
    $stmt->execute(['id_client' => $id_client]);
    $num_commande = $stmt->fetchColumn();

    if (!$num_commande) {
        $stmt = $pdo->prepare("INSERT INTO sae._commande (frais_livraison, montant_total_ht, statut_commande, date_commande, total_nb_prod, montant_total_ttc, id_client)
                                  VALUES (0.00, 0.00, 'En attente de paiement', CURRENT_DATE, 0, 0.00, :id_client)
                                  RETURNING num_commande");
        $stmt->execute(['id_client' => $id_client]);
        $num_commande = $stmt->fetchColumn();
    }

    foreach ($_SESSION['tmp_panier'] as $id_produit => $produit) {
        $quantite = $produit['qtt_panier'];
        $prix_ht = $produit['prix_ht'];
        $total_ht_ligne = round($quantite * $prix_ht, 2);
        $total_ttc_ligne = round($total_ht_ligne * 1.20, 2);

        $stmt = $pdo->prepare("SELECT quantite_prod FROM sae._ligneCommande WHERE num_commande = :num_commande AND id_produit = :id_produit");
        $stmt->execute(['num_commande' => $num_commande, 'id_produit' => $id_produit]);
        $quantite_existante = $stmt->fetchColumn();

        if ($quantite_existante !== false) {
            $nouvelle_quantite = $quantite_existante + $quantite;
            $nouveau_total_ht = round($nouvelle_quantite * $prix_ht, 2);
            $nouveau_total_ttc = round($nouveau_total_ht * 1.20, 2);

            $stmt = $pdo->prepare("UPDATE sae._ligneCommande
                                    SET quantite_prod = :quantite,
                                        total_ligne_commande_ht = :total_ht,
                                        total_ligne_commande_ttc = :total_ttc,
                                        total_ttc = :total_ttc
                                    WHERE num_commande = :num_commande AND id_produit = :id_produit");
            $stmt->execute([
                'quantite' => $nouvelle_quantite,
                'total_ht' => $nouveau_total_ht,
                'total_ttc' => $nouveau_total_ttc,
                'num_commande' => $num_commande,
                'id_produit' => $id_produit
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO sae._ligneCommande (id_produit, num_commande, quantite_prod, total_ttc, total_ligne_commande_ht, total_ligne_commande_ttc)
                                    VALUES (:id_produit, :num_commande, :quantite, :total_ttc, :total_ht, :total_ttc)");
            $stmt->execute([
                'id_produit' => $id_produit,
                'num_commande' => $num_commande,
                'quantite' => $quantite,
                'total_ttc' => $total_ttc_ligne,
                'total_ht' => $total_ht_ligne
            ]);
        }
    }

    $stmt = $pdo->prepare("UPDATE sae._commande
                            SET montant_total_ht = (SELECT COALESCE(SUM(total_ligne_commande_ht), 0) FROM sae._ligneCommande WHERE num_commande = :num_commande),
                                montant_total_ttc = (SELECT COALESCE(SUM(total_ligne_commande_ttc), 0) FROM sae._ligneCommande WHERE num_commande = :num_commande),
                                total_nb_prod = (SELECT COALESCE(SUM(quantite_prod), 0) FROM sae._ligneCommande WHERE num_commande = :num_commande)
                            WHERE num_commande = :num_commande");
    $stmt->execute(['num_commande' => $num_commande]);

    unset($_SESSION['tmp_panier']);
}

if ($estVendeur) {
    header("Location: accueilVendeur.php");
    exit;
} else {
    header("Location: index.php");
    exit;
}
