(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;
    const params = new URLSearchParams(window.location.search);
    const memberId = params.get('id');

    window.TMH.boot(init);

    async function init() {
        const record = memberId ? await store.get('team-members', memberId) : null;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Company', href: 'team' }, { label: 'Team', href: 'team' }, { label: record ? `Edit: ${record.name}` : 'New Team Member' }],
            title: record ? `Edit Member: ${record.name}` : 'Add Team Member',
            sub: 'Enter member name, role, bio, contact email, and photo (URL or upload).',
            actions: `<a href="team" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Back to Team</a>`,
        });

        document.getElementById('view').innerHTML = `
            <form id="teamForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>Member Information</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'name', label: 'Full Name', required: true, value: record ? record.name : '' }),
                                F.text({ name: 'role', label: 'Role / Designation', required: true, value: record ? record.role : '' }),
                                F.email({ name: 'email', label: 'Email Address', value: record ? record.email : '' }),
                                F.text({ name: 'phone', label: 'Phone Number', value: record ? record.phone : '' }),
                                F.textarea({ name: 'bio', label: 'Short Bio', rows: 4, value: record ? record.bio : '' }),
                                F.media({ name: 'photo', label: 'Photo (URL or Upload)', value: record ? record.photo : '' }),
                                F.status({ value: record ? record.status : 'published' }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end">
                    <a href="team" class="btn btn--ghost">Cancel</a>
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Member</button>
                </div>
            </form>`;

        window.TMH.media.paintAll(document.getElementById('teamForm'), record || {});

        document.getElementById('teamForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            if (!data.slug) data.slug = U.slugify(data.name);

            if (memberId) {
                await store.update('team-members', memberId, data);
                toast.success('Member updated');
            } else {
                await store.create('team-members', data);
                toast.success('Member added');
            }

            window.location.href = 'team';
        });
    }
}());
