<?php

class TeamMembersTable extends Migration
{
    public function up(): void
    {
        $this->create('team_members', [
            $this->id(),
            'slug VARCHAR(191) NOT NULL UNIQUE',
            'name VARCHAR(255) NOT NULL',
            'role VARCHAR(255) NOT NULL',
            'photo VARCHAR(255)',
            'bio TEXT',
            'email VARCHAR(255)',
            'phone VARCHAR(100)',
            $this->json('social_links'),
            'status VARCHAR(50) DEFAULT "published"',
            'sort_order INT DEFAULT 0',
            $this->timestamps(),
        ]);

        $this->index('team_members', 'slug', true);
    }

    public function down(): void
    {
        $this->drop('team_members');
    }
}
