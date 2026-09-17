<?php

class ActivityLogTable extends Migration
{
    public function up(): void
    {
        $this->create('activity_log', [
            $this->id(),
            'user_id INT',
            'user_name VARCHAR(255)',
            'action VARCHAR(100)',
            'entity VARCHAR(100)',
            'entity_id VARCHAR(100)',
            'summary TEXT',
            'diff TEXT',
            'ip VARCHAR(50)',
            'created_at DATETIME',
        ]);

        $this->index('activity_log', 'entity, entity_id');
        $this->index('activity_log', 'created_at');
    }

    public function down(): void
    {
        $this->drop('activity_log');
    }
}
