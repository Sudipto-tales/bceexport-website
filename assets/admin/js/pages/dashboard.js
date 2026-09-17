(function () {
    'use strict';

    const { util: U, store, layout } = window.TMH;
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Main' }, { label: 'Dashboard' }],
            title: 'Welcome to BCE Export Admin',
            sub: 'Overview of products, categories, team, certificates, and recent client enquiries.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Visit Website</a>
                <a class="btn btn--primary" href="products">
                    <i class="fa-solid fa-plus"></i> Manage Products</a>`,
        });

        await render();
    }

    async function render() {
        const [products, categories, team, certs, enquiries] = await Promise.all([
            store.all('products'),
            store.all('categories'),
            store.all('team-members'),
            store.all('certificates'),
            store.all('enquiries'),
        ]);

        const newEnquiries = enquiries.filter((e) => e.status === 'new');
        const recentEnquiries = [...enquiries].sort((a, b) => new Date(b.receivedAt) - new Date(a.receivedAt)).slice(0, 5);

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-boxes-stacked', 'navy', products.length, 'Total Products', 'Active export items'],
                ['fa-tags', 'blue', categories.length, 'Categories', 'Product craft sectors'],
                ['fa-users', 'magenta', team.length, 'Team Members', 'Export team experts'],
                ['fa-envelope-open-text', newEnquiries.length ? 'red' : 'green', newEnquiries.length, 'New Enquiries', 'Unread client leads'],
            ])}

            <div class="grid grid--2 gap-4 mt-6">
                <article class="card anim-item">
                    <div class="card__header">
                        <h3><i class="fa-solid fa-envelope-open-text"></i> Recent Enquiries & Leads</h3>
                        <a href="enquiries" class="btn btn--ghost btn--sm">View All (${enquiries.length})</a>
                    </div>
                    <div class="card__body p-0">
                        ${recentEnquiries.length ? `
                            <div class="table-wrap">
                                <table class="adm-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Subject / Product</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${recentEnquiries.map((e) => `
                                            <tr>
                                                <td>
                                                    <b>${U.esc(e.name)}</b><br>
                                                    <small class="muted">${U.esc(e.email)}</small>
                                                </td>
                                                <td>${U.esc(e.product || e.subject || 'General Enquiry')}</td>
                                                <td><small>${U.esc(e.receivedAt ? e.receivedAt.split('T')[0] : 'Recently')}</small></td>
                                                <td><span class="status status--${e.status === 'new' ? 'draft' : 'published'}">${U.esc(e.status)}</span></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        ` : '<div class="p-4 text-center muted">No enquiries received yet.</div>'}
                    </div>
                </article>

                <article class="card anim-item">
                    <div class="card__header">
                        <h3><i class="fa-solid fa-bolt"></i> Quick Operations</h3>
                    </div>
                    <div class="card__body col gap-3">
                        <a href="products" class="btn btn--ghost justify-start">
                            <i class="fa-solid fa-boxes-stacked"></i> Add or Edit Product Items
                        </a>
                        <a href="categories" class="btn btn--ghost justify-start">
                            <i class="fa-solid fa-tags"></i> Manage Categories & Sectors
                        </a>
                        <a href="certificates" class="btn btn--ghost justify-start">
                            <i class="fa-solid fa-certificate"></i> Update Export Certifications
                        </a>
                        <a href="settings-general" class="btn btn--ghost justify-start">
                            <i class="fa-solid fa-sliders"></i> System & Contact Settings
                        </a>
                    </div>
                </article>
            </div>`;

        U.stagger(document.getElementById('view'));
    }
}());
