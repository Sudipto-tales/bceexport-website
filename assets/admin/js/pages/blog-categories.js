(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Catalog' }, { label: 'Blog Categories' }],
            title: 'Blog Categories',
            sub: 'Manage blog post categories (e.g. Export Insights, Handicraft Guides, Shipping & Logistics).',
            actions: `
                <button type="button" class="btn btn--primary" id="addBlogCatBtn">
                    <i class="fa-solid fa-plus"></i> Add Category</button>`,
        });

        document.getElementById('addBlogCatBtn').addEventListener('click', () => edit(null));
        await render();
    }

    async function render() {
        const categories = await store.all('blog-categories');

        document.getElementById('view').innerHTML = `
            <div class="card anim-item mb-4">
                <div class="card__body p-3 row gap-2 items-center">
                    <div class="toolbar__search grow" style="max-width:320px">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="categorySearch" placeholder="Search blog categories...">
                    </div>
                    <span class="muted text-sm ml-auto">${categories.length} categor${categories.length === 1 ? 'y' : 'ies'}</span>
                </div>
            </div>

            <div class="grid grid--3 gap-4" id="categoryGrid">
                ${categories.length ? categories.map((c) => `
                    <article class="card anim-item category-card" data-search="${U.esc((c.name + ' ' + (c.slug || '') + ' ' + (c.description || '')).toLowerCase())}">
                        <div class="card__body col gap-3">
                            <div class="row gap-3 items-center">
                                <span class="avatar" style="width:48px;height:48px;font-size:18px;display:grid;place-items:center;background:var(--surface-2);border-radius:var(--radius-sm)"><i class="fa-solid fa-folder-tree text-brand"></i></span>
                                <div class="grow">
                                    <h4 class="m-0">${U.esc(c.name)}</h4>
                                    <small class="muted">${U.esc(c.slug)}</small>
                                </div>
                            </div>
                            <p class="text-sm muted m-0">${U.esc(c.description || 'No description provided.')}</p>
                            <div class="row gap-2 justify-end pt-2 border-top">
                                <button type="button" class="icon-btn" data-act="edit" data-id="${U.esc(c.id || c.slug)}"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(c.id || c.slug)}"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('') : '<p class="muted p-4 col-span-full">No blog categories found. Create your first category!</p>'}
            </div>`;

        U.stagger(document.getElementById('view'));

        const searchInput = document.getElementById('categorySearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const q = e.target.value.toLowerCase().trim();
                const cards = document.querySelectorAll('#categoryGrid .category-card');
                cards.forEach((card) => {
                    const haystack = card.dataset.search || card.textContent.toLowerCase();
                    card.style.display = haystack.includes(q) ? '' : 'none';
                });
            });
        }

        document.getElementById('view').querySelectorAll('[data-act="edit"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const c = categories.find((cat) => String(cat.id) === String(btn.dataset.id) || cat.slug === btn.dataset.id);
                if (c) edit(c);
            });
        });

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const ok = await window.TMH.confirm({
                    title: 'Delete blog category?',
                    body: 'Deleting a blog category removes it from blog posts selection.',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('blog-categories', btn.dataset.id);
                toast.success('Blog category deleted');
                render();
            });
        });
    }

    async function edit(record) {
        const data = await formLib.editModal({
            title: record ? `Edit Blog Category: ${record.name}` : 'Add Blog Category',
            record,
            html: F.section({
                fields: [
                    F.text({ name: 'name', label: 'Category Name', required: true }),
                    F.text({ name: 'slug', label: 'Slug (optional)', placeholder: 'auto-generated if empty' }),
                    F.textarea({ name: 'description', label: 'Description', rows: 3 }),
                ],
            }),
        });

        if (!data) return;
        if (!data.slug) data.slug = (U.slugify || U.slug)(data.name || '');

        if (record) {
            await store.update('blog-categories', record.id || record.slug, data);
            toast.success('Blog category updated');
        } else {
            await store.create('blog-categories', data);
            toast.success('Blog category created');
        }

        render();
    }
}());
