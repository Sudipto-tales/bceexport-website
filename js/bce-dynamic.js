/**
 * BCE Export Public Dynamic Connector.
 * Handles contact + quote forms with AJAX (no page reload)
 * and shows a thank-you message in place of the form.
 */
(function () {
    'use strict';

    const API_BASE = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');

    document.addEventListener('DOMContentLoaded', () => {
        initForms();
        initDynamicContent();
    });

    function initForms() {
        const forms = document.querySelectorAll(
            'form[action*="enquiry"], form.quote-form, form.contact-form, #contactForm, #quoteForm'
        );

        forms.forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn ? btn.innerHTML : 'Submit';
                const wrapper =
                    form.closest('#contactFormWrapper') ||
                    form.closest('#quoteFormWrapper') ||
                    form.parentElement;

                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';
                }

                const formData = new FormData(form);
                const payload = {
                    name:    formData.get('name') || '',
                    email:   formData.get('email') || '',
                    phone:   formData.get('phone') || '',
                    subject: formData.get('subject') || (form.id === 'quoteForm' ? 'Quote Request' : 'Website Inquiry'),
                    message: formData.get('message') || '',
                    product: formData.get('product') || formData.get('service') || '',
                    source:  form.id === 'quoteForm' ? 'landing' : 'contact',
                    website: formData.get('website') || ''   // honeypot
                };

                try {
                    const res = await fetch(API_BASE + 'api/public/enquiry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const json = await res.json().catch(() => ({}));

                    if (res.ok && json.success !== false) {
                        // Replace form with thank-you note (no page reload)
                        if (wrapper) {
                            wrapper.innerHTML = `
                                <div class="text-center py-5">
                                    <div class="mb-3">
                                        <i class="fa fa-check-circle text-primary" style="font-size: 3.5rem;"></i>
                                    </div>
                                    <h3 class="mb-3">Thank You!</h3>
                                    <p class="mb-0 fs-5">
                                        Your message has been received successfully.<br>
                                        Our team will contact you shortly.
                                    </p>
                                </div>
                            `;
                        } else {
                            form.reset();
                            alert('Thank you! Your enquiry has been received.');
                        }
                    } else {
                        const msg = json.error?.message || json.message || 'Failed to send enquiry. Please try again.';
                        alert(msg);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }
                } catch (err) {
                    console.error(err);
                    alert('Network error. Could not connect to the server.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                }
            });
        });
    }

    async function initDynamicContent() {
        try {
            const res = await fetch(API_BASE + 'api/bootstrap');
            if (!res.ok) return;
            const data = await res.json();
            if (data.settings) {
                applySettings(data.settings);
            }
        } catch (e) {
            // Static HTML fallback
        }
    }

    function applySettings(settings) {
        const contact = settings.contact || {};
        const stats = settings.stats || {};

        if (contact.phone) {
            document.querySelectorAll('[data-bce-phone]').forEach(el => el.textContent = contact.phone);
        }
        if (contact.email) {
            document.querySelectorAll('[data-bce-email]').forEach(el => el.textContent = contact.email);
        }
        if (contact.address) {
            document.querySelectorAll('[data-bce-address]').forEach(el => el.textContent = contact.address);
        }
        if (stats.clients) {
            document.querySelectorAll('[data-stat-clients]').forEach(el => el.textContent = stats.clients);
        }
        if (stats.exports) {
            document.querySelectorAll('[data-stat-exports]').forEach(el => el.textContent = stats.exports);
        }
        if (stats.products) {
            document.querySelectorAll('[data-stat-products]').forEach(el => el.textContent = stats.products);
        }
    }
}());
