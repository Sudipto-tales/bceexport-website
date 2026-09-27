(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;
    const params = new URLSearchParams(window.location.search);
    const postId = params.get('id') || params.get('slug');

    window.TMH.boot(init);

    async function init() {
        const record = postId ? await store.get('blog-posts', postId) : null;
        const categories = await store.all('blog-categories');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Catalog', href: 'blog-posts' },
                { label: 'Blog Posts', href: 'blog-posts' },
                { label: record ? `Edit: ${record.title}` : 'New Article' }
            ],
            title: record ? `Edit Post: ${record.title}` : 'Write New Blog Article',
            sub: 'Set title, category, cover image, excerpt, body rich text content, author, and status.',
            actions: `<a href="blog-posts" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Back to Blog Posts</a>`,
        });

        document.getElementById('view').innerHTML = `
            <form id="blogForm" class="col gap-4">
                <article class="card">
                    <div class="card__header">
                        <h3>Article Details & Content</h3>
                    </div>
                    <div class="card__body">
                        ${F.section({
                            fields: [
                                F.text({ name: 'title', label: 'Article Title', required: true, value: record ? record.title : '' }),
                                F.text({ name: 'slug', label: 'URL Slug', placeholder: 'auto-generated if empty', value: record ? (record.slug || record.id) : '' }),
                                F.select({
                                    name: 'categoryId',
                                    label: 'Blog Category',
                                    options: categories.map((c) => ({ value: c.slug || c.id, label: c.name })),
                                    value: record ? record.categoryId : '',
                                }),
                                F.text({ name: 'authorName', label: 'Author Name', value: record ? (record.authorName || record.author_name || 'BCE Export Team') : 'BCE Export Team' }),
                                F.textarea({ name: 'excerpt', label: 'Summary / Excerpt', rows: 2, value: record ? record.excerpt : '' }),
                                F.editor({ name: 'body', label: 'Article Body (Rich Text Editor)', placeholder: 'Write your blog post here...' }),
                                F.media({ name: 'coverImage', label: 'Cover Image (URL or Upload)', value: record ? (record.coverImage || record.cover_image) : '', hint: 'Paste image URL or click to upload.' }),
                                F.text({ name: 'publishedAt', label: 'Publication Date (YYYY-MM-DD HH:MM:SS)', value: record ? (record.publishedAt || record.published_at || new Date().toISOString().slice(0, 19).replace('T', ' ')) : new Date().toISOString().slice(0, 19).replace('T', ' ') }),
                                F.status({ value: record ? record.status : 'published' }),
                                F.toggle({ name: 'featured', label: 'Feature Post', value: record ? !!record.featured : false }),
                            ],
                        })}
                    </div>
                </article>

                <div class="row gap-2 justify-end mb-6">
                    <a href="blog-posts" class="btn btn--ghost">Cancel</a>
                    <button type="submit" class="btn btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Blog Post</button>
                </div>
            </form>`;

        const formEl = document.getElementById('blogForm');

        if (window.TMH.editor) {
            window.TMH.editor.upgradeAll(formEl);
        }
        if (window.TMH.form) {
            window.TMH.form.bind(formEl, record || {});
        }
        if (window.TMH.media) {
            window.TMH.media.paintAll(formEl, record || {});
        }

        formEl.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = formLib.collect(e.target);

            if (!data.title) {
                toast.error('Article Title is required');
                return;
            }

            if (!data.slug) {
                data.slug = U.slugify(data.title);
            }

            try {
                if (postId) {
                    await store.update('blog-posts', postId, data);
                    toast.success('Blog post updated successfully');
                } else {
                    await store.create('blog-posts', data);
                    toast.success('Blog post created successfully');
                }

                setTimeout(() => {
                    window.location.href = 'blog-posts';
                }, 400);
            } catch (err) {
                toast.error(err.message || 'Failed to save blog post');
            }
        });
    }
}());
