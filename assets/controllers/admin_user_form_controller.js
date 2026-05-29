import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['managedBlock', 'roleSelect', 'entrepriseSelect'];

    static values = {
        agencyEntrepriseId: Number,
    };

    connect() {
        this.toggle();
    }

    toggle() {
        const role = this.roleSelectTarget.value;
        const is17bStaff = role === 'ROLE_17B_ADMIN' || role === 'ROLE_17B_USER';
        const showManaged = role === 'ROLE_17B_USER';

        this.managedBlockTarget.classList.toggle('hidden', !showManaged);

        this.managedBlockTarget.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.disabled = !showManaged;
        });

        if (is17bStaff && this.hasAgencyEntrepriseIdValue && this.hasEntrepriseSelectTarget) {
            this.entrepriseSelectTarget.value = String(this.agencyEntrepriseIdValue);
        }
    }
}
