<?php

class BlogPostsTable extends Migration
{
    public function up(): void
    {
        $this->create('blog_posts', [
            $this->id(),
            'slug VARCHAR(191) NOT NULL UNIQUE',
            'title VARCHAR(255) NOT NULL',
            'excerpt TEXT',
            'body TEXT',
            'cover_image VARCHAR(255)',
            'category_id VARCHAR(191)',
            'author_name VARCHAR(191) DEFAULT "BCE Export"',
            $this->json('tags'),
            $this->bool('featured', false),
            'status VARCHAR(50) DEFAULT "published"',
            'published_at DATETIME',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('blog_posts', 'slug', true);
        $this->index('blog_posts', 'category_id');
        $this->index('blog_posts', 'status');
    }

    public function down(): void
    {
        $this->drop('blog_posts');
    }
}
