<?php

class MediaTable extends Migration
{
    public function up(): void
    {
        $this->create('media', [
            $this->id(),
            'public_id VARCHAR(100) NOT NULL UNIQUE',
            'url VARCHAR(500) NOT NULL',
            'path VARCHAR(500)',
            'filename VARCHAR(255) NOT NULL',
            'mime VARCHAR(100)',
            'alt VARCHAR(255)',
            'caption TEXT',
            'folder VARCHAR(100) DEFAULT "Uploads"',
            'width INT',
            'height INT',
            'size_bytes INT DEFAULT 0',
            'uploaded_by INT',
            $this->timestamps(),
        ]);
        $this->index('media', 'public_id', true);
        $this->index('media', 'folder');
    }

    public function down(): void
    {
        $this->drop('media');
    }
}
