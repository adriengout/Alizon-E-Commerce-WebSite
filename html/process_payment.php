<?php
session_start();
require_once('loginBdd.php');

try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$dbname",
        "$username",
        "$password"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur connexion : " . $e->getMessage());
}

// Fonction de nettoyage des entrées
function clean($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: panier.php");
    exit();
}

// Informations client et adresses
$nom = clean($_POST['nom']);
$prenom = clean($_POST['prenom']);
$num_rue = intval($_POST['num_rue']);
$nom_rue = clean($_POST['nom_rue']);
$complement_adresse = clean($_POST['complement_adresse'] ?? '');
$ville = clean($_POST['ville']);
$code_postal = clean($_POST['code_postal']);
$telephone = clean($_POST['telephone']);
$email = clean($_POST['email']);

// Informations carte bancaire
$nom_porteur = clean($_POST['nom_porteur']);
$card_number = preg_replace('/\D/', '', $_POST['card_number']); 
$card_exp = clean($_POST['card_exp']);
$card_cvc = clean($_POST['card_cvc']);

// Adresse de facturation
$same_address = isset($_POST['same-address']);
$num_rue_fact = $same_address ? $num_rue : intval($_POST['num_rue_fact'] ?? 0);
$nom_rue_fact = $same_address ? $nom_rue : clean($_POST['nom_rue_fact'] ?? '');
$complement_adresse_fact = $same_address ? $complement_adresse : clean($_POST['complement_adresse_fact'] ?? '');
$ville_fact = $same_address ? $ville : clean($_POST['ville_fact'] ?? '');
$code_postal_fact = $same_address ? $code_postal : clean($_POST['code_postal_fact'] ?? '');

// Récupération du panier
$cart_items = json_decode($_POST['cart_items'], true);
$cart_total = floatval($_POST['cart_total']);
$frais_livraison = floatval($_POST['frais_livraison']);

