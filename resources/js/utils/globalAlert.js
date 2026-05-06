export function showGlobalAlert(message, type = 'info') {
    window.dispatchEvent(
        new CustomEvent('ocn:alert', {
            detail: { message, type },
        }),
    );
}

