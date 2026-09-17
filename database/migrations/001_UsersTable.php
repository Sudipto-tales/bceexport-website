<?php

class UsersTable extends Migration
{
    public function up(): void
    {
        $this->create('users', [
            $this->id(),
            'public_id VARCHAR(100) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'email VARCHAR(255) NOT NULL UNIQUE',
            'password VARCHAR(255) NOT NULL',
            'role_id VARCHAR(50) DEFAULT "admin"',
            'phone VARCHAR(100)',
            'avatar VARCHAR(255)',
            'landing_page VARCHAR(100) DEFAULT "dashboard"',
            'status VARCHAR(50) DEFAULT "active"',
            'sort_order INT DEFAULT 0',
            'remember_token VARCHAR(255)',
            'reset_token VARCHAR(255)',
            'last_active_at DATETIME',
            $this->timestamps(),
        ]);

        $this->index('users', 'email', true);
        $this->index('users', 'public_id', true);
    }

    public function down(): void
    {
        $this->drop('users');
    }
}
