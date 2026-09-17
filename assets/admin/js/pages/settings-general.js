(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Settings' }, { label: 'General Info' }],
            title: 'General Company Settings',
            sub: 'Site name, tagline, description, logo, and branding icons.',
        });

        const settings = await store.all('settings');
        const general = Object.fromEntries(settings.filter((s) => s.setting_group === 'general').map((s) => [s.setting_key, s.setting_value]));

        document.getElementById('view').innerHTML = `
            <form id="settingsForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>General Settings</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'site_name', label: 'Company / Brand Name', required: true, value: general.site_name || 'BCE Export' }),
                                F.text({ name: 'tagline', label: 'Tagline', value: general.tagline || '' }),
                                F.textarea({ name: 'description', label: 'Company Overview / Meta Description', rows: 3, value: general.description || '' }),
                                F.media({ name: 'logo', label: 'Company Logo (URL or Upload)', value: general.logo || '/img/logo.webp' }),
                                F.media({ name: 'favicon', label: 'Favicon Icon (URL or Upload)', value: general.favicon || '/img/favicon.png' }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end">
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
                </div>
            </form>`;

        window.TMH.media.paintAll(document.getElementById('settingsForm'), general);

        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            await window.TMH.api.request('PATCH', 'api/settings/general', { body: data });
            toast.success('General settings updated successfully');
        });
    }
}());
