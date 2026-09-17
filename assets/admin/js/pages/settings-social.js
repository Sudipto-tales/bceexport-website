(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Settings' }, { label: 'Social & Stats' }],
            title: 'Social Links & Key Statistics',
            sub: 'Manage social media channels and homepage impact stats (700/654/565).',
        });

        const settings = await store.all('settings');
        const social = Object.fromEntries(settings.filter((s) => s.setting_group === 'social').map((s) => [s.setting_key, s.setting_value]));
        const stats = Object.fromEntries(settings.filter((s) => s.setting_group === 'stats').map((s) => [s.setting_key, s.setting_value]));

        document.getElementById('view').innerHTML = `
            <form id="socialForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>Homepage Counter Statistics</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'stat_clients', label: 'Global Clients Count (e.g. 700+)', required: true, value: stats.clients || '700+' }),
                                F.text({ name: 'stat_exports', label: 'Successful Exports Count (e.g. 654+)', required: true, value: stats.exports || '654+' }),
                                F.text({ name: 'stat_products', label: 'Products & Crafts Count (e.g. 565+)', required: true, value: stats.products || '565+' }),
                            ],
                        })}
                    </div>
                </article>

                <article class="card">
                    <div class="card__header">
                        <h3>Social Media Handles</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'facebook', label: 'Facebook URL', value: social.facebook || '' }),
                                F.text({ name: 'instagram', label: 'Instagram URL', value: social.instagram || '' }),
                                F.text({ name: 'linkedin', label: 'LinkedIn URL', value: social.linkedin || '' }),
                                F.text({ name: 'whatsapp', label: 'WhatsApp Direct Link', value: social.whatsapp || '' }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end">
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Social & Stats</button>
                </div>
            </form>`;

        document.getElementById('socialForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            const socialData = {
                facebook: data.facebook,
                instagram: data.instagram,
                linkedin: data.linkedin,
                whatsapp: data.whatsapp,
            };

            const statsData = {
                clients: data.stat_clients,
                exports: data.stat_exports,
                products: data.stat_products,
            };

            await Promise.all([
                window.TMH.api.request('PATCH', 'api/settings/social', { body: socialData }),
                window.TMH.api.request('PATCH', 'api/settings/stats', { body: statsData }),
            ]);

            toast.success('Social handles and counter statistics updated!');
        });
    }
}());
