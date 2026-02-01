<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();
include('loginBdd.php'); // Vos identifiants BDD

// 1. Vérification de sécurité
if (!isset($_SESSION['id_compte']) || !isset($_GET['num_commande'])) {
    die("Accès refusé.");
}

$id_compte_connecte = $_SESSION['id_compte'];
$num_commande = $_GET['num_commande'];

try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ---------------------------------------------------------
    // ETAPE 1 : Récupérer l'ID Vendeur lié au compte connecté
    // ---------------------------------------------------------
    $stmtVendeurID = $dbh->prepare('SELECT id_vendeur FROM sae._vendeur WHERE id_vendeur = :id'); 
    // Note: Si votre table _vendeur utilise un autre champ pour le lien (ex: id_compte), adaptez ici.
    // On suppose ici que id_vendeur est la clé étrangère ou primaire liée au compte.
    $stmtVendeurID->execute([':id' => $id_compte_connecte]);
    $resVendeur = $stmtVendeurID->fetch(PDO::FETCH_ASSOC);

    if (!$resVendeur) {
        // Fallback : Si l'utilisateur est un admin ou autre, ou si la structure est différente
        // Pour l'exercice, on va supposer que l'ID session EST l'ID vendeur (ou lié directement)
        $id_vendeur_courant = $id_compte_connecte;
    } else {
        $id_vendeur_courant = $resVendeur['id_vendeur'];
    }

    // ---------------------------------------------------------
    // ETAPE 2 : Récupérer TOUTES les infos (Commande, Client, Adresses, Vendeur)
    // ---------------------------------------------------------
    $stmtCmd = $dbh->prepare('
        SELECT 
            c.num_commande, c.statut_commande, c.frais_livraison,
            lc.quantite_prod, lc.total_ligne_commande_ttc,
            p.nom_produit, p.prix_ht, p.id_produit,
            f.date_facture,
            
            -- Infos Client
            cl.nom AS nom_client, cl.prenom AS prenom_client,
            co.mail AS mail_client, co.tel AS tel_client,

            -- Infos Vendeur (celui connecté)
            v.nom_entreprise, v.siret, v.description_vendeur,
            vc.tel AS tel_vendeur,

            -- Adresse Vendeur
            av.num_rue AS v_num, av.nom_rue AS v_rue, av.code_postal AS v_cp, av.ville AS v_ville,

            -- Adresse Livraison Client
            al.num_rue AS liv_num, al.nom_rue AS liv_rue, al.code_postal AS liv_cp, al.ville AS liv_ville, al.complement_adresse AS liv_comp,
            
            -- Adresse Facturation Client
            af.num_rue AS fac_num, af.nom_rue AS fac_rue, af.code_postal AS fac_cp, af.ville AS fac_ville, af.complement_adresse AS fac_comp

        FROM sae._commande c
        INNER JOIN sae._ligneCommande lc ON c.num_commande = lc.num_commande
        INNER JOIN sae._produit p ON lc.id_produit = p.id_produit
        INNER JOIN sae._facture f ON c.num_commande = f.num_commande 
        
        -- Jointures Client
        INNER JOIN sae._client cl ON c.id_client = cl.id_client
        INNER JOIN sae._compte co ON cl.id_client = co.id_compte

        -- Jointures Vendeur
        INNER JOIN sae._vendeur v ON p.id_vendeur = v.id_vendeur
        INNER JOIN sae._compte vc ON v.id_vendeur = vc.id_compte 
        LEFT JOIN sae._adresse av ON v.id_vendeur = av.id_vendeur

        -- Jointures Adresses Client
        LEFT JOIN sae._adresse al ON c.id_livraison = al.id_adresse 
        LEFT JOIN sae._adresse af ON c.id_facturation = af.id_adresse 

        WHERE c.num_commande = :num_cmd 
          AND p.id_vendeur = :id_vendeur
    ');
    
    $stmtCmd->execute([
        ':num_cmd' => $num_commande,
        ':id_vendeur' => $id_vendeur_courant
    ]);
    
    $lignes = $stmtCmd->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lignes)) die("Aucune commande trouvée pour ce vendeur.");

    // --- PRÉPARATION DES DONNÉES POUR L'AFFICHAGE ---
    
    // On prend la première ligne pour les infos générales (Vendeur, Client, Adresses)
    $first = $lignes[0];

    // Formatage Adresse Vendeur
    $addr_vendeur = $first['v_num'] ? ($first['v_num'] . ' ' . $first['v_rue'] . '<br>' . $first['v_cp'] . ' ' . $first['v_ville']) : "Adresse non renseignée";

    // Formatage Adresse Facturation (Client)
    $addr_facturation = $first['fac_num'] . ' ' . $first['fac_rue'] . '<br>';
    if(!empty($first['fac_comp'])) $addr_facturation .= $first['fac_comp'] . '<br>';
    $addr_facturation .= $first['fac_cp'] . ' ' . $first['fac_ville'];

    // Formatage Adresse Livraison (Client)
    $addr_livraison = $first['liv_num'] . ' ' . $first['liv_rue'] . ' ';
    $addr_livraison .= $first['liv_cp'] . ' ' . $first['liv_ville'];

    // Génération N° Facture
    $annee = date('Y', strtotime($first['date_facture']));
    $num_pad = str_pad($first['num_commande'], 4, '0', STR_PAD_LEFT);
    $ref_facture = "FAC-" . $annee . "-" . $num_pad . "-" . str_pad($id_vendeur_courant, 2, '0', STR_PAD_LEFT);

    // Initialisation des totaux
    $total_ht = 0;
    $total_ttc = 0;

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// Fonction utilitaire pour la TVA
function getTva($dbh, $idProduit) {
    try {
        $stmt = $dbh->prepare("SELECT t.taux_tva FROM sae._tva t WHERE t.id_produit = :idProduit");
        $stmt->execute([$idProduit]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture Vendeur <?php echo htmlspecialchars($num_commande); ?></title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #525659; margin: 0; padding: 20px; color: #333; }
        .page-facture { background-color: white; width: 210mm; min-height: 297mm; margin: 0 auto 30px auto; padding: 20mm; box-sizing: border-box; position: relative; }
        
        /* HEADER */
        .header-row { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .col-left { width: 48%; }
        .col-right { width: 40%; text-align: left; background-color: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #eee; }
        .logo h1 { margin: 0; color: #2c3e50; font-size: 26px; text-transform: uppercase; line-height: 1.2; }
        .platform-mention { font-size: 11px; color: #999; margin-bottom: 15px; font-style: italic; }
        .vendeur-address { font-size: 14px; line-height: 1.5; color: #555; margin-top: 10px; }
        .client-title { font-size: 12px; text-transform: uppercase; color: #999; font-weight: bold; margin-bottom: 5px; }
        .client-info p { margin: 0; font-size: 14px; line-height: 1.5; }

        /* BARRE DE DÉTAILS */
        .details-bar { display: flex; justify-content: space-between; background-color: #f1f2f6; padding: 10px 20px; border-left: 5px solid #2c3e50; margin-bottom: 30px; }
        .detail-item strong { display: block; font-size: 11px; text-transform: uppercase; color: #7f8c8d; }
        .detail-item span { font-size: 14px; font-weight: bold; color: #2c3e50; }
        .detail-address { font-size: 11px; color: #555; margin-top: 5px; }

        /* TABLEAU PRINCIPAL */
        table.main-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.main-table th { background-color: #2c3e50; color: white; font-weight: normal; text-align: left; padding: 10px; font-size: 13px; }
        table.main-table td { padding: 12px 10px; border-bottom: 1px solid #eee; font-size: 13px; }
        .col-right-align { text-align: right; }
        .col-center { text-align: center; }

        /* SECTION BAS DE PAGE (Totaux) */
        .bottom-row { display: flex; justify-content: space-between; margin-top: 20px; align-items: flex-end; }
        
        /* TABLEAU RÉCAPITULATIF (GAUCHE) */
        .summary-box { width: 50%; }
        .summary-table { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
        .summary-table th { background-color: #f8f9fa; font-size: 12px; padding: 8px; text-align: center; border: 1px solid #ddd; color: #666; }
        .summary-table td { font-size: 14px; padding: 10px; text-align: center; border: 1px solid #ddd; font-weight: bold; }

        /* TOTAL FINAL (DROITE) */
        .total-box { width: 40%; }
        .total-table { width: 100%; }
        .total-table td { padding: 8px 0; text-align: right; font-size: 14px; }
        .total-final { font-size: 18px; font-weight: bold; color: #2c3e50; border-top: 2px solid #2c3e50; padding-top: 10px; }
        
        footer { position: absolute; bottom: 20mm; left: 20mm; right: 20mm; text-align: center; font-size: 11px; color: #aaa; border-top: 1px solid #eee; padding-top: 15px; }

        @media print {
            @page { margin: 0; size: auto; }
            body { background: white; margin: 0; padding: 0; }
            .page-facture { width: 100%; margin: 0; box-shadow: none; height: auto; page-break-after: always; }
            .page-facture:last-child { page-break-after: auto; }
            footer { position: static; margin-top: 50px; }
            .col-right { background-color: white; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>

    <div class="page-facture">
        
        <div class="header-row">
            
            <div class="col-left">
                <div class="logo">
                    <h1><?= htmlspecialchars($first['nom_entreprise']) ?></h1>
                    <div class="platform-mention">Copie Vendeur - ALIZON Marketplace</div>
                </div>
                <div class="vendeur-address">
                    <?= $addr_vendeur ?><br>
                    <strong>Tél :</strong> <?= htmlspecialchars($first['tel_vendeur']) ?><br>
                    <strong>SIRET :</strong> <?= htmlspecialchars($first['siret']) ?><br>
                    <strong>TVA Intra. :</strong> Non renseigné
                </div>
            </div>

            <div class="col-right">
                <div class="client-title">Client Facturé :</div>
                <div class="client-info">
                    <p>
                        <strong><?= htmlspecialchars($first['prenom_client'] . ' ' . $first['nom_client']) ?></strong><br>
                        <?= $addr_facturation ?><br><br>
                        <?= htmlspecialchars($first['mail_client']) ?><br>
                        <?= htmlspecialchars($first['tel_client']) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="details-bar">
            <div class="detail-item">
                <strong>N° Facture</strong>
                <span><?= htmlspecialchars($ref_facture) ?></span>
            </div>
            <div class="detail-item">
                <strong>Date d'émission</strong>
                <span><?= date('d/m/Y', strtotime($first['date_facture'])) ?></span>
            </div>
            <div class="detail-item">
                <strong>Référence Commande</strong>
                <span>#<?= htmlspecialchars($first['num_commande']) ?></span>
            </div>
            <div class="detail-item">
                <strong>Lieu de Livraison</strong>
                <span class="detail-address"><?= $addr_livraison ?></span>
            </div>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th width="45%">Désignation</th>
                    <th width="10%" class="col-center">Qté</th>
                    <th width="15%" class="col-right-align">P.U. HT</th>
                    <th width="10%" class="col-right-align">TVA</th>
                    <th width="20%" class="col-right-align">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): 
                    // Récupération TVA et Calculs
                    $taux_tva = getTva($dbh, $ligne['id_produit']);
                    
                    // Calcul des totaux pour ce produit
                    $ligne_ht = $ligne['prix_ht'] * $ligne['quantite_prod'];
                    $ligne_ttc = $ligne['total_ligne_commande_ttc']; // Valeur BDD fiable maintenant
                    
                    // Ajout aux totaux globaux
                    $total_ht += $ligne_ht;
                    $total_ttc += $ligne_ttc;
                ?>
                <tr>
                    <td><?= htmlspecialchars($ligne['nom_produit']) ?></td>
                    <td class="col-center"><?= $ligne['quantite_prod'] ?></td>
                    <td class="col-right-align"><?= number_format($ligne['prix_ht'], 2, ',', ' ') ?> €</td>
                    <td class="col-right-align"><?= number_format($taux_tva, 1, ',', ' ') ?> %</td>
                    <td class="col-right-align"><strong><?= number_format($ligne['total_ligne_commande_ttc'], 2, ',', ' ') ?> €</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php $total_tva = $total_ttc - $total_ht; ?>

        <div class="bottom-row">
            
            <div class="summary-box">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Montant HT</th>
                            <th>Montant TVA</th>
                            <th>Montant TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= number_format($total_ht, 2, ',', ' ') ?> €</td>
                            <td><?= number_format($total_tva, 2, ',', ' ') ?> €</td>
                            <td><?= number_format($total_ttc, 2, ',', ' ') ?> €</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="total-box">
                <table class="total-table">
                    <tr>
                        <td>Total HT :</td>
                        <td><?= number_format($total_ht, 2, ',', ' ') ?> €</td>
                    </tr>
                    <tr>
                        <td>Total TVA :</td>
                        <td><?= number_format($total_tva, 2, ',', ' ') ?> €</td>
                    </tr>
                    <tr>
                        <td class="total-final">TOTAL COMMANDE :</td>
                        <td class="total-final"><?= number_format($total_ttc, 2, ',', ' ') ?> €</td>
                    </tr>
                </table>
            </div>

        </div>

        <footer>
            Document interne Vendeur - Ceci est le récapitulatif de vos ventes pour la commande #<?= htmlspecialchars($first['num_commande']) ?>.
        </footer>

    </div>

    <script>