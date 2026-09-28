(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;
    const params = new URLSearchParams(window.location.search);
    const certId = params.get('id') || params.get('slug');

    window.TMH.boot(init);

    async function init() {
        const record = certId ? await store.get('certificates', certId) : null;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Company', href: 'certificates' }, { label: 'Certificates', href: 'certificates' }, { label: record ? `Edit: ${record.title}` : 'New Certificate' }],
            title: record ? `Edit Certificate: ${record.title}` : 'Add Certificate',
            sub: 'Title, issuing authority, and badge image (URL or upload file).',
            actions: `<a href="certificates" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Back to Certificates</a>`,
        });

        document.getElementById('view').innerHTML = `
            <form id="certForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>Certificate Details</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'title', label: 'Certificate Title', required: true, value: record ? record.title : '' }),
                                F.text({ name: 'issuer', label: 'Issuing Authority / Govt Body', value: record ? record.issuer : '' }),
                                F.media({ name: 'image', label: 'Badge / Document Image (URL or Upload)', value: record ? record.image : '' }),
                                F.status({ value: record ? record.status : 'published' }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end">
                    <a href="certificates" class="btn btn--ghost">Cancel</a>
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Certificate</button>
                </div>
            </form>`;

        const formEl = document.getElementById('certForm');
        if (window.TMH.form) window.TMH.form.bind(formEl, record || {});
        if (window.TMH.media) window.TMH.media.paintAll(formEl, record || {});

        document.getElementById('certForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            if (!data.slug) data.slug = (U.slugify || U.slug)(data.title || '');

            try {
                if (certId) {
                    await store.update('certificates', certId, data);
                    toast.success('Certificate updated successfully');
                } else {
                    await store.create('certificates', data);
                    toast.success('Certificate added successfully');
                }

                setTimeout(() => {
                    window.location.href = 'certificates';
                }, 400);
            } catch (err) {
                toast.error(err.message || 'Failed to save certificate');
            }
        });
    }
}());
