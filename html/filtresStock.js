
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.filter-controls-container input, #tri').forEach(input => {
        input.addEventListener('input', () =>
            chargerStock(false)
    );
        input.addEventListener('change', () =>
            chargerStock(true)
    );
    });
    chargerStock();
});

function chargerStock(doCorrection) {
    const recherche = document.getElementById('vendeur-recherche').value;
    const tri = document.getElementById('tri').value;
    const pMin = document.getElementById('prix-min').value;
    const pMax = document.getElementById('prix-max').value;
    const qMin = document.getElementById('qMin').value;
    let pMinEl = document.getElementById('prix-min');
    let pMaxEl = document.getElementById('prix-max');

    if(doCorrection) {
        if (pMinEl.value && pMaxEl.value && parseFloat(pMinEl.value) > parseFloat(pMaxEl.value)) {
            pMaxEl.value = pMinEl.value;
        }
    }

    const url = `filtres_stock.php?recherche=${recherche}&tri=${tri}&pMin=${pMin}&pMax=${pMax}&qMin=${qMin}`;

    fetch(url)
        .then(response => response.json())
        .then(data => afficherStock(data))
        .catch(err => console.error(err));
}

function afficherStock(produits) {
    const grille = document.getElementById('stock-grid');
    grille.innerHTML = '';

    if (produits.length === 0) {
        grille.innerHTML = '<p class="etat-vide">Aucun produit trouvé.</p>';
        return;
    }

    produits.forEach(p => {
        let classe = 'stock-suffisant';
        let texte = 'En stock';
        if (p.quantite_dispo <= p.seuil_alerte) {
            classe = 'stock-critique'; texte = 'Stock critique';
        } else if (p.quantite_dispo <= (p.seuil_alerte * 1.5)) {
            classe = 'stock-faible'; texte = 'Stock faible';
        }

        grille.innerHTML += `
            <div class="carte-produit">
                <div class="image-carte">
                    <img src="${p.chemin}${p.nom_fichier}${p.extension}" alt="${p.nom_produit}">
                    <span class="etiquette-stock ${classe}">${texte}</span>
                </div>
                <div class="contenu-carte">
                    <h3>${p.nom_produit}</h3>
                    <p class="prix">${parseFloat(p.prix_ht).toFixed(2)} € HT</p>
                    <div class="info-stock">Quantité : <strong>${p.quantite_dispo}</strong></div>
                </div>
                <div class="actions-carte">
                    <a href="modifierProduit.php?id=${p.id_produit}" class="bouton-action bouton-modifier">Modifier</a>

                    <form class="form-supprimer-produit" method="post" action="supprimerProduits.php">
                        <input type="hidden" name="id_produit" value="${p.id_produit}">
                        <button type="submit" name="action" value="supprimer_produit" class="bouton-action bouton-supprimer">Supprimer</button>
                    </form>
                </div>
            </div>`;
    });
}

function toutEffacer() {
    document.querySelectorAll('.filter-controls-container input').forEach(i => i.value = '');
    document.getElementById('tri').value = '';
    chargerStock();
}
