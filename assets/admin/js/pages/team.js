(function () {
    'use strict';

    const { util: U, store, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Company' }, { label: 'Team Members' }],
            title: 'Export Team Members',
            sub: 'Manage executive board, global trade experts, and artisanal craft directors.',
            actions: `
                <a href="team-form" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Add Team Member</a>`,
        });

        await render();
    }

    async function render() {
        const team = await store.all('team-members');

        document.getElementById('view').innerHTML = `
            <div class="card anim-item mb-4">
                <div class="card__body p-3 row gap-2 items-center">
                    <div class="toolbar__search grow" style="max-width:320px">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="teamSearch" placeholder="Search team members by name or role...">
                    </div>
                    <span class="muted text-sm ml-auto">${team.length} member${team.length === 1 ? '' : 's'}</span>
                </div>
            </div>

            <div class="grid grid--4 gap-4" id="teamGrid">
                ${team.map((m) => `
                    <article class="card anim-item text-center team-card" data-search="${U.esc((m.name + ' ' + (m.role || '') + ' ' + (m.bio || '')).toLowerCase())}">
                        <div class="card__body col items-center gap-3">
                            ${m.photo ? `<img src="${U.esc(U.mediaUrl(m.photo))}" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:50%">` : `<span class="avatar" style="width:80px;height:80px;font-size:28px;display:grid;place-items:center;border-radius:50%">${U.esc(U.initials(m.name))}</span>`}
                            <div>
                                <h4 class="m-0">${U.esc(m.name)}</h4>
                                <small class="text-primary font-bold">${U.esc(m.role)}</small>
                            </div>
                            <p class="text-xs muted m-0 line-clamp-2">${U.esc(m.bio || '')}</p>
                            <div class="row gap-2 justify-center pt-2 border-top w-full">
                                <a href="team-form?id=${U.esc(m.id)}" class="icon-btn" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(m.id)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    </article>
                `).join('')}
            </div>`;

        U.stagger(document.getElementById('view'));

        const searchInput = document.getElementById('teamSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const q = e.target.value.toLowerCase().trim();
                const cards = document.querySelectorAll('#teamGrid .team-card');
                cards.forEach((card) => {
                    const haystack = card.dataset.search || card.textContent.toLowerCase();
                    card.style.display = haystack.includes(q) ? '' : 'none';
                });
            });
        }

        document.getElementById('view').querySelectorAll('[data-act="delete"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const ok = await window.TMH.confirm({
                    title: 'Delete team member?',
                    body: 'Are you sure you want to remove this team member?',
                    danger: true,
                });
                if (!ok) return;

                await store.remove('team-members', btn.dataset.id);
                toast.success('Team member removed');
                render();
            });
        });
    }
}());
