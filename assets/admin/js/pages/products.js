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

            <div class="card anim-item mb-4">
                <div class="card__body p-3 row gap-2 items-center wrap">
                    <div class="toolbar__search grow" style="max-width:320px">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="productSearch" placeholder="Search products...">
                    </div>
                    <select id="catFilter" style="height:38px;padding:0 12px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                        <option value="all">All Categories</option>
                        ${categories.map((c) => `<option value="${U.esc(c.id)}">${U.esc(c.name)}</option>`).join('')}
                    </select>
                    <div class="row gap-1 ml-auto">
                        <button type="button" class="btn btn--sm btn--primary" id="cardViewBtn" title="Card View"><i class="fa-solid fa-grip"></i> Cards</button>
                        <button type="button" class="btn btn--sm btn--ghost" id="tableViewBtn" title="Table View"><i class="fa-solid fa-list"></i> Table</button>
                    </div>
                    <span class="muted text-sm">${products.length} product${products.length === 1 ? '' : 's'}</span>
                </div>
            </div>

            <div id="productCardsContainer" class="grid grid--4 gap-4">
                ${products.length ? products.map((p) => `
                    <article class="card anim-item product-card col justify-between p-0 overflow-hidden" data-category="${U.esc(p.categoryId || '')}" data-search="${U.esc((p.name + ' ' + (p.slug || '') + ' ' + (catMap[p.categoryId] || '') + ' ' + (p.shortDescription || '')).toLowerCase())}">
                        <div style="position:relative;width:100%;height:180px;background:var(--surface-2);overflow:hidden">
                            ${p.image ? `<img src="${U.esc(U.mediaUrl(p.image))}" alt="${U.esc(p.name)}" style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s ease">` : `<div style="width:100%;height:100%;display:grid;place-items:center;color:var(--text-muted)"><i class="fa-solid fa-boxes-stacked" style="font-size:36px"></i></div>`}
                            <div style="position:absolute;top:10px;left:10px;display:flex;gap:4px;flex-wrap:wrap">
                                <span class="pill pill--soft" style="background:rgba(255,255,255,0.92);backdrop-filter:blur(4px);font-weight:600;font-size:11px">${U.esc(catMap[p.categoryId] || 'Export Craft')}</span>
                                ${p.featured ? '<span class="pill" style="background:var(--brand-red);color:#fff;font-size:11px"><i class="fa-solid fa-star"></i> Featured</span>' : ''}
                            </div>
                            <span class="status status--${p.status === 'published' ? 'published' : 'draft'}" style="position:absolute;bottom:8px;right:8px;background:rgba(255,255,255,0.92);backdrop-filter:blur(4px)">${U.esc(p.status)}</span>
                        </div>
                        <div class="p-3 col gap-2 grow">
                            <h4 class="m-0" style="font-size:15px;line-height:1.3">${U.esc(p.name)}</h4>
                            <p class="text-xs muted m-0 line-clamp-2">${U.esc(p.shortDescription || p.description || 'Authentic Indian handicrafts and export artifacts.')}</p>
                        </div>
                        <div class="row gap-2 justify-between items-center p-3 border-top" style="background:var(--surface-2)">
                            <small class="muted" style="font-size:11px">${U.esc(p.slug)}</small>
                            <div class="row gap-1">
                                <a href="product-form?id=${U.esc(p.id)}" class="icon-btn" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(p.id)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('') : '<p class="muted p-4 col-span-full">No products found. Add your first product!</p>'}
            </div>

            <article class="card anim-item hidden" id="productTableContainer">
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
                                ${products.map((p) => `
                                    <tr data-id="${U.esc(p.id)}" data-category="${U.esc(p.categoryId || '')}">
                                        <td>
                                            ${p.image ? `<img src="${U.esc(U.mediaUrl(p.image))}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px">` : '<span class="muted"><i class="fa-solid fa-image"></i></span>'}
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
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>`;

        U.stagger(document.getElementById('view'));

        const cardsContainer = document.getElementById('productCardsContainer');
        const tableContainer = document.getElementById('productTableContainer');
        const cardViewBtn = document.getElementById('cardViewBtn');
        const tableViewBtn = document.getElementById('tableViewBtn');

        if (cardViewBtn && tableViewBtn) {
            cardViewBtn.addEventListener('click', () => {
                cardsContainer.classList.remove('hidden');
                tableContainer.classList.add('hidden');
                cardViewBtn.className = 'btn btn--sm btn--primary';
                tableViewBtn.className = 'btn btn--sm btn--ghost';
            });
            tableViewBtn.addEventListener('click', () => {
                cardsContainer.classList.add('hidden');
                tableContainer.classList.remove('hidden');
                cardViewBtn.className = 'btn btn--sm btn--ghost';
                tableViewBtn.className = 'btn btn--sm btn--primary';
            });
        }

        const searchInput = document.getElementById('productSearch');
        const catFilter = document.getElementById('catFilter');

        function applyFilters() {
            const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const cat = catFilter ? catFilter.value : 'all';

            document.querySelectorAll('.product-card').forEach((card) => {
                const hay = card.dataset.search || card.textContent.toLowerCase();
                const cardCat = card.dataset.category || '';
                const matchQ = !q || hay.includes(q);
                const matchCat = (cat === 'all') || (cardCat === cat);
                card.style.display = (matchQ && matchCat) ? '' : 'none';
            });

            document.querySelectorAll('#productTable tbody tr').forEach((row) => {
                const text = row.textContent.toLowerCase();
                const rowCat = row.dataset.category || '';
                const matchQ = !q || text.includes(q);
                const matchCat = (cat === 'all') || (rowCat === cat);
                row.style.display = (matchQ && matchCat) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (catFilter) catFilter.addEventListener('change', applyFilters);

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
