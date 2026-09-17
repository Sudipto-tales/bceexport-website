(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Catalog' }, { label: 'Categories' }],
            title: 'Product Categories',
            sub: 'Export categories and craft sectors (Leather, Wooden Handicrafts, Furniture, Jute, etc.).',
            actions: `
                <button type="button" class="btn btn--primary" id="addCatBtn">
                    <i class="fa-solid fa-plus"></i> Add Category</button>`,
        });

        document.getElementById('addCatBtn').addEventListener('click', () => edit(null));
        await render();
    }

    async function render() {
        const categories = await store.all('categories');

        document.getElementById('view').innerHTML = `
            <div class="grid grid--3 gap-4">
                ${categories.map((c) => `
                    <article class="card anim-item">
                        <div class="card__body col gap-3">
                            <div class="row gap-3 items-center">
                                ${c.image ? `<img src="${U.esc(c.image)}" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:var(--radius-sm)">` : `<span class="avatar" style="width:50px;height:50px;font-size:20px;display:grid;place-items:center"><i class="fa-solid fa-tags"></i></span>`}
                                <div class="grow">
                                    <h4 class="m-0">${U.esc(c.name)}</h4>
                                    <small class="muted">${U.esc(c.slug)}</small>
                                </div>
                            </div>
                            <p class="text-sm muted m-0">${U.esc(c.description || 'No description provided.')}</p>
                            <div class="row gap-2 justify-end pt-2 border-top">
                                <button type="button" class="icon-btn" data-act="edit" data-id="${U.esc(c.id)}"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(c.id)}"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('')}
            </div>`;

        U.stagger(document.getElementById('view'));

        document.getElementById('view').querySelectorAll('[data-act="edit"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const c = categories.find((cat) => cat.id === btn.dataset.id);
                if (c) edit(c);
            });
        });

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const ok = await window.TMH.confirm({
                    title: 'Delete category?',
                    body: 'Deleting a category removes it from selection in products.',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('categories', btn.dataset.id);
                toast.success('Category deleted');
                render();
            });
        });
    }

    async function edit(record) {
        const data = await formLib.editModal({
            title: record ? `Edit Category: ${record.name}` : 'Add Category',
            record,
            html: F.section({
                fields: [
                    F.text({ name: 'name', label: 'Category Name', required: true }),
                    F.text({ name: 'slug', label: 'Slug (optional)', placeholder: 'auto-generated if empty' }),
                    F.textarea({ name: 'description', label: 'Description', rows: 3 }),
                    F.media({ name: 'image', label: 'Category Image (URL or Upload)' }),
                ],
            }),
        });

        if (!data) return;
        if (!data.slug) data.slug = U.slugify(data.name);

        if (record) {
            await store.update('categories', record.id, data);
            toast.success('Category updated');
        } else {
            await store.create('categories', data);
            toast.success('Category created');
        }

        render();
    }
}());
