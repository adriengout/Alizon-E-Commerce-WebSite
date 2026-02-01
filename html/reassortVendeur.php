<?php
session_start();

require_once('loginBdd.php');

try {
    $connexionPDO = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);
    $connexionPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connexionPDO->exec("SET search_path TO sae, public");
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

if (!isset($_SESSION['is_vendeur']) || !$_SESSION['is_vendeur']) {
    header('Location: index.php');
    exit();
}

$identifiantVendeur = $_SESSION['id_compte'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id_produit'], $_POST['quantite_reassort'])) {
    $idProduit = intval($_POST['id_produit']);
    $quantiteReassort = intval($_POST['quantite_reassort']);
    $commentaire = trim($_POST['commentaire'] ?? '');

    if ($quantiteReassort > 0) {
        try {
            $connexionPDO->beginTransaction();

            $stmtStock = $connexionPDO->prepare("SELECT quantite_dispo FROM _stock WHERE id_produit = ? AND id_vendeur = ?");
            $stmtStock->execute([$idProduit, $identifiantVendeur]);
            $stockActuel = $stmtStock->fetchColumn();

            if ($stockActuel !== false) {
                $nouvelleQuantite = $stockActuel + $quantiteReassort;

                $stmtUpdate = $connexionPDO->prepare("
                    UPDATE _stock
                    SET quantite_dispo = ?,
                        derniere_maj = CURRENT_DATE,
                        alerte = CASE WHEN ? > seuil_alerte THEN false ELSE alerte END
                    WHERE id_produit = ? AND id_vendeur = ?
                ");
                $stmtUpdate->execute([$nouvelleQuantite, $nouvelleQuantite, $idProduit, $identifiantVendeur]);

                $stmtReassort = $connexionPDO->prepare("
                    INSERT INTO _reassort (id_produit, id_vendeur, quantite_ajoutee, quantite_avant, quantite_apres, commentaire)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtReassort->execute([$idProduit, $identifiantVendeur, $quantiteReassort, $stockActuel, $nouvelleQuantite, $commentaire]);

                $stmtNomProduit = $connexionPDO->prepare("SELECT nom_produit FROM _produit WHERE id_produit = ?");
                $stmtNomProduit->execute([$idProduit]);
                $nomProduit = $stmtNomProduit->fetchColumn();

                $stmtNotif = $connexionPDO->prepare("
                    INSERT INTO _notification (id_compte, type_notification, titre, message, date_creation, lue)
                    VALUES (?, 'Réassort', 'Réassort effectué', ?, CURRENT_TIMESTAMP, false)
                ");
                $messageNotif = "Vous avez ajouté " . $quantiteReassort . " unités au produit \"" . $nomProduit . "\". Stock actuel : " . $nouvelleQuantite . " unités.";
                $stmtNotif->execute([$identifiantVendeur, $messageNotif]);

                $connexionPDO->commit();
                $_SESSION['toast_message'] = "Réassort effectué ! +" . $quantiteReassort . " unités";
                $_SESSION['toast_type'] = "ajoute";
            } else {
                $connexionPDO->rollBack();
                $_SESSION['toast_message'] = "Produit non trouvé dans votre stock";
                $_SESSION['toast_type'] = "erreur";
            }
        } catch (Exception $e) {
            $connexionPDO->rollBack();
            $_SESSION['toast_message'] = "Erreur lors du réassort";
            $_SESSION['toast_type'] = "erreur";
        }
    } else {
        $_SESSION['toast_message'] = "La quantité doit être supérieure à 0";
        $_SESSION['toast_type'] = "erreur";
    }

    header("Location: reassortVendeur.php");
    exit();
}

$requeteProduits = $connexionPDO->prepare("
    SELECT
        p.id_produit,
        p.nom_produit,
        s.quantite_dispo,
        s.seuil_alerte,
        i.nom_fichier,
        i.chemin,
        i.extension
    FROM _produit p
    JOIN _stock s ON p.id_produit = s.id_produit
    LEFT JOIN _image i ON p.id_image = i.id_image
    WHERE p.id_vendeur = :idVendeur
    ORDER BY s.quantite_dispo ASC
");
$requeteProduits->execute(['idVendeur' => $identifiantVendeur]);
$listeProduits = $requeteProduits->fetchAll(PDO::FETCH_ASSOC);

$requeteHistorique = $connexionPDO->prepare("
    SELECT
        r.id_reassort,
        r.quantite_ajoutee,
        r.quantite_avant,
        r.quantite_apres,
        r.date_reassort,
        r.commentaire,
        p.nom_produit
    FROM _reassort r
    JOIN _produit p ON r.id_produit = p.id_produit
    WHERE r.id_vendeur = :idVendeur
    ORDER BY r.date_reassort DESC
    LIMIT 50
");
$requeteHistorique->execute(['idVendeur' => $identifiantVendeur]);
$historiqueReassorts = $requeteHistorique->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="reassortVendeurStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <title>Réassort - Espace Vendeur</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="conteneur-principal">
        <div class="entete-page">
            <h1>Gestion des Réassorts</h1>
            <p class="sous-titre">Réapprovisionnez votre stock et consultez l'historique</p>
        </div>

        <div class="grille-reassort">
            <section class="section-reassort">
                <h2>Réassort rapide</h2>
                <div class="liste-produits-reassort">
                    <?php if (count($listeProduits) > 0): ?>
                        <?php foreach($listeProduits as $produit): ?>
                            <?php
                                $classeStock = 'stock-ok';
                                if ($produit['quantite_dispo'] <= $produit['seuil_alerte']) {
                                    $classeStock = 'stock-critique';
                                } elseif ($produit['quantite_dispo'] <= ($produit['seuil_alerte'] * 1.5)) {
                                    $classeStock = 'stock-faible';
                                }
                            ?>
                            <div class="carte-reassort <?= $classeStock ?>">
                                <div class="info-produit">
                                    <img src="<?= htmlspecialchars($produit['chemin'] . $produit['nom_fichier'] . $produit['extension']) ?>"
                                         alt="<?= htmlspecialchars($produit['nom_produit']) ?>"
                                         class="img-produit">
                                    <div class="details-produit">
                                        <h3><?= htmlspecialchars($produit['nom_produit']) ?></h3>
                                        <p class="stock-actuel">
                                            Stock actuel : <strong><?= $produit['quantite_dispo'] ?></strong>
                                            <span class="seuil">(seuil : <?= $produit['seuil_alerte'] ?>)</span>
                                        </p>
                                    </div>
                                </div>
                                <form method="POST" class="form-reassort">
                                    <input type="hidden" name="id_produit" value="<?= $produit['id_produit'] ?>">
                                    <div class="champs-reassort">
                                        <div class="groupe-input">
                                            <label for="qte_<?= $produit['id_produit'] ?>">Quantité à ajouter</label>
                                            <input type="number"
                                                   id="qte_<?= $produit['id_produit'] ?>"
                                                   name="quantite_reassort"
                                                   min="1"
                                                   value="10"
                                                   required>
                                        </div>
                                        <div class="groupe-input">
                                            <label for="com_<?= $produit['id_produit'] ?>">Commentaire (optionnel)</label>
                                            <input type="text"
                                                   id="com_<?= $produit['id_produit'] ?>"
                                                   name="commentaire"
                                                   placeholder="Ex: Livraison fournisseur"
                                                   maxlength="255">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn-reassort">+ Réapprovisionner</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="etat-vide">
                            <p>Vous n'avez aucun produit en stock.</p>
                            <a href="creerProduit.php" class="btn-primaire">Créer un produit</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="section-historique">
                <h2>Historique des réassorts</h2>
                <?php if (count($historiqueReassorts) > 0): ?>
                    <div class="table-container">
                        <table class="table-historique">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Produit</th>
                                    <th>Quantité ajoutée</th>
                                    <th>Avant → Après</th>
                                    <th>Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($historiqueReassorts as $reassort): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($reassort['date_reassort'])) ?></td>
                                        <td><?= htmlspecialchars($reassort['nom_produit']) ?></td>
                                        <td class="quantite-ajoutee">+<?= $reassort['quantite_ajoutee'] ?></td>
                                        <td>
                                            <span class="avant"><?= $reassort['quantite_avant'] ?></span>
                                            →
                                            <span class="apres"><?= $reassort['quantite_apres'] ?></span>
                                        </td>
                                        <td class="commentaire"><?= htmlspecialchars($reassort['commentaire'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="etat-vide">
                        <p>Aucun réassort effectué pour le moment.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
