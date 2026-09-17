<?php

class CertificatesTable extends Migration
{
    public function up(): void
    {
        $this->create('certificates', [
            $this->id(),
            'slug VARCHAR(191) NOT NULL UNIQUE',
            'title VARCHAR(255) NOT NULL',
            'issuer VARCHAR(255)',
            'image VARCHAR(255)',
            'issue_date VARCHAR(100)',
            'expiry_date VARCHAR(100)',
            'status VARCHAR(50) DEFAULT "published"',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('certificates', 'slug', true);
    }

    public function down(): void
    {
        $this->drop('certificates');
    }
}
