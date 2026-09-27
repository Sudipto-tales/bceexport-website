(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Catalog' }, { label: 'Blog Posts' }],
            title: 'Blog Posts',
            sub: 'Publish and manage industry insights, trade guides, and company news.',
            actions: `
                <a class="btn btn--primary" href="blog-post-form">
                    <i class="fa-solid fa-plus"></i> Write Blog Post</a>`,
        });

        await render();
    }

    async function render() {
        const [posts, categories] = await Promise.all([
            store.all('blog-posts'),
            store.all('blog-categories'),
        ]);

        const catMap = Object.fromEntries(categories.map((c) => [c.slug, c.name]));
        categories.forEach((c) => { catMap[c.id] = c.name; });

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-newspaper', 'navy', posts.length, 'Total Articles', 'Published & drafts'],
                ['fa-circle-check', 'green', posts.filter((p) => p.status === 'published').length, 'Published', 'Live on blog'],
                ['fa-star', 'blue', posts.filter((p) => p.featured).length, 'Featured', 'Highlighted articles'],
            ])}

            <div class="card anim-item mb-4">
                <div class="card__body p-3 row gap-2 items-center wrap">
                    <div class="toolbar__search grow" style="max-width:320px">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="postSearch" placeholder="Search blog posts...">
                    </div>
                    <select id="catFilter" style="height:38px;padding:0 12px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                        <option value="all">All Categories</option>
                        ${categories.map((c) => `<option value="${U.esc(c.slug)}">${U.esc(c.name)}</option>`).join('')}
                    </select>
                    <span class="muted text-sm ml-auto">${posts.length} post${posts.length === 1 ? '' : 's'}</span>
                </div>
            </div>

            <div id="postsGrid" class="grid grid--3 gap-4">
                ${posts.length ? posts.map((p) => `
                    <article class="card anim-item post-card col justify-between p-0 overflow-hidden" data-category="${U.esc(p.categoryId || '')}" data-search="${U.esc((p.title + ' ' + (p.slug || '') + ' ' + (p.excerpt || '') + ' ' + (p.authorName || '')).toLowerCase())}">
                        <div style="position:relative;width:100%;height:160px;background:var(--surface-2);overflow:hidden">
                            ${p.coverImage ? `<img src="${U.esc(U.mediaUrl(p.coverImage))}" alt="${U.esc(p.title)}" style="width:100%;height:100%;object-fit:cover;">` : `<div style="width:100%;height:100%;display:grid;place-items:center;color:var(--text-muted)"><i class="fa-solid fa-newspaper" style="font-size:36px"></i></div>`}
                            <div style="position:absolute;top:10px;left:10px;display:flex;gap:4px;flex-wrap:wrap">
                                <span class="pill pill--soft" style="background:rgba(255,255,255,0.92);backdrop-filter:blur(4px);font-weight:600;font-size:11px">${U.esc(catMap[p.categoryId] || 'General')}</span>
                                ${p.featured ? '<span class="pill" style="background:var(--brand-red);color:#fff;font-size:11px"><i class="fa-solid fa-star"></i> Featured</span>' : ''}
                            </div>
                            <span class="status status--${p.status === 'published' ? 'published' : 'draft'}" style="position:absolute;bottom:8px;right:8px;background:rgba(255,255,255,0.92);backdrop-filter:blur(4px)">${U.esc(p.status)}</span>
                        </div>
                        <div class="p-3 col gap-2 grow">
                            <h4 class="m-0" style="font-size:15px;line-height:1.3">${U.esc(p.title)}</h4>
                            <p class="text-xs muted m-0 line-clamp-2">${U.esc(p.excerpt || 'No summary available.')}</p>
                            <small class="muted" style="font-size:11px"><i class="fa-regular fa-calendar me-1"></i>${U.esc(p.publishedAt || p.createdAt || 'Draft')}</small>
                        </div>
                        <div class="row gap-2 justify-between items-center p-3 border-top" style="background:var(--surface-2)">
                            <small class="muted" style="font-size:11px">${U.esc(p.slug)}</small>
                            <div class="row gap-1">
                                <a href="blog-post-form?id=${U.esc(p.id || p.slug)}" class="icon-btn" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(p.id || p.slug)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('') : '<p class="muted p-4 col-span-full">No blog posts found. Write your first article!</p>'}
            </div>`;

        U.stagger(document.getElementById('view'));

        const searchInput = document.getElementById('postSearch');
        const catFilter = document.getElementById('catFilter');

        function applyFilters() {
            const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const cat = catFilter ? catFilter.value : 'all';

            document.querySelectorAll('.post-card').forEach((card) => {
                const hay = card.dataset.search || card.textContent.toLowerCase();
                const cardCat = card.dataset.category || '';
                const matchQ = !q || hay.includes(q);
                const matchCat = (cat === 'all') || (cardCat === cat);
                card.style.display = (matchQ && matchCat) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (catFilter) catFilter.addEventListener('change', applyFilters);

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const ok = await window.TMH.confirm({
                    title: 'Delete blog post?',
                    body: 'Are you sure you want to delete this post permanently?',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('blog-posts', id);
                toast.success('Blog post deleted');
                render();
            });
        });
    }
}());
