let triSelect, searchInput, grille, catId;

document.addEventListener('DOMContentLoaded', () => {
    triSelect = document.getElementById('tri');
    grille = document.querySelector('.produits-grid');
    searchInput = document.querySelector('input[name="recherche"]');

    const urlParams = new URLSearchParams(window.location.search);
    const nomCategorie = urlParams.get('cat');
    const categoriesMapping = {'alimentation': 1, 'artisanat': 2, 'mode': 3, 'culture': 4, 'derives': 5 };
    catId = categoriesMapping[nomCategorie] || '';

    const rechercheInitiale = urlParams.get('recherche');
    if (rechercheInitiale && searchInput) {
        searchInput.value = rechercheInitiale;
    }

    document.querySelectorAll('.filter-controls-container input').forEach(input => {
        input.addEventListener('input', () => {
            chargerCatalogue(false);
        });
        input.addEventListener('change', () => {
            chargerCatalogue(true);
        });
    });

    if (triSelect) { triSelect.addEventListener('change', chargerCatalogue); }
    if (searchInput) { searchInput.addEventListener('input', chargerCatalogue); }

    chargerCatalogue();
});

function chargerCatalogue(doCorrection = false) {
    const tri = triSelect ? triSelect.value : 'ID';
    const recherche = searchInput ? searchInput.value : '';

    let pMinEl = document.getElementById('prix-min');
    let pMaxEl = document.getElementById('prix-max');
    let nMinEl = document.getElementById('note-min');
    let nMaxEl = document.getElementById('note-max');

    if(doCorrection) {
        if (pMinEl && pMaxEl && pMinEl.value && pMaxEl.value && parseFloat(pMinEl.value) > parseFloat(pMaxEl.value)) {
            pMaxEl.value = pMinEl.value;
        }
        if (nMinEl && nMaxEl && nMinEl.value && nMaxEl.value && parseFloat(nMinEl.value) > parseFloat(nMaxEl.value)) {
            nMaxEl.value = nMinEl.value;
        }
    }

    const pMin = pMinEl ? pMinEl.value : '';
    const pMax = pMaxEl ? pMaxEl.value : '';
    const nMin = nMinEl ? nMinEl.value : '';
    const nMax = nMaxEl ? nMaxEl.value : '';

    const urlParams = new URLSearchParams(window.location.search);
    const isPromo = urlParams.get('promo') || '';

    const url = `filtres_catalogue.php?catId=${catId}&recherche=${recherche}&tri=${tri}&pMin=${pMin}&pMax=${pMax}&nMin=${nMin}&nMax=${nMax}&promo=${isPromo}`;

    fetch(url)
        .then(response => response.json())
        .then(produits => {
            afficherProduits(produits);
        })
        .catch(erreur => console.error('Erreur', erreur));
}

function clearInput(ids) {
    ids.forEach(id => {
        const el = document.getElementById(id);
        if(el) el.value = '';
    });
    chargerCatalogue();
}

function toutEffacer() {
    document.querySelectorAll('.filter-controls-container input').forEach(i => i.value = '');
    if(document.getElementById('tri')) document.getElementById('tri').value = 'ID';
    chargerCatalogue();
}

