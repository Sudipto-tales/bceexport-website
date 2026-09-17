(function () {
    'use strict';

    const { util: U, store, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Leads' }, { label: 'Enquiries' }],
            title: 'Enquiries & Quote Submissions',
            sub: 'Client leads received via the website contact and quote request forms.',
        });

        await render();
    }

    async function render() {
        const enquiries = await store.all('enquiries');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-envelope-open-text', 'navy', enquiries.length, 'Total Submissions', 'All time leads'],
                ['fa-inbox', 'red', enquiries.filter((e) => e.status === 'new').length, 'New Leads', 'Awaiting review'],
                ['fa-check-double', 'green', enquiries.filter((e) => e.status === 'replied').length, 'Replied', 'Processed leads'],
            ])}

            <article class="card anim-item">
                <div class="card__body p-0">
                    <div class="table-wrap">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Client Info</th>
                                    <th>Product / Subject</th>
                                    <th>Message Snippet</th>
                                    <th>Date Received</th>
                                    <th>Status</th>
                                    <th style="width:80px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${enquiries.length ? enquiries.map((e) => `
                                    <tr>
                                        <td>
                                            <b>${U.esc(e.name)}</b><br>
                                            <small class="muted"><i class="fa-solid fa-envelope"></i> ${U.esc(e.email)}</small><br>
                                            ${e.phone ? `<small class="muted"><i class="fa-solid fa-phone"></i> ${U.esc(e.phone)}</small>` : ''}
                                        </td>
                                        <td><b>${U.esc(e.product || e.subject || 'General Quote')}</b></td>
                                        <td><p class="m-0 text-xs line-clamp-2 muted">${U.esc(e.message || 'No message provided.')}</p></td>
                                        <td><small>${U.esc(e.receivedAt ? e.receivedAt.split('T')[0] : 'Recently')}</small></td>
                                        <td><span class="status status--${e.status === 'new' ? 'draft' : 'published'}">${U.esc(e.status)}</span></td>
                                        <td>
                                            <button type="button" class="btn btn--ghost btn--sm" data-act="mark" data-id="${U.esc(e.id)}">
                                                ${e.status === 'new' ? '<i class="fa-solid fa-check"></i> Read' : '<i class="fa-solid fa-envelope"></i> Unread'}
                                            </button>
                                        </td>
                                    </tr>
                                `).join('') : '<tr><td colspan="6" class="text-center p-4 muted">No enquiries submitted yet.</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>`;

        U.stagger(document.getElementById('view'));

        document.getElementById('view').querySelectorAll('[data-act="mark"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const e = enquiries.find((item) => item.id === btn.dataset.id);
                if (!e) return;
                const newStatus = e.status === 'new' ? 'replied' : 'new';
                await store.update('enquiries', e.id, { status: newStatus });
                toast.success(`Enquiry marked as ${newStatus}`);
                render();
            });
        });
    }
}());
