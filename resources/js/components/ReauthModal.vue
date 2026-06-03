<template>
    <transition name="reauth-fade">
        <div v-if="visible" class="reauth-overlay" @mousedown.self="cancel">
            <div class="reauth-card" role="dialog" aria-modal="true" aria-labelledby="reauth-title">
                <header class="reauth-header">
                    <h3 id="reauth-title">Session expired</h3>
                    <p>Re-enter your password to continue. Your work is paused, not lost.</p>
                </header>

                <form @submit.prevent="submit">
                    <div class="reauth-field">
                        <label for="reauth-email">Email</label>
                        <input id="reauth-email" type="email" :value="email" disabled />
                    </div>

                    <div class="reauth-field">
                        <label for="reauth-password">Password</label>
                        <input
                            id="reauth-password"
                            ref="passwordInput"
                            v-model="password"
                            type="password"
                            autocomplete="current-password"
                            :disabled="submitting"
                            required
                        />
                    </div>

                    <p v-if="errorMessage" class="reauth-error">{{ errorMessage }}</p>

                    <div class="reauth-actions">
                        <button type="button" class="reauth-btn ghost" :disabled="submitting" @click="cancel">
                            Sign out
                        </button>
                        <button type="submit" class="reauth-btn primary" :disabled="submitting || !password">
                            <span v-if="submitting">Verifying...</span>
                            <span v-else>Unlock</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </transition>
</template>

<script>
import axios from 'axios';
import { registerModalOpener } from '../auth/sessionGuard';

export default {
    name: 'ReauthModal',
    data() {
        return {
            visible: false,
            email: '',
            password: '',
            submitting: false,
            errorMessage: '',
            resolvePrompt: null,
        };
    },
    mounted() {
        registerModalOpener(({ email }) => {
            this.email = email || '';
            this.password = '';
            this.errorMessage = '';
            this.submitting = false;
            this.visible = true;
            this.$nextTick(() => this.$refs.passwordInput?.focus());
            return new Promise((resolve) => {
                this.resolvePrompt = resolve;
            });
        });
        window.addEventListener('keydown', this.onKeyDown);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.onKeyDown);
    },
    methods: {
        onKeyDown(e) {
            if (!this.visible) return;
            if (e.key === 'Escape') this.cancel();
        },
        finish(token) {
            this.visible = false;
            const resolver = this.resolvePrompt;
            this.resolvePrompt = null;
            if (resolver) resolver(token);
        },
        cancel() {
            if (this.submitting) return;
            this.finish(null);
        },
        async submit() {
            if (!this.password) return;
            this.submitting = true;
            this.errorMessage = '';
            try {
                const response = await axios.post(
                    '/api/v1/auth/reauth',
                    { email: this.email, password: this.password },
                    { __skipReauth: true }
                );
                const token = response?.data?.token;
                if (!token) {
                    this.errorMessage = 'Unexpected response from server.';
                    this.submitting = false;
                    return;
                }
                this.finish(token);
            } catch (err) {
                this.submitting = false;
                this.password = '';
                this.errorMessage =
                    err?.response?.data?.message ||
                    (err?.response?.status === 401
                        ? 'Wrong password. Try again or sign out.'
                        : 'Could not verify. Please try again.');
                this.$nextTick(() => this.$refs.passwordInput?.focus());
            }
        },
    },
};
</script>

<style scoped>
.reauth-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
    z-index: 12000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.reauth-card {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    padding: 28px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.reauth-header h3 {
    margin: 0 0 6px 0;
    color: #0f172a;
    font-size: 1.25rem;
}

.reauth-header p {
    margin: 0 0 18px 0;
    color: #475569;
    font-size: 0.92rem;
}

.reauth-field {
    margin-bottom: 14px;
}

.reauth-field label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #334155;
}

.reauth-field input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    background: #fff;
    color: #0f172a;
}

.reauth-field input:disabled {
    background: #f1f5f9;
    color: #64748b;
}

.reauth-field input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}

.reauth-error {
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    margin: 0 0 14px 0;
}

.reauth-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 6px;
}

.reauth-btn {
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background 0.15s ease, transform 0.05s ease;
}

.reauth-btn:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.reauth-btn.ghost {
    background: transparent;
    color: #475569;
}

.reauth-btn.ghost:hover:not(:disabled) {
    background: #f1f5f9;
}

.reauth-btn.primary {
    background: #4f46e5;
    color: #fff;
}

.reauth-btn.primary:hover:not(:disabled) {
    background: #4338ca;
}

.reauth-fade-enter-active,
.reauth-fade-leave-active {
    transition: opacity 0.15s ease;
}
.reauth-fade-enter-from,
.reauth-fade-leave-to {
    opacity: 0;
}
</style>
