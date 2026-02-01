<?php
session_start();
require_once("loginBdd.php");

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $quantitesPanier = [];

    if (isset($_SESSION['id_compte'])) {
        $stmtPanier = $pdo->prepare("
            SELECT lc.id_produit, lc.quantite_prod
            FROM sae._ligneCommande lc
            JOIN sae._commande c ON lc.num_commande = c.num_commande
            WHERE c.id_client = :id_client AND c.statut_commande = 'En attente de paiement'
        ");
        $stmtPanier->execute(['id_client' => $_SESSION['id_compte']]);
        while ($row = $stmtPanier->fetch(PDO::FETCH_ASSOC)) {
            $quantitesPanier[$row['id_produit']] = intval($row['quantite_prod']);
        }
    } elseif (isset($_SESSION['tmp_panier'])) {
        foreach ($_SESSION['tmp_panier'] as $id_produit => $produit) {
            $quantitesPanier[$id_produit] = intval($produit['qtt_panier']);
        }
    }

    $catId = $_GET['catId'] ?? null;
    $recherche = $_GET['recherche'] ?? '';
    $tri_choisi = $_GET['tri'] ?? 'ID';

    $pMin = $_GET['pMin'] ?? '';
    $pMax = $_GET['pMax'] ?? '';
    $nMin = $_GET['nMin'] ?? '';
    $nMax = $_GET['nMax'] ?? '';

    if ($pMin !== '' && $pMax !== '' && (float)$pMin > (float)$pMax) {
        $temp = $pMin;
        $pMin = $pMax;
        $pMax = $temp;
    }

    if ($nMin !== '' && $nMax !== '' && (float)$nMin > (float)$nMax) {
        $temp = $nMin;
        $nMin = $nMax;
        $nMax = $temp;
    }

    $onlyPromo = isset($_GET['promo']) && $_GET['promo'] == '1';

    $formulePrixReel = "(
        p.prix_ht
        * (1 + COALESCE(CASE WHEN t.taux_tva > 1 THEN t.taux_tva/100.0 ELSE t.taux_tva END, 0))
        * (1 - CASE WHEN pr.nom_promotion IS NOT NULL THEN COALESCE(r.taux_remise, 0) ELSE 0 END)
    )";

    $sql = "
        SELECT
            p.id_produit, p.nom_produit, p.description_prod, p.prix_ht,
            i.chemin, i.nom_fichier, i.extension, s.quantite_dispo,
            r.taux_remise,
            pr.nom_promotion,
            t.taux_tva,
            COALESCE(avg_a.moyenne, 0) AS moyenne,
            COALESCE(nb_a.nb_avis, 0) AS \"nbAvis\",
            $formulePrixReel as prix_reel_calcule
        FROM sae._produit p
        LEFT JOIN sae._stock s ON p.id_produit = s.id_produit
        LEFT JOIN sae._image i ON p.id_image = i.id_image
        LEFT JOIN sae._tva t ON p.id_produit = t.id_produit
        LEFT JOIN sae._promotion pr ON p.id_produit = pr.id_produit
            AND CURRENT_DATE BETWEEN pr.date_debut AND pr.date_fin
        LEFT JOIN sae._remise r ON p.id_produit = r.id_produit

        LEFT JOIN (
            SELECT id_produit, AVG(note) as moyenne
            FROM sae._avis GROUP BY id_produit
        ) avg_a ON p.id_produit = avg_a.id_produit
        LEFT JOIN (
            SELECT id_produit, COUNT(id_avis) as nb_avis
            FROM sae._avis GROUP BY id_produit
        ) nb_a ON p.id_produit = nb_a.id_produit

        WHERE 1=1
    ";

    $params = [];

    if ($onlyPromo) {
        $sql .= " AND pr.nom_promotion IS NOT NULL";
    }

    if (!empty($catId)) {
        $sql .= ' AND p.id_categ = :idcat';
        $params['idcat'] = $catId;
    }

    if (!empty($recherche)) {
        $sql .= ' AND (p.nom_produit ILIKE :search OR p.description_prod ILIKE :search)';
        $params['search'] = '%' . $recherche . '%';
    }

    if ($pMin !== '') {
        $sql .= " AND $formulePrixReel >= :pMin";
        $params['pMin'] = floatval($pMin);
    }
    if ($pMax !== '') {
        $sql .= " AND $formulePrixReel <= :pMax";
        $params['pMax'] = floatval($pMax);
    }

    if ($nMin !== '') {
        $sql .= ' AND COALESCE(avg_a.moyenne, 0) >= :nMin';
        $params['nMin'] = $nMin;
    }
    if ($nMax !== '') {
        $sql .= ' AND COALESCE(avg_a.moyenne, 0) <= :nMax';
        $params['nMax'] = $nMax;
    }

    $order_by = "p.id_produit";
    switch ($tri_choisi) {
        case 'PRIX_ASC': $order_by = "prix_reel_calcule ASC"; break;
        case 'PRIX_DESC': $order_by = "prix_reel_calcule DESC"; break;
        case 'NOTE': $order_by = "moyenne DESC"; break;
        case 'POPULAIRE': $order_by = '"nbAvis" DESC'; break;
        default: $order_by = "p.id_produit";
    }
    $sql .= " ORDER BY " . $order_by;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($produits as &$produit) {
        $idProd = $produit['id_produit'];
        $produit['quantite_panier'] = isset($quantitesPanier[$idProd]) ? $quantitesPanier[$idProd] : 0;
    }
    unset($produit);

    header('Content-Type: application/json');
    echo json_encode($produits);

} catch (Exception $e) {
    echo json_encode(['erreur' => $e->getMessage()]);
}
?>
