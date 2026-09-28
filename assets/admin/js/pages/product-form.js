(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;
    const params = new URLSearchParams(window.location.search);
    const productId = params.get('id') || params.get('slug');

    window.TMH.boot(init);

    async function init() {
        const record = productId ? await store.get('products', productId) : null;
        const categories = await store.all('categories');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Catalog', href: 'products' }, { label: 'Products', href: 'products' }, { label: record ? `Edit: ${record.name}` : 'New Product' }],
            title: record ? `Edit Product: ${record.name}` : 'Create New Product',
            sub: 'Set name, category, image URL or upload, description, and status.',
            actions: `<a href="products" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Back to Products</a>`,
        });

        document.getElementById('view').innerHTML = `
            <form id="productForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>General Details</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'name', label: 'Product Name', required: true, value: record ? record.name : '' }),
                                F.text({ name: 'slug', label: 'URL Slug', placeholder: 'auto-generated if empty', value: record ? (record.slug || record.id) : '' }),
                                F.select({
                                    name: 'categoryId',
                                    label: 'Category',
                                    required: true,
                                    options: categories.map((c) => ({ value: c.id || c.slug, label: c.name })),
                                    value: record ? record.categoryId : '',
                                }),
                                F.textarea({ name: 'shortDescription', label: 'Short Summary', rows: 2, value: record ? record.shortDescription : '' }),
                                F.textarea({ name: 'description', label: 'Full Description', rows: 5, value: record ? record.description : '' }),
                                F.media({ name: 'image', label: 'Product Image (URL or Upload)', value: record ? record.image : '', hint: 'Paste external image link or click to upload.' }),
                                F.status({ value: record ? record.status : 'published' }),
                                F.toggle({ name: 'featured', label: 'Feature on Home Page', value: record ? !!record.featured : false }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end">
                    <a href="products" class="btn btn--ghost">Cancel</a>
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Product</button>
                </div>
            </form>`;

        const formEl = document.getElementById('productForm');
        if (window.TMH.form) window.TMH.form.bind(formEl, record || {});
        if (window.TMH.media) window.TMH.media.paintAll(formEl, record || {});

        document.getElementById('productForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            if (!data.slug) {
                data.slug = (U.slugify || U.slug)(data.name || '');
            }

            try {
                if (productId) {
                    await store.update('products', productId, data);
                    toast.success('Product updated successfully');
                } else {
                    await store.create('products', data);
                    toast.success('Product created successfully');
                }

                setTimeout(() => {
                    window.location.href = 'products';
                }, 400);
            } catch (err) {
                toast.error(err.message || 'Failed to save product');
            }
        });
    }
}());
