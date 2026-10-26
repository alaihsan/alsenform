/**
 * Copies text, also where navigator.clipboard is missing: browsers only offer it on HTTPS and
 * localhost, so on the intranet address (http://falcon.lan:2026 or an IP) the copy buttons need
 * the older way of copying a selected text. Resolves to whether the text was copied.
 */
export async function copyText(text: string): Promise<boolean> {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Refused (no permission, page not focused): try the older way below.
        }
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '0';
    field.style.left = '-9999px';
    field.style.opacity = '0';
    document.body.appendChild(field);

    const selection = document.getSelection();
    const previousRange = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;

    field.select();
    field.setSelectionRange(0, text.length);

    let isCopied = false;
    try {
        isCopied = document.execCommand('copy');
    } catch {
        isCopied = false;
    }

    document.body.removeChild(field);
    previousFocus?.focus({ preventScroll: true });
    if (selection && previousRange) {
        selection.removeAllRanges();
        selection.addRange(previousRange);
    }

    return isCopied;
}
