<?php

class EnquiriesTable extends Migration
{
    public function up(): void
    {
        $this->create('enquiries', [
            $this->id(),
            'public_id VARCHAR(100) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'email VARCHAR(255) NOT NULL',
            'phone VARCHAR(100)',
            'subject VARCHAR(255)',
            'message TEXT',
            'product VARCHAR(255)',
            'source VARCHAR(100) DEFAULT "contact"',
            'assigned_to VARCHAR(100)',
            'priority VARCHAR(50) DEFAULT "normal"',
            'status VARCHAR(50) DEFAULT "new"',
            'received_at DATETIME',
            $this->json('replies'),
            $this->json('internal_notes'),
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('enquiries', 'public_id', true);
        $this->index('enquiries', 'status');
    }

    public function down(): void
    {
        $this->drop('enquiries');
    }
}
