import './stimulus_bootstrap.js';
// CSRF stateless (SameOriginCsrfTokenManager) : cookie + en-tête pour Turbo et navigateurs stricts (Firefox).
import './controllers/csrf_protection_controller.js';
import 'flowbite';
import ApexCharts from 'apexcharts';

const formatHoursMinutes = (value) => {
    const minutes = Math.round(Number(value || 0));
    const sign = minutes < 0 ? '-' : '';
    const absolute = Math.abs(minutes);
    const hours = Math.floor(absolute / 60);
    const remainingMinutes = absolute % 60;

    return `${sign}${hours}h${String(remainingMinutes).padStart(2, '0')}`;
};

const formatPrimaryDuration = (value) => {
    const minutes = Math.round(Number(value || 0));

    if (Math.abs(minutes) >= 60) {
        return formatHoursMinutes(minutes);
    }

    return `${minutes}mn`;
};

const formatAlternateDuration = (value) => {
    const minutes = Math.round(Number(value || 0));

    if (Math.abs(minutes) >= 60) {
        return `${minutes}mn`;
    }

    return formatHoursMinutes(minutes);
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
            colors: ['#111111', '#75c66a'],
            chart: {
                height: chartHeight,
                type: 'pie',
                fontFamily: 'Inter, sans-serif',
                background: 'transparent',
                dropShadow: {
                    enabled: true,
                    top: 6,
                    left: 0,
                    blur: 12,
                    color: '#75c66a',
                    opacity: 0.18,
                },
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['#ffffff'],
            },
            dataLabels: {
                enabled: true,
                formatter: (_percent, opts) => {
                    // Uniquement sur la part verte « Disponible » (index 1).
                    if (opts.seriesIndex !== 1) {
                        return '';
                    }

                    const minutes = Number(opts.w.config.series[opts.seriesIndex] || 0);
                    if (minutes <= 0) {
                        return '';
                    }

                    return formatPrimaryDuration(minutes);
                },
                style: {
                    fontSize: '16px',
                    fontFamily: 'Inter, sans-serif',
                    fontWeight: 700,
                    // Index 0 = consommé (label masqué), index 1 = disponible.
                    colors: ['#ffffff', '#194122'],
                },
                dropShadow: {
                    enabled: false,
                },
            },
            legend: {
                position: 'bottom',
                fontSize: '13px',
                fontWeight: 600,
                labels: {
                    colors: '#334155',
                },
                markers: {
                    size: 8,
                    strokeWidth: 0,
                    offsetX: -2,
                },
                itemMargin: {
                    horizontal: 14,
                    vertical: 4,
                },
            },
            plotOptions: {
                pie: {
                    expandOnClick: false,
                    offsetY: -4,
                    dataLabels: {
                        offset: -10,
                        minAngleToShowLabel: 18,
                    },
                },
            },
            states: {
                hover: {
                    filter: {
                        type: 'lighten',
                        value: 0.04,
                    },
                },
                active: {
                    filter: {
                        type: 'none',
                    },
                },
            },
            tooltip: {
                theme: 'light',
                style: {
                    fontSize: '13px',
                },
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
