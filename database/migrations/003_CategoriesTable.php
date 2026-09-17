<?php

class CategoriesTable extends Migration
{
    public function up(): void
    {
        $this->create('categories', [
            $this->id(),
            'slug VARCHAR(191) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'description TEXT',
            'image VARCHAR(255)',
            'icon VARCHAR(255)',
            'status VARCHAR(50) DEFAULT "published"',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('categories', 'slug', true);
    }

    public function down(): void
    {
        $this->drop('categories');
    }
}
