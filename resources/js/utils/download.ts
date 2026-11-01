import axios from 'axios';

/** The file name in a Content-Disposition header, or the fallback. */
function filenameOf(disposition: string | undefined, fallback: string): string {
    const encoded = disposition?.match(/filename\*=utf-8''([^;]+)/i)?.[1];
    if (encoded) {
        return decodeURIComponent(encoded);
    }

    return disposition?.match(/filename="?([^";]+)"?/i)?.[1] ?? fallback;
}

/**
 * Save a file the server makes (a GET, or a POST of the data given) without leaving the page, so
 * a refusal can be shown on it. Gives the response headers; throws an Error with the message to
 * show when it fails.
 */
export async function downloadFile(url: string, options: { data?: Record<string, unknown>; fallbackName: string }): Promise<Record<string, string>> {
    let response;
    try {
        response = options.data ? await axios.post(url, options.data, { responseType: 'blob' }) : await axios.get(url, { responseType: 'blob' });
    } catch (error: any) {
        // The server's message comes as a Blob too.
        let message = '';
        try {
            message = JSON.parse(await (error?.response?.data as Blob).text())?.message ?? '';
        } catch {
            // No message: a network error or a page instead of JSON.
        }
        throw new Error(message || 'Berkas gagal dibuat. Periksa koneksi lalu coba lagi.');
    }

    const link = document.createElement('a');
    link.href = URL.createObjectURL(response.data as Blob);
    link.download = filenameOf(response.headers['content-disposition'], options.fallbackName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 10_000);

    return response.headers as Record<string, string>;
}
