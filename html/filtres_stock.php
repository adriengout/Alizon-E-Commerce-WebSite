<?php
session_start();
require_once("loginBdd.php");

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    $idVendeur = $_SESSION['id_compte'];
    // Récupération des paramètres de filtre
    $recherche = $_GET['recherche'] ?? ''; 
    $tri = $_GET['tri'] ?? '';
    $pMin = $_GET['pMin'] ?? '';
    $pMax = $_GET['pMax'] ?? '';
    $qMin = $_GET['qMin'] ?? ''; 

    $sql = "
        SELECT p.id_produit, p.nom_produit, p.prix_ht, p.description_prod,
               s.quantite_dispo, s.seuil_alerte,
               i.nom_fichier, i.chemin, i.extension
        FROM sae._produit p
        JOIN sae._stock s ON p.id_produit = s.id_produit
        LEFT JOIN sae._image i ON p.id_image = i.id_image
        WHERE p.id_vendeur = :idVendeur
    ";
    // Préparation des paramètres
    $params = ['idVendeur' => $idVendeur];

    if (!empty($recherche)) {
    $sql .= " AND p.nom_produit ILIKE :search"; 
    $params['search'] = '%' . $recherche . '%';
}

    if($pMin !== '' && $pMax !== '' && (float)$pMin > (float)$pMax) { // Met la valeur du pMin dans pMax si pMin est plus grand que pMax
        $temp = $pMin;
        $pMin = $pMax;
        $pMax = $temp;
    }

    if ($pMin !== '') { // Filtre prix
        $sql .= " AND p.prix_ht >= :pMin";
        $params['pMin'] = $pMin;
    }

    if ($pMax !== '') { // Filtre prix
        $sql .= " AND p.prix_ht <= :pMax";
        $params['pMax'] = $pMax;
    }

    if ($qMin !== '') { // Filtre stock
        $sql .= " AND s.quantite_dispo >= :qMin";
        $params['qMin'] = $qMin;
    }

    switch ($tri) { // Tri des résultats
        case 'prix_asc': $sql .= " ORDER BY p.prix_ht ASC"; break;
        case 'prix_desc': $sql .= " ORDER BY p.prix_ht DESC"; break;
        case 'stock': $sql .= " ORDER BY s.quantite_dispo ASC"; break;
        default: $sql .= " ORDER BY p.nom_produit ASC"; break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($produits);

} catch (Exception $e) {
    echo json_encode(['erreur' => $e->getMessage()]);
}