import { Controller } from '@hotwired/stimulus';

/**
 * Tableau d’interventions : lignes dynamiques, Select2 client, crédits filtrés par client.
 */
export default class extends Controller {
    static targets = ['tbody', 'prototype', 'empty'];

    static values = {
        index: Number,
    };

    connect() {
        if (!this.hasIndexValue) {
            this.indexValue = this.rowElements().length;
        }
        this.toggleEmpty();
        this.rowElements().forEach((row) => this.setupRow(row));
    }

    disconnect() {
        this.rowElements().forEach((row) => this.destroySelect2(row));
    }

    add(event) {
        event.preventDefault();
        if (!this.hasPrototypeTarget || !this.hasTbodyTarget) {
            return;
        }

        const prototypeHtml = this.prototypeTarget.dataset.prototype || '';
        if (prototypeHtml === '') {
            return;
        }

        const index = this.indexValue;
        const html = prototypeHtml.replace(/__name__/g, String(index));
        this.tbodyTarget.insertAdjacentHTML('beforeend', html);
        this.indexValue = index + 1;
        this.toggleEmpty();

        const lastRow = this.rowElements().at(-1);
        if (lastRow) {
            this.setupRow(lastRow);
            const entreprise = lastRow.querySelector('[data-bulk-entreprise]');
            if (entreprise && window.jQuery?.(entreprise).data('select2')) {
                window.jQuery(entreprise).select2('open');
            } else {
                entreprise?.focus();
            }
        }
    }

    remove(event) {
        event.preventDefault();
        const row = event.currentTarget.closest('[data-intervention-bulk-table-target="row"]');
        if (row) {
            this.destroySelect2(row);
            row.remove();
        }
        this.toggleEmpty();
    }

    onEntrepriseChange(event) {
        const row = event.currentTarget.closest('[data-intervention-bulk-table-target="row"]');
        if (row) {
            this.filterCredits(row);
        }
    }

    setupRow(row) {
        this.initSelect2(row);
        this.filterCredits(row);

        const entreprise = row.querySelector('[data-bulk-entreprise]');
        if (entreprise && entreprise.dataset.bulkEntrepriseBound !== '1') {
            entreprise.dataset.bulkEntrepriseBound = '1';
            entreprise.addEventListener('change', () => this.filterCredits(row));
            if (window.jQuery) {
                window.jQuery(entreprise).on('select2:select select2:clear change', () => this.filterCredits(row));
            }
        }
    }

    filterCredits(row) {
        const entreprise = row.querySelector('[data-bulk-entreprise]');
        const credit = row.querySelector('[data-bulk-credit]');
        if (!credit) {
            return;
        }

        const entrepriseId = entreprise?.value || '';
        let selectedStillValid = false;

        Array.from(credit.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const match = entrepriseId !== '' && option.dataset.entrepriseId === entrepriseId;
            option.hidden = !match;
            option.disabled = !match;
            if (match && option.selected) {
                selectedStillValid = true;
            }
        });

        if (!selectedStillValid) {
            credit.value = '';
        }

        // S’il n’y a qu’un seul crédit pour ce client, le présélectionner.
        if (entrepriseId !== '' && credit.value === '') {
            const visible = Array.from(credit.options).filter((o) => o.value && !o.disabled);
            if (visible.length === 1) {
                credit.value = visible[0].value;
            }
        }
    }

    initSelect2(row) {
        if (!window.jQuery?.fn?.select2) {
            return;
        }

        row.querySelectorAll('[data-bulk-entreprise-searchable]').forEach((select) => {
            if (select.dataset.select2Initialized === '1' || select.disabled) {
                return;
            }

            const $select = window.jQuery(select);
            $select.select2({
                width: '100%',
                placeholder: select.getAttribute('data-placeholder') || 'Rechercher un client…',
                allowClear: true,
                dropdownParent: window.jQuery(row),
            });
            select.dataset.select2Initialized = '1';
        });
    }

    destroySelect2(row) {
        if (!window.jQuery?.fn?.select2) {
            return;
        }

        row.querySelectorAll('[data-bulk-entreprise-searchable]').forEach((select) => {
            if (select.dataset.select2Initialized !== '1') {
                return;
            }

            const $select = window.jQuery(select);
            if ($select.data('select2')) {
                $select.off('select2:select select2:clear change');
                $select.select2('destroy');
            }
            delete select.dataset.select2Initialized;
            delete select.dataset.bulkEntrepriseBound;
        });
    }

    rowElements() {
        if (!this.hasTbodyTarget) {
            return [];
        }

        return Array.from(this.tbodyTarget.querySelectorAll('[data-intervention-bulk-table-target="row"]'));
    }

    toggleEmpty() {
        if (!this.hasEmptyTarget) {
            return;
        }
        this.emptyTarget.classList.toggle('hidden', this.rowElements().length > 0);
    }
}
