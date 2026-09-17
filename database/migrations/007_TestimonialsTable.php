<?php

class TestimonialsTable extends Migration
{
    public function up(): void
    {
        $this->create('testimonials', [
            $this->id(),
            'public_id VARCHAR(100) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'role VARCHAR(255)',
            'company VARCHAR(255)',
            'text TEXT NOT NULL',
            'photo VARCHAR(255)',
            'rating INT DEFAULT 5',
            $this->bool('featured', true),
            'status VARCHAR(50) DEFAULT "published"',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('testimonials', 'public_id', true);
    }

    public function down(): void
    {
        $this->drop('testimonials');
    }
}
