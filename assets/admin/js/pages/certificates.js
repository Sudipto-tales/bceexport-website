(function () {
    'use strict';

    const { util: U, store, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Company' }, { label: 'Certificates' }],
            title: 'Certifications & Credentials',
            sub: 'MSME, APEDA, Import Export Code (IEC), Trademark certificates.',
            actions: `
                <a href="certificate-form" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Add Certificate</a>`,
        });

        await render();
    }

    async function render() {
        const certs = await store.all('certificates');

        document.getElementById('view').innerHTML = `
            <div class="grid grid--4 gap-4">
                ${certs.map((c) => `
                    <article class="card anim-item text-center">
                        <div class="card__body col items-center gap-3">
                            ${c.image ? `<img src="${U.esc(c.image)}" alt="" style="width:100%;height:140px;object-fit:contain;border-radius:var(--radius-sm);background:var(--surface-2);padding:8px">` : `<div style="width:100%;height:140px;display:grid;place-items:center;background:var(--surface-2);border-radius:var(--radius-sm)"><i class="fa-solid fa-certificate" style="font-size:40px;color:var(--text-mid)"></i></div>`}
                            <div>
                                <h4 class="m-0">${U.esc(c.title)}</h4>
                                <small class="muted">${U.esc(c.issuer || '')}</small>
                            </div>
                            <div class="row gap-2 justify-center pt-2 border-top w-full">
                                <a href="certificate-form?id=${U.esc(c.id)}" class="icon-btn" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(c.id)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('')}
            </div>`;

        U.stagger(document.getElementById('view'));

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const ok = await window.TMH.confirm({
                    title: 'Delete certificate?',
                    body: 'Are you sure you want to remove this certificate badge?',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('certificates', btn.dataset.id);
                toast.success('Certificate removed');
                render();
            });
        });
    }
}());
