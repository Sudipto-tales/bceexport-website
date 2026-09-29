(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Settings' }, { label: 'Contact Details' }],
            title: 'Contact Details & Address',
            sub: 'Phone, WhatsApp, email, physical address, and operating hours.',
        });

        const settings = await store.all('settings');
        const contact = Object.fromEntries(settings.filter((s) => s.setting_group === 'contact').map((s) => [s.setting_key, s.setting_value]));

        document.getElementById('view').innerHTML = `
            <form id="contactForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>Contact Information</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'phone', label: 'Primary Phone Number', required: true, value: contact.phone || '+91 89003 79037' }),
                                F.text({ name: 'whatsapp', label: 'WhatsApp Number', required: true, value: contact.whatsapp || '+91 89003 79037' }),
                                F.email({ name: 'email', label: 'Export Email Address', required: true, value: contact.email || 'info@bceexport.com' }),
                                F.textarea({ name: 'address', label: 'Export Headquarters Address', rows: 3, value: contact.address || 'Kolkata, West Bengal, India' }),
                                F.text({ name: 'working_hours', label: 'Working Hours', value: contact.working_hours || 'Mon - Sat: 9:00 AM - 7:00 PM IST' }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end">
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Contact Details</button>
                </div>
            </form>`;

        document.getElementById('contactForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            await window.TMH.api.request('PATCH', 'api/settings/contact', { body: data });
            toast.success('Contact details updated successfully');
        });
    }
}());