function afficherProduits(produits) {
    const grille = document.querySelector('.produits-grid');

    if (produits.error) {
        console.error("Erreur Backend :", produits.error);
        grille.innerHTML = `<p style="color:red; text-align:center;">Erreur système : ${produits.error}</p>`;
        return;
    }

    if (!produits || produits.length === 0) {
        grille.innerHTML = '<p style="text-align:center; width:100%; padding:20px;">Aucun produit trouvé.</p>';
        return;
    }

    let contenuHTML = '';

    produits.forEach(p => {
        const prixHT = parseFloat(p.prix_ht);
        let tauxRemise = p.taux_remise * 100;

        let tauxTVA = 0;
        if (p.taux_tva !== null && p.taux_tva !== undefined) {
            let rawTva = parseFloat(p.taux_tva);
            tauxTVA = (rawTva > 1) ? rawTva / 100 : rawTva;
        }

        const prixTTCBase = prixHT * (1 + tauxTVA);

        let prixFinal = prixTTCBase;
        const nomPromo = p.nom_promotion;
        const hasPromo = (tauxRemise > 0 && nomPromo !== null && nomPromo !== undefined);

        if (hasPromo) {
            let reduction = prixTTCBase * (tauxRemise / 100);
            prixFinal = prixTTCBase - reduction;
        }

        let prixHTML = '';

        if (hasPromo) {
            prixHTML = `
                <div class="prix-container" style="display:flex; align-items:center; gap:8px;">
                    <span class="sale-price" style="color:#e74c3c; font-weight:bold;">
                        ${prixFinal.toFixed(2).replace('.', ',')} €
                    </span>
                    <span class="old-price" style="text-decoration:line-through; color:gray; font-size:0.9em;">
                        ${prixTTCBase.toFixed(2).replace('.', ',')} €
                    </span>
                    <span class="badge-promo" style="background:#e74c3c; color:white; padding:2px 5px; border-radius:4px; font-size:0.8em;">
                        -${tauxRemise}%
                    </span>
                </div>`;
        } else {
            prixHTML = `
                <span class="normal-price" style="font-weight:bold;">
                    ${prixTTCBase.toFixed(2).replace('.', ',')} €
                </span>`;
        }

        const imgPath = p.chemin + p.nom_fichier + p.extension;
        const noteMoyenne = p.moyenne ? Math.round(parseFloat(p.moyenne)) : 0;

        let etoilesHtml = '';
        for (let i = 1; i <= 5; i++) {
            const etoileSrc = i <= noteMoyenne ? 'media/universel/etoile_pleine.png' : 'media/universel/etoile_vide.png';
            etoilesHtml += `<img src="${etoileSrc}" style="width:16px;">`;
        }

        contenuHTML += `
            <article class="carte-produit">
                <div class="image-zone">
                    <a href="descriptionProduitClient.php?id_produit=${p.id_produit}" style="width:100%; height:100%; display:block;">
                        <img src="${imgPath}" alt="${p.nom_produit}" class="img-produit" loading="lazy"
                             onerror="this.src='images/placeholder.jpg'">
                    </a>
                </div>

                <div class="card-body">
                    <h2 class="card-title">${p.nom_produit}</h2>

                    <div class="card-rating">
                        <div class="etoiles">${etoilesHtml}</div>
                        <span class="avis-count">(${p.nbAvis || 0} avis)</span>
                    </div>

                    <p class="card-desc">${p.description_prod ? p.description_prod.substring(0, 100) + '...' : ''}</p>

                    <div class="card-price-block" style="margin-top: auto;">
                        ${prixHTML}
                    </div>

                    <form action="ajouterPanier.php" class="add-to-cart-form" method="post">
                        <input type="hidden" name="id_produit" value="${p.id_produit}">
                        <input type="hidden" name="redirect_url" value="${window.location.href}">

                        <div class="actions-container" style="display:flex; gap:10px; align-items:center;">
                            ${(() => {
                                const stockDispo = parseInt(p.quantite_dispo) || 0;
                                const dansPanier = parseInt(p.quantite_panier) || 0;
                                const stockRestant = stockDispo - dansPanier;

                                if (stockDispo <= 0) {
                                    return `<button type="button" class="btn-panier" disabled style="opacity: 0.6; cursor: not-allowed;">Rupture de stock</button>`;
                                } else if (stockRestant <= 0) {
                                    return `<button type="button" class="btn-panier" disabled style="opacity: 0.6; cursor: not-allowed;">Stock max atteint</button>`;
                                } else {
                                    return `
                                        <select name="quantite" class="quantite-select-style" style="padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                                            ${genererOptionsQuantite(stockRestant)}
                                        </select>
                                        <button type="submit" class="btn-panier">Ajouter au panier</button>
                                    `;
                                }
                            })()}
                        </div>
                    </form>
                </div>
            </article>`;
    });

    grille.innerHTML = contenuHTML;
}

function genererOptionsQuantite(dispo) {
    let options = '';
    let qte = dispo ? parseInt(dispo) : 0;

    if (qte <= 0) return '<option disabled>0</option>';

    for (let i = 1; i <= qte; i++) {
        options += `<option value="${i}">${i}</option>`;
    }
    return options;
}
