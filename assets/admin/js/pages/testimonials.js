(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Company' }, { label: 'Testimonials' }],
            title: 'Client Testimonials',
            sub: 'Client reviews, ratings, and testimonials displayed on the home page.',
            actions: `
                <button type="button" class="btn btn--primary" id="addTstBtn">
                    <i class="fa-solid fa-plus"></i> Add Testimonial</button>`,
        });

        document.getElementById('addTstBtn').addEventListener('click', () => edit(null));
        await render();
    }

    async function render() {
        const testimonials = await store.all('testimonials');

        document.getElementById('view').innerHTML = `
            <div class="grid grid--3 gap-4">
                ${testimonials.map((t) => `
                    <article class="card anim-item">
                        <div class="card__body col gap-3">
                            <div class="row gap-2 text-gold">
                                ${'<i class="fa-solid fa-star"></i>'.repeat(t.rating || 5)}
                            </div>
                            <p class="text-sm m-0 italic">"${U.esc(t.text)}"</p>
                            <div class="row gap-3 items-center pt-2 border-top">
                                ${t.photo ? `<img src="${U.esc(t.photo)}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:50%">` : `<span class="avatar" style="width:40px;height:40px;display:grid;place-items:center;border-radius:50%">${U.esc(U.initials(t.name))}</span>`}
                                <div class="grow">
                                    <b class="text-sm">${U.esc(t.name)}</b><br>
                                    <small class="muted">${U.esc(t.company || t.role || '')}</small>
                                </div>
                                <button type="button" class="icon-btn" data-act="edit" data-id="${U.esc(t.id)}"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(t.id)}"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('')}
            </div>`;

        U.stagger(document.getElementById('view'));

        document.getElementById('view').querySelectorAll('[data-act="edit"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const t = testimonials.find((item) => item.id === btn.dataset.id);
                if (t) edit(t);
            });
        });

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const ok = await window.TMH.confirm({
                    title: 'Delete testimonial?',
                    body: 'Are you sure you want to remove this client review?',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('testimonials', btn.dataset.id);
                toast.success('Testimonial deleted');
                render();
            });
        });
    }

    async function edit(record) {
        const data = await formLib.editModal({
            title: record ? `Edit Testimonial: ${record.name}` : 'Add Testimonial',
            record,
            html: F.section({
                fields: [
                    F.text({ name: 'name', label: 'Client Name', required: true }),
                    F.text({ name: 'company', label: 'Company / Organization' }),
                    F.text({ name: 'role', label: 'Role / Position' }),
                    F.textarea({ name: 'text', label: 'Review / Quote', required: true, rows: 4 }),
                    F.select({
                        name: 'rating', label: 'Rating Stars',
                        options: [5, 4, 3, 2, 1].map((n) => ({ value: String(n), label: `${n} Stars` })),
                    }),
                    F.media({ name: 'photo', label: 'Client Avatar / Photo (URL or Upload)' }),
                    F.toggle({ name: 'featured', label: 'Featured Testimonial' }),
                ],
            }),
        });

        if (!data) return;
        data.rating = Number(data.rating) || 5;

        if (record) {
            await store.update('testimonials', record.id, data);
            toast.success('Testimonial updated');
        } else {
            await store.create('testimonials', data);
            toast.success('Testimonial added');
        }

        render();
    }
}());
