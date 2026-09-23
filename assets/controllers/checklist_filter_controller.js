import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['query', 'item', 'empty'];

    filter() {
        const needle = this.normalize(this.queryTarget.value);
        let visibleCount = 0;

        this.itemTargets.forEach((item) => {
            const label = this.normalize(item.dataset.label || item.textContent || '');
            const match = needle === '' || label.includes(needle);
            item.classList.toggle('hidden', !match);
            if (match) {
                visibleCount += 1;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', visibleCount > 0 || needle === '');
        }
    }

    normalize(value) {
        return String(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }
}
