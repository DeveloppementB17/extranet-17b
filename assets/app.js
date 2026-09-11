import './stimulus_bootstrap.js';
// CSRF stateless (SameOriginCsrfTokenManager) : cookie + en-tête pour Turbo et navigateurs stricts (Firefox).
import './controllers/csrf_protection_controller.js';
import 'flowbite';
import ApexCharts from 'apexcharts';

const formatPrimaryDuration = (value) => {
    const minutes = Math.round(Number(value || 0));

    if (minutes > 180 || minutes < -180) {
        return `${(minutes / 60).toFixed(2)} h`;
    }

    return `${minutes} min`;
};

const formatAlternateDuration = (value) => {
    const minutes = Math.round(Number(value || 0));

    if (minutes > 180 || minutes < -180) {
        return `${minutes} min`;
    }

    return `${(minutes / 60).toFixed(2)} h`;
};

const initStaffClientSwitcher = () => {
    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
        return;
    }

    document.querySelectorAll('[data-staff-client-switcher]').forEach((switcher) => {
        if (switcher.dataset.select2Initialized === '1') {
            return;
        }

        const $switcher = window.jQuery(switcher);
        $switcher.select2({
            width: 'resolve',
            placeholder: 'Sélectionner une entreprise',
            allowClear: true,
        });

        $switcher.on('select2:select', () => {
            const form = switcher.closest('form');
            if (form) {
                form.submit();
            }
        });
        $switcher.on('select2:clear', () => {
            const form = switcher.closest('form');
            if (form) {
                form.submit();
            }
        });

        switcher.dataset.select2Initialized = '1';
    });
};

const destroyStaffClientSwitcher = () => {
    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
        return;
    }

    document.querySelectorAll('[data-staff-client-switcher]').forEach((switcher) => {
        if (switcher.dataset.select2Initialized !== '1') {
            return;
        }

        const $switcher = window.jQuery(switcher);
        if ($switcher.data('select2')) {
            $switcher.select2('destroy');
        }

        delete switcher.dataset.select2Initialized;
    });
};

const dismissFlashMessage = (message) => {
    if (!message || message.dataset.flashDismissing === '1') {
        return;
    }

    message.dataset.flashDismissing = '1';
    message.classList.add('opacity-0');
    window.setTimeout(() => message.remove(), 300);
};

const initFlashMessages = () => {
    document.querySelectorAll('[data-flash-message]').forEach((message) => {
        if (message.dataset.flashInitialized === '1') {
            return;
        }

        const dismissButton = message.querySelector('[data-flash-dismiss]');
        dismissButton?.addEventListener('click', () => dismissFlashMessage(message));

        if (message.hasAttribute('data-flash-auto-dismiss')) {
            window.setTimeout(() => dismissFlashMessage(message), 7000);
        }

        message.dataset.flashInitialized = '1';
    });
};

const initTimeCreditDonutChart = () => {
    const chartElements = document.querySelectorAll('[data-time-credit-donut-chart]');
    if (chartElements.length === 0) {
        return;
    }

    chartElements.forEach((chartElement) => {
        if (chartElement.dataset.chartInitialized === '1') {
            return;
        }

        const remainingMinutes = Number(chartElement.dataset.remainingMinutes || 0);
        const consumedMinutes = Number(chartElement.dataset.consumedMinutes || 0);
        const totalMinutes = Number(chartElement.dataset.totalMinutes || 0);
        const chartHeight = Number(chartElement.dataset.chartHeight || 320);

        const chart = new ApexCharts(chartElement, {
            series: [consumedMinutes, remainingMinutes],
            labels: ['Consommé', 'Disponible'],
            colors: ['#000000', '#D9D9D9'],
            chart: {
                height: chartHeight,
                type: 'donut',
                fontFamily: 'Inter, sans-serif',
            },
            stroke: {
                colors: ['transparent'],
            },
            dataLabels: {
                enabled: false,
            },
            legend: {
                position: 'bottom',
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                offsetY: 22,
                            },
                            value: {
                                show: true,
                                offsetY: -18,
                                formatter: (value) => formatPrimaryDuration(value),
                            },
                            total: {
                                show: true,
                                showAlways: true,
                                label: 'Total',
                                formatter: () => formatPrimaryDuration(totalMinutes),
                            },
                        },
                    },
                },
            },
            tooltip: {
                y: {
                    formatter: (value) => `${formatPrimaryDuration(value)} (${formatAlternateDuration(value)})`,
                },
            },
        });

        chart.render();
        chartElement.dataset.chartInitialized = '1';
    });
};

document.addEventListener('turbo:load', () => {
    initFlashMessages();
    initStaffClientSwitcher();
    initTimeCreditDonutChart();
});
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('[data-flash-message]').forEach((message) => message.remove());
});
document.addEventListener('turbo:before-cache', destroyStaffClientSwitcher);
