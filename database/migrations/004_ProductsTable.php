<?php

class ProductsTable extends Migration
{
    public function up(): void
    {
        $this->create('products', [
            $this->id(),
            'slug VARCHAR(191) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'category_id VARCHAR(191)',
            'short_description TEXT',
            'description TEXT',
            'image VARCHAR(255)',
            $this->json('gallery'),
            $this->json('features'),
            $this->json('specifications'),
            $this->bool('featured', false),
            'status VARCHAR(50) DEFAULT "published"',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('products', 'slug', true);
        $this->index('products', 'category_id');
    }

    public function down(): void
    {
        $this->drop('products');
    }
}
