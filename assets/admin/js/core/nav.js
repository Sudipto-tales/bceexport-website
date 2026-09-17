(function () {
    function count(entity, test) {
        try {
            return (window.TMH.store.allSync(entity) || []).filter(test).length;
        } catch (e) {
            return 0;
        }
    }

    window.TMH_NAV_COUNT = count;
}());

window.TMH_NAV = [
    {
        label: 'Main',
        items: [
            { key: 'dashboard', label: 'Dashboard', icon: 'fa-house', href: 'dashboard' },
        ],
    },
    {
        label: 'Catalog & Content',
        items: [
            { key: 'products', label: 'Products', icon: 'fa-boxes-stacked', href: 'products' },
            { key: 'categories', label: 'Categories', icon: 'fa-tags', href: 'categories' },
            { key: 'team', label: 'Team Members', icon: 'fa-users', href: 'team' },
            { key: 'certificates', label: 'Certificates', icon: 'fa-certificate', href: 'certificates' },
            { key: 'testimonials', label: 'Testimonials', icon: 'fa-comments', href: 'testimonials' },
        ],
    },
    {
        label: 'Leads & Enquiries',
        items: [
            {
                key: 'enquiries', label: 'Enquiries', icon: 'fa-envelope-open-text', href: 'enquiries',
                badge: () => window.TMH_NAV_COUNT('enquiries', (e) => e.status === 'new'),
            },
        ],
    },
    {
        label: 'System Settings',
        items: [
            { key: 'settings-general', label: 'General Info', icon: 'fa-sliders', href: 'settings-general' },
            { key: 'settings-contact', label: 'Contact Details', icon: 'fa-address-book', href: 'settings-contact' },
            { key: 'settings-social', label: 'Social & Stats', icon: 'fa-share-nodes', href: 'settings-social' },
        ],
    },
];
