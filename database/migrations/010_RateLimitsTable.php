<?php

class RateLimitsTable extends Migration
{
    public function up(): void
    {
        $this->create('rate_limits', [
            $this->id(),
            'action VARCHAR(50) NOT NULL',
            'client_key VARCHAR(100) NOT NULL',
            'created_at DATETIME NOT NULL',
        ]);

        $this->index('rate_limits', 'action, client_key, created_at');
    }

    public function down(): void
    {
        $this->drop('rate_limits');
    }
}
