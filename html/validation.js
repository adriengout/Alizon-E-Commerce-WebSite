document.addEventListener('DOMContentLoaded', function() {
    
    const submitBtn = document.querySelector('input[type="submit"]');

    // --- Fonction principale : Activer/Désactiver le bouton Suivant ---
    function updateButtonState() {
        if (!submitBtn) return;

        // 1. Y a-t-il des champs marqués en erreur (rouge) ?
        const hasErrors = document.querySelectorAll('.input-erreur').length > 0;

        // 2. Y a-t-il des champs requis qui sont vides ?
        let hasEmptyFields = false;
        const requiredFields = document.querySelectorAll('input[required], textarea[required]');

        requiredFields.forEach(field => {
            if (field.value.trim() === '') {
                hasEmptyFields = true;
            }
        });

        // Si erreur OU vide => on désactive le bouton
        if (hasErrors || hasEmptyFields) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = "0.5"; // Visuel : grisé
            submitBtn.style.cursor = "not-allowed";
        } else {
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1"; // Visuel : normal
            submitBtn.style.cursor = "pointer";
        }
    }

    // Fonction utilitaire pour afficher/masquer les erreurs
    const setStatus = (inputId, isValid, message = '') => {
        const input = document.getElementById(inputId);
        if (!input) return;

        let errorSpan = input.nextElementSibling;
        while (errorSpan && !errorSpan.classList.contains('erreur-message')) {
            errorSpan = errorSpan.nextElementSibling;
        }

        if (!isValid) {
            input.classList.add('input-erreur');
            if (errorSpan) errorSpan.innerText = message;
        } else {
            input.classList.remove('input-erreur');
            if (errorSpan) errorSpan.innerText = "";
        }
        
        // IMPORTANT : On revérifie le bouton à chaque changement d'état
        updateButtonState();
    };

    // --- 1. Validation LOGIN (AJAX) ---
    const loginInput = document.getElementById('login');
    if (loginInput) {
        loginInput.addEventListener('input', debounce(function() {
            const value = this.value;
            if (!/^[a-zA-Z0-9_-]+$/.test(value)) {
                setStatus('login', false, "Caractères autorisés : lettres, chiffres, - et _");
                return;
            }
            if (value.length < 3 || value.length > 20) {
                setStatus('login', false, "Entre 3 et 20 caractères");
                return;
            }
            checkDatabase('login', value);
        }, 500));
        
        // Petit hack : on lance updateButtonState sur l'input simple aussi 
        // pour gérer le cas où l'utilisateur efface tout (champ vide)
        loginInput.addEventListener('input', updateButtonState);
    }

    // --- 2. Validation EMAIL (AJAX) ---
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', debounce(function() {
            const value = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(value)) {
                setStatus('email', false, "Format email invalide");
                return;
            }
            checkDatabase('email', value);
        }, 500));
        emailInput.addEventListener('input', updateButtonState);
    }

    // --- 3. Validation MOT DE PASSE (JS pur) ---
    const mdpInput = document.getElementById('motdepasse');
    if (mdpInput) {
        mdpInput.addEventListener('input', function() {
            const val = this.value;
            let err = "";

            if (val.length < 8) err = "8 caractères minimum.";
            else if (!/[A-Z]/.test(val)) err = "Une majuscule requise.";
            else if (!/[a-z]/.test(val)) err = "Une minuscule requise.";
            else if (!/[0-9]/.test(val)) err = "Un chiffre requis.";
            else if (!/[!@#$%^&*(),.?":{}|<>_\-+=]/.test(val)) err = "Un caractère spécial requis.";

            setStatus('motdepasse', err === "", err);
        });
    }

    // --- 3b. Validation NOUVEAU MOT DE PASSE (pour changementMdp et reset_password) ---
    const newMdpInput = document.getElementById('newMdp');
    if (newMdpInput) {
        newMdpInput.addEventListener('input', function() {
            const val = this.value;
            let err = "";

            if (val.length < 8) err = "8 caractères minimum.";
            else if (!/[A-Z]/.test(val)) err = "Une majuscule requise.";
            else if (!/[a-z]/.test(val)) err = "Une minuscule requise.";
            else if (!/[0-9]/.test(val)) err = "Un chiffre requis.";
            else if (!/[!@#$%^&*(),.?":{}|<>_\-+=]/.test(val)) err = "Un caractère spécial requis.";

            setStatus('newMdp', err === "", err);

            // Vérifier aussi la confirmation si elle existe et n'est pas vide
            const confMdpInput = document.getElementById('confMdp');
            if (confMdpInput && confMdpInput.value !== '') {
                if (val !== confMdpInput.value) {
                    setStatus('confMdp', false, "Les mots de passe ne correspondent pas.");
                } else {
                    setStatus('confMdp', true, "");
                }
            }
        });
    }

    // --- 3c. Validation CONFIRMATION MOT DE PASSE ---
    const confMdpInput = document.getElementById('confMdp');
    if (confMdpInput) {
        confMdpInput.addEventListener('input', function() {
            const val = this.value;
            const newMdpVal = document.getElementById('newMdp')?.value || '';

            if (val !== newMdpVal) {
                setStatus('confMdp', false, "Les mots de passe ne correspondent pas.");
            } else {
                setStatus('confMdp', true, "");
            }
        });
    }

    // --- 4. Validation TÉLÉPHONE (JS pur) ---
    const telInput = document.getElementById('tel');
    if (telInput) {
        telInput.addEventListener('input', function() {
            const val = this.value.replace(/[\s.\-]/g, '');
            let isValid = /^0[1-9]\d{8}$/.test(val) || /^\+33[1-9]\d{8}$/.test(val);
            setStatus('tel', isValid, "Numéro invalide");
        });
    }

    // --- 5. Validation NOM ENTREPRISE (JS pur) ---
    const nomEntrepriseInput = document.getElementById('nom_entreprise');
    if (nomEntrepriseInput) {
        nomEntrepriseInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 2) err = "2 caractères minimum.";
            else if (val.length > 100) err = "100 caractères maximum.";
            else if (!/^[a-zA-Z0-9À-ÿ\s\-'.,&]+$/.test(val)) err = "Caractères invalides.";
            setStatus('nom_entreprise', err === "", err);
        });
    }

    // --- 6. Validation SIRET avec algorithme de Luhn (JS pur) ---
    const siretInput = document.getElementById('siret');
    if (siretInput) {
        siretInput.addEventListener('input', function() {
            const val = this.value.replace(/[\s]/g, '');
            let err = "";

            if (!/^\d{14}$/.test(val)) {
                err = "Le SIRET doit contenir 14 chiffres.";
            } else {
                // Algorithme de Luhn pour valider le SIRET
                let sum = 0;
                for (let i = 0; i < 14; i++) {
                    let digit = parseInt(val[i], 10);
                    // Position paire (index impair car on commence à 0) : on multiplie par 2
                    if (i % 2 === 1) {
                        digit *= 2;
                        if (digit > 9) digit -= 9;
                    }
                    sum += digit;
                }
                if (sum % 10 !== 0) {
                    err = "Numéro SIRET invalide (clé de contrôle incorrecte).";
                }
            }
            setStatus('siret', err === "", err);
        });
    }

    // --- 7. Validation NOM/PRÉNOM (JS pur) ---
    const nomInput = document.getElementById('nom');
    if (nomInput) {
        nomInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 2) err = "2 caractères minimum.";
            else if (val.length > 50) err = "50 caractères maximum.";
            else if (!/^[a-zA-ZÀ-ÿ\s\-']+$/.test(val)) err = "Caractères invalides.";
            setStatus('nom', err === "", err);
        });
    }

    const prenomInput = document.getElementById('prenom');
    if (prenomInput) {
        prenomInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 2) err = "2 caractères minimum.";
            else if (val.length > 50) err = "50 caractères maximum.";
            else if (!/^[a-zA-ZÀ-ÿ\s\-']+$/.test(val)) err = "Caractères invalides.";
            setStatus('prenom', err === "", err);
        });
    }

    // --- 8. Validation DATE DE NAISSANCE (JS pur) ---
    const dateNaissanceInput = document.getElementById('dateNaissance') || document.getElementById('date_naissance');
    if (dateNaissanceInput) {
        dateNaissanceInput.addEventListener('input', function() {
            const val = this.value;
            let err = "";
            if (!val) {
                err = "Date requise.";
            } else {
                const date = new Date(val);
                const today = new Date();
                const age = Math.floor((today - date) / (365.25 * 24 * 60 * 60 * 1000));
                if (age < 18) err = "Vous devez avoir au moins 18 ans.";
                else if (age > 120) err = "Date invalide.";
            }
            setStatus(this.id, err === "", err);
        });
    }

    // --- 9. Validation EMAIL pour modifProfil (id="mail") ---
    const mailInput = document.getElementById('mail');
    if (mailInput) {
        mailInput.addEventListener('input', debounce(function() {
            const value = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                setStatus('mail', false, "Format email invalide");
                return;
            }
            setStatus('mail', true, "");
        }, 500));
        mailInput.addEventListener('input', updateButtonState);
    }

    // --- Nom Rue ---
    const nomRueInput = document.getElementById('AdresseFactNomRue');
    if (nomRueInput) {
        nomRueInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 3) err = "3 caractères minimum.";
            else if (!/^[a-zA-Z0-9À-ÿ\s\-'.,]+$/.test(val)) err = "Caractères invalides.";
            setStatus('AdresseFactNomRue', err === "", err);
        });
    }

    // --- Numéro Rue (Validation spécifique comme en PHP) ---
    const numRueInput = document.getElementById('AdresseFactNumRue');
    if (numRueInput) {
        numRueInput.addEventListener('input', function() {
            const val = this.value.trim();
            // Regex : accepte chiffres seuls ou chiffres + bis/ter/a/b
            const regexNum = /^\d+\s?(bis|ter|quater|a|b|c|d)?$/i;
            
            let isValid = regexNum.test(val);
            if (val.length > 10) isValid = false;

            setStatus('AdresseFactNumRue', isValid, "Format invalide (ex: 12, 12bis)");
        });
    }

    // --- Code Postal (Validation stricte 5 chiffres + départements) ---
    const cpInput = document.getElementById('codePostalFacturation');
    if (cpInput) {
        cpInput.addEventListener('input', function() {
            const val = this.value.replace(/\s/g, ''); // Enlever espaces
            let err = "";

            if (!/^\d{5}$/.test(val)) {
                err = "Doit contenir 5 chiffres.";
            } else {
                // Vérif départements réalistes (01-95 ou 97-98)
                const dept = parseInt(val.substring(0, 2));
                if (dept < 1 || (dept > 95 && dept < 97)) {
                    err = "Code postal invalide.";
                }
            }
            setStatus('codePostalFacturation', err === "", err);
        });
    }

    // --- Ville ---
    const villeInput = document.getElementById('villeFacturation');
    if (villeInput) {
        villeInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 2) err = "2 caractères minimum.";
            else if (!/^[a-zA-ZÀ-ÿ\s\-']+$/.test(val)) err = "Caractères invalides (lettres, tirets).";
            setStatus('villeFacturation', err === "", err);
        });
    }

    // --- Complément (Optionnel mais vérifié si rempli) ---
    const complInput = document.getElementById('complementAdresseFatc');
    if (complInput) {
        complInput.addEventListener('input', function() {
            const val = this.value.trim();
            // Si vide c'est valide (car optionnel)
            if (val === "") {
                setStatus('complementAdresseFatc', true);
                return;
            }
            // Sinon on vérifie
            let isValid = /^[a-zA-Z0-9À-ÿ\s,.\-'\/]+$/.test(val) && val.length >= 2;
            setStatus('complementAdresseFatc', isValid, "Caractères invalides ou trop court.");
        });
    }

    // --- Adresse Siège Vendeur ---
    const nomRueSiegeInput = document.getElementById('AdresseSiegeNomRue');
    if (nomRueSiegeInput) {
        nomRueSiegeInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 3) err = "3 caractères minimum.";
            else if (!/^[a-zA-Z0-9À-ÿ\s\-'.,]+$/.test(val)) err = "Caractères invalides.";
            setStatus('AdresseSiegeNomRue', err === "", err);
        });
    }

    const numRueSiegeInput = document.getElementById('AdresseSiegeNumRue');
    if (numRueSiegeInput) {
        numRueSiegeInput.addEventListener('input', function() {
            const val = this.value.trim();
            const regexNum = /^\d+\s?(bis|ter|quater|a|b|c|d)?$/i;
            let isValid = regexNum.test(val);
            if (val.length > 10) isValid = false;
            setStatus('AdresseSiegeNumRue', isValid, "Format invalide (ex: 12, 12bis)");
        });
    }

    const complSiegeInput = document.getElementById('complementAdresseSiege');
    if (complSiegeInput) {
        complSiegeInput.addEventListener('input', function() {
            const val = this.value.trim();
            if (val === "") {
                setStatus('complementAdresseSiege', true);
                return;
            }
            let isValid = /^[a-zA-Z0-9À-ÿ\s,.\-'\/]+$/.test(val) && val.length >= 2;
            setStatus('complementAdresseSiege', isValid, "Caractères invalides ou trop court.");
        });
    }

    const cpSiegeInput = document.getElementById('codePostalSiege');
    if (cpSiegeInput) {
        cpSiegeInput.addEventListener('input', function() {
            const val = this.value.replace(/\s/g, '');
            let err = "";
            if (!/^\d{5}$/.test(val)) {
                err = "Doit contenir 5 chiffres.";
            } else {
                const dept = parseInt(val.substring(0, 2));
                if (dept < 1 || (dept > 95 && dept < 97)) {
                    err = "Code postal invalide.";
                }
            }
            setStatus('codePostalSiege', err === "", err);
        });
    }

    const villeSiegeInput = document.getElementById('villeSiege');
    if (villeSiegeInput) {
        villeSiegeInput.addEventListener('input', function() {
            const val = this.value.trim();
            let err = "";
            if (val.length < 2) err = "2 caractères minimum.";
            else if (!/^[a-zA-ZÀ-ÿ\s\-']+$/.test(val)) err = "Caractères invalides (lettres, tirets).";
            setStatus('villeSiege', err === "", err);
        });
    }


    // --- 5. Autres champs requis simples (Nom, Prénom, etc.) ---
    // Pour tous les autres inputs et textareas requis qui n'ont pas de validation spéciale,
    // on veut quand même vérifier s'ils sont vides pour le bouton.
    const otherFields = document.querySelectorAll('input[required], textarea[required]');
    otherFields.forEach(field => {
        field.addEventListener('input', updateButtonState);
    });


    // --- Fonctions Techniques ---

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function checkDatabase(field, value) {
        // On désactive le bouton PENDANT la vérification (optionnel mais recommandé)
        if(submitBtn) submitBtn.disabled = true;

        fetch('ajax_verif.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ field: field, value: value })
        })
        .then(response => response.json())
        .then(data => {
            setStatus(field, data.valide, data.erreur);
            // La mise à jour du bouton se fait dans setStatus
        })
        .catch(error => console.error('Erreur:', error));
    }

    // Appel initial pour désactiver le bouton au chargement de la page
    updateButtonState();
});