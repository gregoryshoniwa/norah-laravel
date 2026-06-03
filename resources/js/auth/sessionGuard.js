/**
 * Global session guard for the dashboard.
 *
 * Behavior:
 *  - Axios responses are intercepted; when a protected request returns 401,
 *    the interceptor pauses, asks the ReauthModal to collect the user's
 *    password, calls /api/v1/auth/reauth, updates the stored token, then
 *    retries the original request with the new token.
 *  - If the user cancels the modal or the re-auth call itself fails, the
 *    user is logged out and sent to /login. The original promise rejects
 *    so calling code receives the error and stops.
 *  - Concurrent 401s share a single modal: while one prompt is open, every
 *    other failing request awaits the same promise.
 */

import axios from 'axios';

let modalOpener = null;
let routerRef = null;
let pendingReauth = null; // shared Promise<string|null> while a prompt is open

const STORAGE_TOKEN = 'authToken';
const STORAGE_USER = 'user';

function readStoredEmail() {
    try {
        const raw = localStorage.getItem(STORAGE_USER);
        if (!raw) return null;
        const u = JSON.parse(raw);
        return u?.email || null;
    } catch (e) {
        return null;
    }
}

function applyToken(token) {
    if (!token) return;
    localStorage.setItem(STORAGE_TOKEN, token);
    window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

function clearAuthAndRedirect() {
    localStorage.removeItem(STORAGE_TOKEN);
    localStorage.removeItem('refreshToken');
    localStorage.removeItem(STORAGE_USER);
    delete window.axios.defaults.headers.common['Authorization'];
    delete axios.defaults.headers.common['Authorization'];
    if (routerRef) {
        const currentPath = routerRef.currentRoute?.value?.fullPath || '/';
        if (!currentPath.startsWith('/login')) {
            routerRef.push({ path: '/login', query: { next: currentPath } });
        }
    } else {
        window.location.href = '/login';
    }
}

/**
 * Open the re-auth modal once and resolve with the new token, or null if
 * the user cancelled / re-auth failed. Concurrent callers share the same
 * promise, so we only ever show one modal.
 */
function ensureReauth() {
    if (pendingReauth) return pendingReauth;
    if (!modalOpener) {
        clearAuthAndRedirect();
        return Promise.resolve(null);
    }

    const email = readStoredEmail();
    if (!email) {
        clearAuthAndRedirect();
        return Promise.resolve(null);
    }

    pendingReauth = modalOpener({ email })
        .then((token) => {
            pendingReauth = null;
            if (token) {
                applyToken(token);
                return token;
            }
            clearAuthAndRedirect();
            return null;
        })
        .catch(() => {
            pendingReauth = null;
            clearAuthAndRedirect();
            return null;
        });

    return pendingReauth;
}

/** Public: ReauthModal calls this once it's ready. */
export function registerModalOpener(fn) {
    modalOpener = fn;
}

/** Public: app.js calls this with the Vue Router instance after init. */
export function registerRouter(router) {
    routerRef = router;
}

/** Public: install axios response interceptor. Idempotent. */
let installed = false;
export function installAxiosSessionGuard() {
    if (installed) return;
    installed = true;

    const onResponseError = async (error) => {
        const status = error?.response?.status;
        const config = error?.config || {};

        if (status !== 401 || config.__skipReauth) {
            return Promise.reject(error);
        }

        // The re-auth endpoint itself returning 401 means wrong password -
        // never recurse on it.
        const url = String(config.url || '');
        if (url.includes('/auth/reauth') || url.includes('/auth/sign-in')) {
            return Promise.reject(error);
        }

        const newToken = await ensureReauth();
        if (!newToken) {
            return Promise.reject(error);
        }

        // Retry the original request once, with the new token and a guard
        // flag so a second 401 falls straight through to the caller.
        const retryConfig = {
            ...config,
            __skipReauth: true,
            headers: {
                ...(config.headers || {}),
                Authorization: `Bearer ${newToken}`,
            },
        };
        return axios(retryConfig);
    };

    axios.interceptors.response.use((r) => r, onResponseError);
    if (window.axios && window.axios !== axios) {
        window.axios.interceptors.response.use((r) => r, onResponseError);
    }
}
