/**
 * Sauvegarde automatique de la checklist de recette (debounce).
 */
(function () {
    const form = document.getElementById('recette-checklist-form');
    const saveUrl = window.recetteSaveUrl;
    const hint = document.getElementById('recette-save-hint');
    const progressBar = document.getElementById('recette-progress-bar');
    const progressText = document.getElementById('recette-progress-text');

    if (!form || !saveUrl) {
        return;
    }

    let debounceTimer = null;
    let inFlight = false;

    const setHint = (state, message) => {
        if (!hint) {
            return;
        }
        hint.classList.remove('is-saving', 'is-saved', 'is-error');
        hint.classList.add(state);
        hint.textContent = message;
    };

    const updateItemBorder = (select) => {
        const item = select.closest('.recette-item');
        if (item) {
            item.dataset.status = select.value;
        }
    };

    const updateProgress = (progress) => {
        if (!progress || !progressBar || !progressText) {
            return;
        }
        progressBar.style.width = `${progress.percent}%`;
        progressText.textContent = `${progress.valide}/${progress.total} validé(s) (${progress.percent} %)`;
    };

    const save = async () => {
        if (inFlight) {
            return;
        }
        inFlight = true;
        setHint('is-saving', 'Enregistrement…');

        const body = new FormData(form);

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Erreur lors de la sauvegarde');
            }

            updateProgress(data.progress);
            setHint('is-saved', `Enregistré à ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`);
        } catch (error) {
            setHint('is-error', error.message || 'Échec de l’enregistrement');
        } finally {
            inFlight = false;
        }
    };

    const scheduleSave = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(save, 800);
    };

    form.querySelectorAll('.recette-status-select').forEach((select) => {
        select.addEventListener('change', () => {
            updateItemBorder(select);
            scheduleSave();
        });
        updateItemBorder(select);
    });

    form.querySelectorAll('.recette-comment').forEach((textarea) => {
        textarea.addEventListener('input', scheduleSave);
    });

    const firstNameInput = form.querySelector('#tester_first_name');
    if (firstNameInput) {
        firstNameInput.addEventListener('change', scheduleSave);
    }
})();