// Validation des données
if (!preg_match('/^4[0-9]{15}$/', $card_number)) {
    die("Erreur : Numéro de carte invalide (doit être VISA 16 chiffres).");
}// validation date expiration
if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_exp)) {
    die("Erreur : Date d'expiration invalide (format MM/AA).");
}
// validation CVC
if (!preg_match('/^\d{3}$/', $card_cvc)) {
    die("Erreur : Cryptogramme invalide (3 chiffres requis).");
}
// Fin validation
try {
    $pdo->beginTransaction();

    $id_client = $_SESSION['id_compte'];
    
    $stmt = $pdo->prepare("
        SELECT num_commande
        FROM sae._commande 
        WHERE id_client = :id_client AND statut_commande = 'En attente de paiement'
    ");
    $stmt->execute(['id_client' => $id_client]);
    $num_commande = $stmt->fetchColumn();

    if (!$num_commande) {
        throw new Exception("Aucune commande en attente trouvée.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO sae._adresse (ville, complement_adresse, code_postal, num_rue, nom_rue, id_client, type)
        VALUES (:ville, :complement, :code_postal, :num_rue, :nom_rue, :id_client, 'livraison')
        RETURNING id_adresse
    ");
    $stmt->execute([
        ':ville' => $ville,
        ':complement' => $complement_adresse,
        ':code_postal' => $code_postal,
        ':num_rue' => $num_rue,
        ':nom_rue' => $nom_rue,
        ':id_client' => $id_client
    ]);
    $id_adresse_livraison = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        INSERT INTO sae._adresse (ville, complement_adresse, code_postal, num_rue, nom_rue, id_client, type)
        VALUES (:ville, :complement, :code_postal, :num_rue, :nom_rue, :id_client, 'facturation')
        RETURNING id_adresse
    ");
    $stmt->execute([
        ':ville' => $ville_fact,
        ':complement' => $complement_adresse_fact,
        ':code_postal' => $code_postal_fact,
        ':num_rue' => $num_rue_fact,
        ':nom_rue' => $nom_rue_fact,
        ':id_client' => $id_client
    ]);
    $id_adresse_facturation = $stmt->fetchColumn();

    $montant_calcul_ht = 0;
    $total_nb_prod = 0;

    foreach ($cart_items as $item) {
        $qty = intval($item['qty']);
        $prix_paye_ttc = floatval($item['subtotal']);
        $prix_paye_ht = $prix_paye_ttc / 1.20; 

        $montant_calcul_ht += $prix_paye_ht;
        $total_nb_prod += $qty;

        $stmt_stock = $pdo->prepare("
            UPDATE sae._stock 
            SET quantite_dispo = quantite_dispo - :qty,
                derniere_maj = CURRENT_DATE,
                alerte = CASE WHEN (quantite_dispo - :qty) <= seuil_alerte THEN TRUE ELSE alerte END
            WHERE id_produit = :id_produit
            RETURNING alerte, id_vendeur, quantite_dispo
        ");
        $stmt_stock->execute([
            ':qty' => $qty,
            ':id_produit' => $item['id']
        ]);

        $stmt_ligne = $pdo->prepare("
            UPDATE sae._ligneCommande
            SET total_ligne_commande_ttc = :prix_ttc,
                total_ligne_commande_ht = :prix_ht
            WHERE num_commande = :num_commande AND id_produit = :id_produit
        ");
        $stmt_ligne->execute([
            ':prix_ttc' => $prix_paye_ttc,
            ':prix_ht' => $prix_paye_ht,
            ':num_commande' => $num_commande,
            ':id_produit' => $item['id']
        ]);
        $alerte = $stmt_stock->fetch(PDO::FETCH_ASSOC);
        if ($alerte && $alerte['alerte']) {
            $message = "Le stock du produit ID " . $item['id'] . " est en dessous du seuil d'alerte. Quantité restante : " . $alerte['quantite_dispo'];
            
            $stmt_notif = $pdo->prepare("
                INSERT INTO sae._notification 
                (type_notification, titre, message, date_creation, lue, id_compte) 
                VALUES 
                ('stock', 'Alerte de stock pour le produit ID " . $item['id'] . "', :message, CURRENT_DATE, false, :id_vendeur)
            ");
            $stmt_notif->execute([
                ':message' => $message,
                ':id_vendeur' => $alerte['id_vendeur']
            ]);
        }
        
        
    }


    $host = '127.0.0.1';
    $port = 9000;

    $socket = @fsockopen($host, $port, $errno, $errstr, 2);

    if ($socket) {
        fwrite($socket, "LOGIN alizon admin\n"); 
        fgets($socket); 
        
        fwrite($socket, "GENERER_BORDEREAU $num_commande\n");
        $bordereau = fgets($socket);
    }

    
    $stmt = $pdo->prepare("
        UPDATE sae._commande 
        SET bordereau = :bordereau,
            frais_livraison = :frais,
            montant_total_ht = :ht,
            statut_commande = 'En préparation',
            date_commande = CURRENT_DATE,
            total_nb_prod = :nb_prod,
            montant_total_ttc = :ttc,
            id_livraison = :id_livraison,
            id_facturation = :id_facturation
        WHERE num_commande = :num_commande
    ");

    $stmt->execute([
        'bordereau' => $bordereau,
        'frais' => $frais_livraison,
        'ht' => $montant_calcul_ht,
        'nb_prod' => $total_nb_prod,
        'ttc' => $cart_total,
        'id_livraison' => $id_adresse_livraison,
        'id_facturation' => $id_adresse_facturation,
        'num_commande' => $num_commande
    ]);
    
    $card_masked = '**** **** **** ' . substr($card_number, -4);
    
    $stmt = $pdo->prepare("
        INSERT INTO sae._coordonnees_banquaire 
        (numero_cb_masque, nom_porteur, cryptogramme, date_exp_cb, id_client)
        VALUES (:numero_masque, :nom_porteur, :crypto, TO_DATE(:exp, 'MM/YY'), :id_client)
        RETURNING id_coord
    ");
    $stmt->execute([
        ':numero_masque' => $card_masked,
        ':nom_porteur' => $nom_porteur,
        ':crypto' => $card_cvc,
        ':exp' => $card_exp,
        ':id_client' => $id_client
    ]);
    $id_coord = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        INSERT INTO sae._paiement 
        (id_coord, num_commande, montant_ttc)
        VALUES (:id_coord, :num_commande, :montant_ttc)
        RETURNING id_paiement
    ");
    $stmt->execute([
        ':id_coord' => $id_coord,
        ':num_commande' => $num_commande,
        ':montant_ttc' => $cart_total
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO sae._facture 
        (date_facture, montant_facture, num_commande)
        VALUES (CURRENT_DATE, :montant, :num_commande)
        RETURNING id_facture
    ");
    $stmt->execute([
        ':montant' => $cart_total,
        ':num_commande' => $num_commande
    ]);
    $id_facture = $stmt->fetchColumn();
    
    

    
    $pdo->commit();
    
    $_SESSION['commande_success'] = [
        'num_commande' => $num_commande,
        'id_facture' => $id_facture,
        'cart_items' => $cart_items,
        'cart_total' => $cart_total,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'adresse' => "$num_rue $nom_rue, $code_postal $ville"
    ];
    
    unset($_SESSION['tmp_panier']);
    
    header("Location: success.php");
    exit();
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur lors de l'enregistrement de la commande : " . $e->getMessage());
}
?>