<?php

class SettingsTable extends Migration
{
    public function up(): void
    {
        $this->create('settings', [
            $this->id(),
            'setting_group VARCHAR(100) NOT NULL',
            'setting_key VARCHAR(100) NOT NULL',
            $this->json('setting_value'),
            $this->timestamps(),
        ]);

        $this->index('settings', 'setting_group, setting_key', true);
    }

    public function down(): void
    {
        $this->drop('settings');
    }
}
