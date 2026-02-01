// Script spécifique à la page descriptionProduitClient.php
// Les toasts sont gérés par header.php via le système de session

document.addEventListener('DOMContentLoaded', function() {
    // Les votes utilisent maintenant la soumission PHP classique
    
    // Gestion de la modal de confirmation de suppression
    var btnOpenModal = document.getElementById('btn-open-modal-supprimer');
    var modalDelete = document.getElementById('modal-confirm-delete');
    var btnCancel = document.getElementById('btn-cancel-delete');
    var btnConfirm = document.getElementById('btn-confirm-delete');
    var formSupprimer = document.getElementById('form-supprimer-avis');
    
    if (btnOpenModal && modalDelete) {
        btnOpenModal.addEventListener('click', function() {
            modalDelete.style.display = 'flex';
            setTimeout(function() { modalDelete.classList.add('show'); }, 10);
        });
        
        function closeModalDelete() {
            modalDelete.classList.remove('show');
            setTimeout(function() { modalDelete.style.display = 'none'; }, 300);
        }
        
        if (btnCancel) btnCancel.addEventListener('click', closeModalDelete);
        
        var overlayDelete = modalDelete.querySelector('.modal-confirm-overlay');
        if (overlayDelete) overlayDelete.addEventListener('click', closeModalDelete);
        
        if (btnConfirm && formSupprimer) {
            btnConfirm.addEventListener('click', function() { formSupprimer.submit(); });
        }
    }
});

// Fonctions globales pour la modal de signalement
window.openSignalementModal = function(idAvis, idProduit) {
    var modal = document.getElementById('modal-signalement');
    var inputAvis = document.getElementById('modal-id-avis');
    var inputProduit = document.getElementById('modal-id-produit');
    var textarea = document.getElementById('raison-signalement');
    
    if (modal && inputAvis && inputProduit && textarea) {
        inputAvis.value = idAvis;
        inputProduit.value = idProduit;
        textarea.value = '';
        modal.style.display = 'flex';
        setTimeout(function() {
            modal.classList.add('show');
            textarea.focus();
        }, 10);
    }
};

window.closeSignalementModal = function() {
    var modal = document.getElementById('modal-signalement');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(function() { modal.style.display = 'none'; }, 300);
    }
};
