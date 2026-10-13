import axios from 'axios';

/**
 * How long a page visit (Inertia) waits for the server. On weak school Wi-Fi a request can stall
 * without ever failing: the page then kept loading forever. A visit that times out fails like an
 * unreachable server, so ConnectionLostNotice tries the page again by itself and otherwise says so.
 * Requests that set their own timeout (the exam page) and file uploads are left alone.
 */
export const PAGE_VISIT_TIMEOUT_MS = 15000;
export const FORM_SUBMIT_TIMEOUT_MS = 20000;

export function setupRequestTimeouts(): void {
    axios.interceptors.request.use((config) => {
        const isPageVisit = Boolean(config.headers?.get?.('X-Inertia'));
        const isUpload = typeof FormData !== 'undefined' && config.data instanceof FormData;

        if (isPageVisit && !isUpload && !config.timeout) {
            config.timeout = (config.method ?? 'get').toLowerCase() === 'get' ? PAGE_VISIT_TIMEOUT_MS : FORM_SUBMIT_TIMEOUT_MS;
        }

        return config;
    });
}
