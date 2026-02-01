class ConfirmDialog {
    constructor(options) {
        /**
         * 1. Fusion des options
         */
        const defaultOptions = {
            lang: 'english',
            subtitle: undefined,
            message: undefined,
            okText: undefined,
            cancelText: undefined 
        };
        this.options = Object.assign(defaultOptions, options);

        /**
         * 2. Dictionnaire de langues
         */
        this.language = {
            english: { message: "Confirm this action?", ok: "OK", cancel: "Cancel" },
            spanish: { message: "¿Confirmar esta acción?", ok: "Aceptar", cancel: "Cancelar" },
            german: { message: "Diese Aktion bestätigen?", ok: "OK", cancel: "Abbrechen" },
            french: { message: "Confirmer cette action ?", ok: "OK", cancel: "Annuler" },
            portuguese: { message: "Confirmar esta ação?", ok: "OK", cancel: "Cancelar" }
        };

        // On récupère les textes par défaut de la langue
        const langData = this.language[this.options.lang] || this.language.english;

        /**
         * 3. Création des éléments (SANS DOUBLONS)
         */
        this.background = document.createElement('div');
        this.message = document.createElement('div');
        this.subtitle = document.createElement('div');
        this.actions = document.createElement('div');
        this.confirmButton = document.createElement('button');
        this.cancelButton = document.createElement('button');

        // ATTRIBUTION DES TEXTES (La priorité est donnée aux options)
        this.message.textContent = this.options.message ?? langData.message;
        this.subtitle.textContent = this.options.subtitle ?? '';
        this.confirmButton.textContent = this.options.okText ?? langData.ok;
        this.cancelButton.textContent = this.options.cancelText ?? langData.cancel;

        /**
         * 4. Styles
         */
        this.background.style.position = 'fixed';
        this.background.style.display = 'flex';        
        this.background.style.justifyContent = 'center';
        this.background.style.alignItems = 'center';
        this.background.style.top = '0';
        this.background.style.left = '0';
        this.background.style.width = '100%';
        this.background.style.height = '100%';
        this.background.style.zIndex = '9999';
        this.background.style.backgroundColor = 'rgba(0, 0, 0, 0.4)';

        this.message.style.boxSizing = 'border-box';
        this.message.style.position = 'relative';
        this.message.style.display = 'flex';
        this.message.style.flexDirection = 'column';
        this.message.style.alignItems = 'center';
        this.message.style.justifyContent = 'center';
        this.message.style.gap = '25px';
        this.message.style.backgroundColor = '#f2f2f2';
        this.message.style.borderRadius = '5px';
        this.message.style.fontSize = '1.4rem';
        this.message.style.width = '350px';
        this.message.style.minHeight = '150px';
        this.message.style.margin = 'auto';
        this.message.style.padding = '30px';
        this.message.style.transition = 'transform 0.3s ease-in-out, opacity 0.3s ease-in-out';
        this.message.style.transform = 'translateY(-25px)';
        this.message.style.opacity = '0.3';

        this.actions.style.display = 'flex';
        this.actions.style.justifyContent = 'end';
        this.actions.style.gap = '15px';
        this.actions.style.marginTop = '10px';

        this.subtitle.style.fontSize = '1.1rem';

        this.confirmButton.style.padding = '8px 16px';
        this.confirmButton.style.borderRadius = '4px';
        this.confirmButton.style.backgroundColor = '#FFB75A';
        this.confirmButton.style.color = '#fff';
        this.confirmButton.style.border = "solid black 1px";
        this.confirmButton.style.cursor = 'pointer';
        this.confirmButton.style.fontSize = '1.05rem';

        this.cancelButton.style.padding = '8px 16px';
        this.cancelButton.style.borderRadius = '4px';
        this.cancelButton.style.backgroundColor = '#ffffff';
        this.cancelButton.style.color = '#000000';
        this.cancelButton.style.border = "solid black 1px";
        this.cancelButton.style.cursor = 'pointer';
        this.cancelButton.style.fontSize = '1.05rem';

        /**
         * 5. Événements Hover
         */
        this.confirmButton.addEventListener('mouseover', () => this.confirmButton.style.backgroundColor = '#d3984aff');
        this.confirmButton.addEventListener('mouseout', () => this.confirmButton.style.backgroundColor = '#FFB75A');
        this.cancelButton.addEventListener('mouseover', () => this.cancelButton.style.backgroundColor = '#c6c3c3ff');
        this.cancelButton.addEventListener('mouseout', () => this.cancelButton.style.backgroundColor = '#ffffff');
    }

    // Garde tes méthodes addToDOM(), show() et resolvePromise() telles quelles...
    addToDOM() {       
        this.background.appendChild(this.message);
        this.message.appendChild(this.actions);
        this.actions.appendChild(this.cancelButton);
        this.actions.appendChild(this.confirmButton);
        if (this.options.subtitle) {
            this.subtitle.textContent = this.options.subtitle;
            this.message.insertBefore(this.subtitle, this.actions);
        }
        document.body.appendChild(this.background);
        setTimeout(() => {
            this.message.style.transform = 'translateY(0px)';
            this.message.style.opacity = '1';
        }, 10);
    }

    show(subtitle) {
        if (subtitle) this.options.subtitle = subtitle;
        this.addToDOM();
        return new Promise((resolve) => {
            this.confirmButton.onclick = () => this.resolvePromise(resolve, { ok: true });
            this.cancelButton.onclick = () => this.resolvePromise(resolve, { ok: false });
        });
    }

    resolvePromise(resolve, status) {
        this.message.style.transform = 'translateY(-25px)';
        this.message.style.opacity = '0.3';
        setTimeout(() => {
            if (this.background.parentNode) document.body.removeChild(this.background);
            resolve(status);
        }, 300);
    }
}