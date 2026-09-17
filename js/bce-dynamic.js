/**
 * BCE Export Public Dynamic Connector.
 *
 * Dynamically connects contact/quote forms to POST /api/public/enquiry
 * and loads dynamic content (Categories, Products, Team, Certificates, Testimonials, Settings)
 * from the SQLite backend.
 */
(function () {
    'use strict';

    const API_BASE = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/');

    document.addEventListener('DOMContentLoaded', () => {
        initForms();
        initDynamicContent();
    });

    function initForms() {
        const forms = document.querySelectorAll('form[action*="enquiry"], form.quote-form, form.contact-form, #contactForm, #quoteForm');
        
        forms.forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn ? btn.innerHTML : 'Submit';
                
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
                }

                const formData = new FormData(form);
                const payload = {
                    name: formData.get('name') || formData.get('your-name') || '',
                    email: formData.get('email') || formData.get('your-email') || '',
                    phone: formData.get('phone') || formData.get('your-phone') || '',
                    subject: formData.get('subject') || 'Website Inquiry',
                    message: formData.get('message') || formData.get('your-message') || '',
                    product: formData.get('product') || formData.get('service') || '',
                    source: window.location.pathname.split('/').pop() || 'contact'
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
                        alert('Thank you! Your enquiry has been received successfully. Our team will contact you shortly.');
                        form.reset();
                    } else {
                        alert(json.error?.message || json.message || 'Failed to send enquiry. Please check your information and try again.');
                    }
                } catch (err) {
                    alert('Network error. Could not connect to the server.');
                } finally {
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
            // Static HTML fallback stays active
        }
    }

    function applySettings(settings) {
        const contact = settings.contact || {};
        const stats = settings.stats || {};
        const social = settings.social || {};

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
