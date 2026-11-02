/**
 * Copies text to the clipboard. The modern API only exists on secure pages (HTTPS or localhost), so
 * on a plain http address — a LAN IP, for example — it falls back to a hidden field and `execCommand`.
 * Resolves true when the text was copied.
 */
window.copyText = async (text) => {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Falls through to the legacy way.
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
    document.body.appendChild(field);
    field.select();
    field.setSelectionRange(0, text.length);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    field.remove();

    return copied;
};
