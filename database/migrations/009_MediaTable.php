<?php

class MediaTable extends Migration
{
    public function up(): void
    {
        $this->create('media', [
            $this->id(),
            'filename VARCHAR(255) NOT NULL',
            'filepath VARCHAR(255) NOT NULL',
            'filetype VARCHAR(100)',
            'filesize INT DEFAULT 0',
            'alt_text VARCHAR(255)',
            $this->timestamps(),
        ]);
    }

    public function down(): void
    {
        $this->drop('media');
    }
}
