<?php

class BlogCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->create('blog_categories', [
            $this->id(),
            'slug VARCHAR(191) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'description TEXT',
            'status VARCHAR(50) DEFAULT "published"',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('blog_categories', 'slug', true);
    }

    public function down(): void
    {
        $this->drop('blog_categories');
    }
}
