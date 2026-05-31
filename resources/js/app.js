import './bootstrap';

import Alpine from 'alpinejs';

const hasUppercase = (value) => /[A-Z]/.test(value);
const hasLowercase = (value) => /[a-z]/.test(value);
const hasNumber = (value) => /\d/.test(value);
const hasSymbol = (value) => /[^A-Za-z0-9]/.test(value);

window.passwordSecurityForm = ({ initialPassword = '', initialConfirmation = '' } = {}) => ({
    password: initialPassword,
    passwordConfirmation: initialConfirmation,
    attemptedSubmit: false,

    get rules() {
        return [
            {
                key: 'length',
                label: 'Minimo 10 caracteres',
                valid: this.password.length >= 10,
            },
            {
                key: 'uppercase',
                label: 'Al menos una mayuscula',
                valid: hasUppercase(this.password),
            },
            {
                key: 'lowercase',
                label: 'Al menos una minuscula',
                valid: hasLowercase(this.password),
            },
            {
                key: 'number',
                label: 'Al menos un numero',
                valid: hasNumber(this.password),
            },
            {
                key: 'symbol',
                label: 'Al menos un caracter especial',
                valid: hasSymbol(this.password),
            },
        ];
    },

    get passwordIsValid() {
        return this.rules.every((rule) => rule.valid);
    },

    get confirmationIsComplete() {
        return this.passwordConfirmation.length > 0;
    },

    get confirmationMatches() {
        return this.password === this.passwordConfirmation;
    },

    get canSubmit() {
        return this.passwordIsValid && this.confirmationIsComplete && this.confirmationMatches;
    },

    get confirmationMessage() {
        if (!this.confirmationIsComplete) {
            return 'Confirma tu password para continuar.';
        }

        return this.confirmationMatches
            ? 'Las passwords coinciden.'
            : 'La confirmacion no coincide con la password.';
    },

    submitIfValid(event) {
        this.attemptedSubmit = true;

        if (this.canSubmit) {
            return;
        }

        event.preventDefault();
    },
});

window.loginLockoutForm = ({ initialEmail, initialSecondsRemaining, initialLocked, statusUrl }) => ({
    email: initialEmail,
    locked: initialLocked,
    secondsRemaining: Number(initialSecondsRemaining) || 0,
    statusUrl,
    countdownIntervalId: null,

    get formattedCountdown() {
        const minutes = String(Math.floor(this.secondsRemaining / 60)).padStart(2, '0');
        const seconds = String(this.secondsRemaining % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },

    init() {
        this.syncCountdown();
    },

    preventIfLocked(event) {
        if (!this.locked) {
            return;
        }

        event.preventDefault();
    },

    syncCountdown() {
        window.clearInterval(this.countdownIntervalId);

        if (!this.locked || this.secondsRemaining <= 0) {
            this.locked = false;
            this.secondsRemaining = 0;

            return;
        }

        this.countdownIntervalId = window.setInterval(() => {
            if (this.secondsRemaining <= 1) {
                window.clearInterval(this.countdownIntervalId);
                this.locked = false;
                this.secondsRemaining = 0;

                return;
            }

            this.secondsRemaining -= 1;
        }, 1000);
    },

    async refreshStatus() {
        try {
            const response = await window.axios.get(this.statusUrl, {
                params: {
                    email: this.email,
                },
            });

            this.locked = Boolean(response.data.locked);
            this.secondsRemaining = Number(response.data.seconds_remaining) || 0;
            this.syncCountdown();
        } catch {
            // Ignore transient UI polling failures and keep backend protection as source of truth.
        }
    },
});

window.sessionInactivityGuard = ({ enabled, modalTimeoutSeconds, warningTimeoutSeconds, heartbeatUrl, logoutUrl, csrfToken }) => ({
    enabled,
    modalTimeoutSeconds,
    warningTimeoutSeconds,
    heartbeatUrl,
    logoutUrl,
    csrfToken,
    showPrompt: false,
    countdownSeconds: 0,
    idleTimerId: null,
    promptTimerId: null,
    countdownIntervalId: null,
    heartbeatTimerId: null,

    get promptCountdown() {
        return `${this.countdownSeconds} segundo${this.countdownSeconds === 1 ? '' : 's'}`;
    },

    init() {
        if (!this.enabled) {
            return;
        }

        this.registerListeners();
        this.startHeartbeat();
        this.resetIdleTimer();
    },

    registerListeners() {
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach((eventName) => {
            window.addEventListener(eventName, () => this.handleActivity(), { passive: true });
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.handleActivity();
            }
        });
    },

    handleActivity() {
        if (!this.enabled) {
            return;
        }

        if (this.showPrompt) {
            return;
        }

        this.resetIdleTimer();
    },

    resetIdleTimer() {
        window.clearTimeout(this.idleTimerId);

        this.idleTimerId = window.setTimeout(() => {
            this.openPrompt();
        }, this.modalTimeoutSeconds * 1000);
    },

    openPrompt() {
        this.showPrompt = true;
        this.countdownSeconds = this.warningTimeoutSeconds;

        window.clearTimeout(this.promptTimerId);
        window.clearInterval(this.countdownIntervalId);

        this.countdownIntervalId = window.setInterval(() => {
            if (this.countdownSeconds <= 1) {
                window.clearInterval(this.countdownIntervalId);
                this.countdownSeconds = 0;

                return;
            }

            this.countdownSeconds -= 1;
        }, 1000);

        this.promptTimerId = window.setTimeout(() => {
            this.logoutNow();
        }, this.warningTimeoutSeconds * 1000);
    },

    async stayActive() {
        this.showPrompt = false;
        window.clearTimeout(this.promptTimerId);
        window.clearInterval(this.countdownIntervalId);
        this.countdownSeconds = 0;

        await this.sendHeartbeat();
        this.resetIdleTimer();
    },

    async sendHeartbeat() {
        try {
            await window.axios.post(this.heartbeatUrl);
        } catch (error) {
            if (error?.response?.status === 401 || error?.response?.status === 419) {
                this.logoutNow();
            }
        }
    },

    startHeartbeat() {
        this.heartbeatTimerId = window.setInterval(() => {
            if (this.showPrompt) {
                return;
            }

            this.sendHeartbeat();
        }, 60000);
    },

    logoutNow() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = this.logoutUrl;

        const csrfField = document.createElement('input');
        csrfField.type = 'hidden';
        csrfField.name = '_token';
        csrfField.value = this.csrfToken;

        form.appendChild(csrfField);
        document.body.appendChild(form);
        form.submit();
    },
});

window.Alpine = Alpine;

Alpine.start();
