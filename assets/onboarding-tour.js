const DRIVER_CDN = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.5/dist/driver.js.iife.js';
const DRIVER_CSS = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.5/dist/driver.css';

let driverLoader = null;
let activeDriver = null;

const loadDriver = () => {
    if (window.driver && typeof window.driver.js?.driver === 'function') {
        return Promise.resolve(window.driver.js.driver);
    }
    if (typeof window.driver === 'function') {
        return Promise.resolve(window.driver);
    }
    if (driverLoader) {
        return driverLoader;
    }

    driverLoader = new Promise((resolve, reject) => {
        if (!document.querySelector('link[data-driver-css]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = DRIVER_CSS;
            link.dataset.driverCss = '1';
            document.head.appendChild(link);
        }

        const existing = document.querySelector('script[data-driver-js]');
        if (existing) {
            existing.addEventListener('load', () => {
                resolve(window.driver?.js?.driver || window.driver);
            });
            return;
        }

        const script = document.createElement('script');
        script.src = DRIVER_CDN;
        script.async = true;
        script.dataset.driverJs = '1';
        script.onload = () => resolve(window.driver?.js?.driver || window.driver);
        script.onerror = () => reject(new Error('Impossible de charger driver.js'));
        document.head.appendChild(script);
    });

    return driverLoader;
};

const postTourAction = async (url, token) => {
    const body = new URLSearchParams();
    body.set('_token', token);

    await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body,
        credentials: 'same-origin',
    });
};

const filterAvailableSteps = (steps) => steps.filter((step) => {
    if (!step.element) {
        return true;
    }

    return document.querySelector(step.element) !== null;
});

export const startOnboardingTour = async (options = {}) => {
    const root = document.querySelector('[data-onboarding-tour]');
    if (!root) {
        return;
    }

    let config;
    try {
        config = JSON.parse(root.dataset.onboardingTour || '{}');
    } catch {
        return;
    }

    const steps = filterAvailableSteps(config.steps || []);
    if (steps.length === 0) {
        return;
    }

    const createDriver = await loadDriver();
    if (typeof createDriver !== 'function') {
        return;
    }

    if (activeDriver) {
        try {
            activeDriver.destroy();
        } catch {
            // ignore
        }
        activeDriver = null;
    }

    const completeUrl = root.dataset.completeUrl;
    const csrf = root.dataset.csrfToken;
    const markComplete = async () => {
        if (!completeUrl || !csrf) {
            return;
        }
        try {
            await postTourAction(completeUrl, csrf);
            root.dataset.autoStart = '0';
        } catch {
            // ignore réseau
        }
    };

    activeDriver = createDriver({
        showProgress: true,
        animate: true,
        allowClose: true,
        overlayOpacity: 0.55,
        stagePadding: 8,
        stageRadius: 4,
        popoverClass: 'driverjs-theme',
        nextBtnText: 'Suivant',
        prevBtnText: 'Précédent',
        doneBtnText: 'Terminer',
        progressText: '{{current}} / {{total}}',
        steps: steps.map((step) => ({
            element: step.element,
            popover: {
                title: step.title,
                description: step.description,
                side: step.side || 'right',
                align: 'start',
            },
        })),
        onDestroyStarted: () => {
            if (!activeDriver) {
                return;
            }
            // Fermeture (croix, overlay, Terminer) : on marque comme vu.
            markComplete();
            activeDriver.destroy();
            activeDriver = null;
        },
    });

    activeDriver.drive(options.startIndex || 0);
};

export const initOnboardingTour = () => {
    const root = document.querySelector('[data-onboarding-tour]');
    if (!root) {
        return;
    }

    const helpButton = root.querySelector('[data-tour="help-button"]');
    if (helpButton && helpButton.dataset.tourClickBound !== '1') {
        helpButton.dataset.tourClickBound = '1';
        helpButton.addEventListener('click', (event) => {
            event.preventDefault();
            startOnboardingTour();
        });
    }

    if (root.dataset.autoStart === '1' && root.dataset.autoStarted !== '1') {
        root.dataset.autoStarted = '1';
        window.setTimeout(() => {
            startOnboardingTour();
        }, 600);
    }
};
