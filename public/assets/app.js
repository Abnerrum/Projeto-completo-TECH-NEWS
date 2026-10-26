document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm) {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    }
});


document.addEventListener('click', async function (event) {
    if (!(event.target instanceof Element)) {
        return;
    }

    const copyButton = event.target.closest('[data-copy-page-url]');
    if (copyButton) {
        const status = document.querySelector('[data-share-status]');
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(window.location.href);
            } else {
                const helper = document.createElement('textarea');
                helper.value = window.location.href;
                helper.setAttribute('readonly', '');
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                const copied = document.execCommand('copy');
                helper.remove();
                if (!copied) {
                    throw new Error('Não foi possível copiar automaticamente.');
                }
            }
            if (status) {
                status.textContent = 'Link copiado. Agora você pode compartilhar a reportagem.';
            }
        } catch (error) {
            if (status) {
                status.textContent = 'Não foi possível copiar. Copie o endereço diretamente da barra do navegador.';
            }
        }
        return;
    }

    const whatsappButton = event.target.closest('[data-share-whatsapp]');
    if (whatsappButton) {
        const title = whatsappButton.getAttribute('data-share-title') || document.title;
        const message = title + ' — TECH NEWS: ' + window.location.href;
        const shareUrl = 'https://wa.me/?text=' + encodeURIComponent(message);
        window.open(shareUrl, '_blank', 'noopener,noreferrer');
    }
});
