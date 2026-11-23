(() => {
    const form = document.querySelector('[data-contact-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const fields = form.querySelector('[data-contact-fields]');
    const status = document.getElementById('contact-form-status');
    const submit = form.querySelector('button[type="submit"]');
    if (!(fields instanceof HTMLFieldSetElement) || !(status instanceof HTMLElement) || !(submit instanceof HTMLButtonElement)) {
        return;
    }

    fields.disabled = false;

    // Live "120 / 5000" counter under the message label.
    const counter = form.querySelector('[data-counter-for="contact-message"]');
    const messageBox = form.elements.namedItem('message');
    const updateCounter = () => {
        if (counter instanceof HTMLElement && messageBox instanceof HTMLTextAreaElement) {
            counter.textContent = `${messageBox.value.length} / ${messageBox.maxLength}`;
            counter.classList.toggle('is-near-limit', messageBox.value.length > messageBox.maxLength * 0.9);
        }
    };
    form.addEventListener('input', updateCounter);
    form.addEventListener('reset', () => setTimeout(updateCounter, 0));
    updateCounter();

    const fieldNames = ['name', 'email', 'message'];
    const clearErrors = () => {
        for (const name of fieldNames) {
            const control = form.elements.namedItem(name);
            const error = document.getElementById(`contact-${name}-error`);
            if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement) {
                control.removeAttribute('aria-invalid');
            }
            if (error instanceof HTMLElement) {
                error.textContent = '';
                error.hidden = true;
            }
        }
    };

    form.addEventListener('input', clearErrors);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();

        const token = form.querySelector('[name="cf-turnstile-response"]');
        if (!(token instanceof HTMLInputElement || token instanceof HTMLTextAreaElement) || token.value === '') {
            status.textContent = form.dataset.turnstileRequired || '';
            return;
        }

        submit.disabled = true;
        status.textContent = form.dataset.sendingMessage || '';
        const locale = form.dataset.locale || 'en';

        try {
            const response = await fetch(form.dataset.endpoint || '/api/v1/contact', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Accept-Language': locale,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    name: form.elements.namedItem('name').value.trim(),
                    email: form.elements.namedItem('email').value.trim(),
                    message: form.elements.namedItem('message').value.trim(),
                    locale,
                    turnstile_token: token.value,
                }),
            });
            const result = await response.json().catch(() => null);

            if (response.ok && typeof result?.data?.message === 'string') {
                status.textContent = result.data.message;
                form.reset();
                clearErrors();
            } else {
                const error = result?.error;
                if (error?.fields && typeof error.fields === 'object') {
                    for (const name of fieldNames) {
                        const message = error.fields[name];
                        const control = form.elements.namedItem(name);
                        const fieldError = document.getElementById(`contact-${name}-error`);
                        if (typeof message === 'string' && (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement)
                            && fieldError instanceof HTMLElement) {
                            control.setAttribute('aria-invalid', 'true');
                            fieldError.textContent = message;
                            fieldError.hidden = false;
                        }
                    }
                }
                status.textContent = typeof error?.message === 'string'
                    ? error.message
                    : form.dataset.networkError || '';
            }
            window.turnstile?.reset();
        } catch {
            status.textContent = form.dataset.networkError || '';
            window.turnstile?.reset();
        } finally {
            submit.disabled = false;
        }
    });
})();
