<?php
session_start();
if (!isset($_SESSION['id_compte'])){
  header("Location: connexion.php");
  exit();
}
require_once("loginBdd.php");

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET search_path TO sae");
} catch (Exception $e) {
    die("Erreur connexion : " . $e->getMessage());
}

$id_client = $_SESSION['id_compte'];

$stmt = $pdo->prepare("
    SELECT
        LC.id_produit,
        LC.quantite_prod as quantite_prod,
        P.nom_produit,
        P.prix_ht,
        P.description_prod,
        I.chemin,
        I.nom_fichier,
        I.extension,
        COALESCE(T.taux_tva, 20.0) as taux_tva,
        R.taux_remise, 
        PR.nom_promotion
    FROM
        sae._ligneCommande LC
    JOIN
        sae._commande C ON LC.num_commande = C.num_commande
    JOIN
        sae._produit P ON LC.id_produit = P.id_produit
    JOIN
        sae._image I ON P.id_image = I.id_image
    LEFT JOIN
        sae._tva T ON P.id_produit = T.id_produit
    LEFT JOIN sae._promotion PR ON P.id_produit = PR.id_produit 
        AND CURRENT_DATE BETWEEN PR.date_debut AND PR.date_fin
    LEFT JOIN sae._remise R ON P.id_produit = R.id_produit
    WHERE
        C.id_client = :id_client
        AND C.statut_commande = 'En attente de paiement'
");
$stmt->execute(['id_client' => $id_client]);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$cart_items = [];
$total = 0;

foreach ($produits as $produit) { // Pour chaque produit dans le panier
    $quantite = intval($produit['quantite_prod']);
    $prix_ht = floatval($produit['prix_ht']);
    
    $rawTva = floatval($produit['taux_tva']);
    $taux_tva = ($rawTva > 1) ? $rawTva / 100 : $rawTva;

    $prix_unitaire_ttc_base = $prix_ht * (1 + $taux_tva);
    
    $rawRemise = !empty($produit['taux_remise']) ? floatval($produit['taux_remise']) : 0;
    $pourcentage = ($rawRemise > 1) ? $rawRemise / 100 : $rawRemise;

    // Prix final après remise
    $prix_final = $prix_unitaire_ttc_base;

  // Appliquer la remise si une promotion est active
    if (!empty($produit['nom_promotion']) && $pourcentage > 0) {
        $prix_final = $prix_final * (1 - $pourcentage);
    }

    if ($prix_final < 0) $prix_final = 0;
    $prix_final = round($prix_final, 2);

    $total_ligne_ttc = $prix_final * $quantite;
    
    // Chemin de l'image
    $image_path = $produit['chemin'] . $produit['nom_fichier'] . $produit['extension'];
    
    $cart_items[] = [
        'id' => $produit['id_produit'],
        'name' => $produit['nom_produit'],
        'description' => $produit['description_prod'],
        'price_ttc' => $prix_final,
        'original_price' => $prix_unitaire_ttc_base,
        'qty' => $quantite,
        'subtotal' => $total_ligne_ttc,
        'image' => $image_path,
        'remise_percent' => $pourcentage * 100,
        'nom_promo' => $produit['nom_promotion']
    ];
    
    $total += $total_ligne_ttc;
}

$frais_livraison = 0;
$total_final = $total + $frais_livraison;
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Alizon</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="paiement.css">
    <link rel="stylesheet" href="footer.css">
  </head>
  <body>
  <main>
    <div class="container">
      <a href="panier.php" class="btn-retour-panier">← Retour au panier</a>

      <section>
        <article class="formulaire">
          <form action="process_payment.php" method="POST" id="payment-form">

            <h2>Adresse de Livraison</h2>

            <div class="two-col">
              <div>
                <label>Nom*</label>
                <input type="text" name="nom" required>
              </div>
              <div>
                <label>Prénom*</label>
                <input type="text" name="prenom" required>
              </div>
            </div>

            <label>Numéro de rue*</label>
            <input type="number" name="num_rue" required min="1">

            <label>Nom de rue*</label>
            <input type="text" name="nom_rue" required>

            <label>Complément d'adresse</label>
            <input type="text" name="complement_adresse" placeholder="Bâtiment, appartement, étage...">

            <div class="two-col">
              <div class="ville-wrapper">
                <label>Ville*</label>
                <input type="text" id="ville" name="ville" autocomplete="off" required>
                <ul class="suggestions" id="suggestions"></ul>
              </div>

              <div>
                <label>Code Postal*</label>
                <input type="text" id="codepostal" name="code_postal" required pattern="[0-9]{5}">
              </div>
            </div>

            <label>Téléphone*</label>
            <input type="tel" name="telephone" placeholder="06 12 34 56 78" required>

            <label>Email*</label>
            <input type="email" name="email" placeholder="exemple@email.com" required>

            <h2>Paiement par carte bancaire</h2>

            <label>Nom figurant sur la carte*</label>
            <input type="text" name="nom_porteur" required placeholder="Nom complet" />

            <label>Numéro de carte*</label>
            <input type="text" name="card_number" id="card-number" maxlength="19"
                  placeholder="4111 1111 1111 1111" required />

            <div class="two-col">
              <div>
                <label>Date d'expiration*</label>
                <input type="text" name="card_exp" id="card-exp" maxlength="5"
                      placeholder="MM/AA" required />
              </div>
              <div>
                <label>Cryptogramme (CVV)*</label>
                <input type="text" name="card_cvc" id="card-cvc" maxlength="3"
                      placeholder="123" required />
              </div>
            </div>

            <h2>Adresse de facturation</h2>
            
            <label class="checkbox-label">
              <input type="checkbox" id="same-address" name="same-address" checked> 
              Identique à l'adresse de livraison
            </label>

            <div id="billing-address" style="display: none;">
              <label>Numéro de rue</label>
              <input type="number" name="num_rue_fact" min="1">

              <label>Nom de rue</label>
              <input type="text" name="nom_rue_fact">

              <label>Complément d'adresse</label>
              <input type="text" name="complement_adresse_fact">

              <div class="two-col">
                <div class="ville-wrapper">
                  <label>Ville</label>
                  <input type="text" id="ville-fact" name="ville_fact" autocomplete="off">
                  <ul class="suggestions" id="suggestions-fact"></ul>
                </div>

                <div>
                  <label>Code Postal</label>
                  <input type="text" id="codepostal-fact" name="code_postal_fact" pattern="[0-9]{5}">
                </div>
              </div>
            </div>

            
            <input type="hidden" name="cart_items" value='<?= htmlspecialchars(json_encode($cart_items)) ?>'>
            <input type="hidden" name="cart_total" value="<?= $total_final ?>">
            <input type="hidden" name="frais_livraison" value="<?= $frais_livraison ?>">

            <button type="submit" class="btn jaune">
              Valider ma commande • <?= number_format($total_final, 2) ?> €
            </button>

          </form>
        </article>

        <aside class="recapitulatif">
          <h2>Récapitulatif</h2>
          
          <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
              <p>Votre panier est vide</p>
              <a href="catalogue.php">Retour au catalogue</a>
            </div>
          <?php else: ?>
            <?php foreach ($cart_items as $item): ?>
            <div class="recap-item">
              <img src="<?= htmlspecialchars($item['image']) ?>" 
                  alt="<?= htmlspecialchars($item['name']) ?>"
                  onerror="this.src='images/placeholder.jpg'">
              <div class="recap-item-info">
                <div class="recap-item-name"><?= htmlspecialchars($item['name']) ?></div>
                
                <div class="recap-item-qty">
                  Qté : <?= $item['qty'] ?> × 
                  
                  <?php if ($item['remise_percent'] > 0): ?>
                    <span style="color: #e74c3c; font-weight: bold;">
                        <?= number_format($item['price_ttc'], 2) ?> €
                    </span>
                    <br>
                    <span style="font-size: 0.8em; color: gray; text-decoration: line-through;">
                        <?= number_format($item['original_price'], 2) ?> €
                    </span>
                    <span style="font-size: 0.8em; color: #27ae60;">
                        (-<?= round($item['remise_percent']) ?>%)
                    </span>
                  <?php else: ?>
                    <?= number_format($item['price_ttc'], 2) ?> €
                  <?php endif; ?>

                </div>
              </div>
              <div class="recap-item-price">
                <?= number_format($item['subtotal'], 2) ?> €
              </div>
            </div>
            <?php endforeach; ?>

            <div class="recap-subtotal">
              <span>Sous-total (<?= count($cart_items) ?> article<?= count($cart_items) > 1 ? 's' : '' ?>)</span>
              <span><?= number_format($total, 2) ?> €</span>
            </div>

            <div class="recap-shipping">
              <span>Frais de livraison</span>
              <span><?= number_format($frais_livraison, 2) ?> €</span>
            </div>

            <div class="recap-total">
              <span>Total TTC</span>
              <span><?= number_format($total_final, 2) ?> €</span>
            </div>
          <?php endif; ?>
        </aside>
      </section>
    </div>
  </main>
  <script>
  let communes = [];

  fetch("communes.csv")
    .then(response => response.text())
    .then(text => {
      communes = text.split("\n").slice(1).map(ligne => {
        const [nom, code] = ligne.split(",");
        return { nom: nom?.trim(), code: code?.trim() };
      }).filter(x => x.nom && x.code);
    })
    .catch(err => console.error("Erreur chargement communes:", err));

  function setupAutocomplete(inputId, codeId, suggestionsId) {
    const input = document.getElementById(inputId);
    const code = document.getElementById(codeId);
    const list = document.getElementById(suggestionsId);

    if (!input || !code || !list) return;

    function afficherListe(villes) {
      list.innerHTML = "";
      villes.slice(0, 10).forEach(c => {
        const li = document.createElement("li");
        li.textContent = c.nom + " (" + c.code + ")";
        li.onclick = () => {
          input.value = c.nom;
          code.value = c.code;
          list.style.display = "none";
        };
        list.appendChild(li);
      });
      list.style.display = villes.length ? "block" : "none";
    }

    input.addEventListener("focus", () => {
      if (communes.length > 0) {
        afficherListe(communes);
      }
    });

    input.addEventListener("input", () => {
      const texte = input.value.toLowerCase();
      const filtres = texte === "" ? communes : communes.filter(c => 
        c.nom.toLowerCase().includes(texte)
      );
      afficherListe(filtres);
    });

    document.addEventListener("click", (e) => {
      if (!list.contains(e.target) && e.target !== input) {
        list.style.display = "none";
      }
    });
  }

  setupAutocomplete("ville", "codepostal", "suggestions");
  setupAutocomplete("ville-fact", "codepostal-fact", "suggestions-fact");

  const cardNumber = document.getElementById("card-number");
  if (cardNumber) {
    cardNumber.addEventListener("input", function(e) {
      let value = e.target.value.replace(/\D/g, "");
      value = value.replace(/(.{4})/g, "$1 ").trim();
      e.target.value = value;
    });
  }

  const cardExp = document.getElementById("card-exp");
  if (cardExp) {
    cardExp.addEventListener("input", function(e) {
      let value = e.target.value.replace(/\D/g, "");
      if (value.length >= 3) {
        value = value.substring(0, 2) + "/" + value.substring(2, 4);
      }
      e.target.value = value;
    });
  }

  const cardCvc = document.getElementById("card-cvc");
  if (cardCvc) {
    cardCvc.addEventListener("input", function(e) {
      e.target.value = e.target.value.replace(/\D/g, "");
    });
  }

  const sameAddressCheckbox = document.getElementById("same-address");
  const billingAddress = document.getElementById("billing-address");

  if (sameAddressCheckbox && billingAddress) {
    sameAddressCheckbox.addEventListener("change", function() {
      billingAddress.style.display = this.checked ? "none" : "block";
    });
  }

  const paymentForm = document.getElementById("payment-form");
  if (paymentForm) {
    paymentForm.addEventListener("submit", function(e) {
      const cardNum = document.getElementById("card-number").value.replace(/\D/g, "");
      
      if (cardNum.length !== 16) {
        e.preventDefault();
        alert("Le numéro de carte doit contenir 16 chiffres.");
        return false;
      }
      
      if (!cardNum.startsWith('4')) {
        e.preventDefault();
        alert("Seules les cartes VISA sont acceptées (commence par 4).");
        return false;
      }
      
      return true;
    });
  }
  </script>
  </body>
</html>