(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Catalog' }, { label: 'Products' }],
            title: 'Export Products Catalog',
            sub: 'Manage product items, descriptions, categories, image URLs, and specifications.',
            actions: `
                <a class="btn btn--primary" href="product-form">
                    <i class="fa-solid fa-plus"></i> Add Product</a>`,
        });

        await render();
    }

    async function render() {
        const [products, categories] = await Promise.all([
            store.all('products'),
            store.all('categories'),
        ]);

        const catMap = Object.fromEntries(categories.map((c) => [c.id, c.name]));

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-boxes-stacked', 'navy', products.length, 'Total Products', 'Items in database'],
                ['fa-circle-check', 'green', products.filter((p) => p.status === 'published').length, 'Published', 'Live on site'],
                ['fa-star', 'blue', products.filter((p) => p.featured).length, 'Featured', 'Highlighted items'],
            ])}

            <article class="card anim-item">
                <div class="card__header row gap-2">
                    <div class="toolbar__search grow" style="max-width:300px">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="productSearch" placeholder="Search products...">
                    </div>
                </div>
                <div class="card__body p-0">
                    <div class="table-wrap">
                        <table class="adm-table" id="productTable">
                            <thead>
                                <tr>
                                    <th style="width:60px">Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Featured</th>
                                    <th>Status</th>
                                    <th style="width:100px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${products.length ? products.map((p) => `
                                    <tr data-id="${U.esc(p.id)}">
                                        <td>
                                            ${p.image ? `<img src="${U.esc(p.image)}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px">` : '<span class="muted"><i class="fa-solid fa-image"></i></span>'}
                                        </td>
                                        <td>
                                            <b>${U.esc(p.name)}</b><br>
                                            <small class="muted">${U.esc(p.slug)}</small>
                                        </td>
                                        <td><span class="pill pill--soft">${U.esc(catMap[p.categoryId] || p.categoryId || 'Uncategorized')}</span></td>
                                        <td>${p.featured ? '<span class="tag info"><i class="fa-solid fa-star"></i> Featured</span>' : '<span class="muted">—</span>'}</td>
                                        <td><span class="status status--${p.status === 'published' ? 'published' : 'draft'}">${U.esc(p.status)}</span></td>
                                        <td>
                                            <a href="product-form?id=${U.esc(p.id)}" class="icon-btn" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                                            <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(p.id)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                        </td>
                                    </tr>
                                `).join('') : '<tr><td colspan="6" class="text-center p-4 muted">No products found. Add your first product!</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>`;

        U.stagger(document.getElementById('view'));

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const ok = await window.TMH.confirm({
                    title: 'Delete product?',
                    body: 'Are you sure you want to delete this product item permanently?',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('products', id);
                toast.success('Product deleted');
                render();
            });
        });
    }
}());
