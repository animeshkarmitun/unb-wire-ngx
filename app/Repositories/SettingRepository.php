<?php

namespace App\Repositories;

use App\Models\Setting;

class SettingRepository
{
    public function get(string $key): mixed
    {
        $setting = Setting::where('key', $key)->first();

        return $setting?->value;
    }

    public function set(string $key, mixed $value, ?int $updatedBy = null): Setting
    {
        return Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $updatedBy ?? auth()->id(),
                'updated_at' => now(),
            ]
        );
    }

    public function getAiDeskConfig(): array
    {
        $value = $this->get('ai.desk');

        return is_string($value) ? json_decode($value, true) : ($value ?? []);
    }

    public function getDeliveryConfig(): array
    {
        $value = $this->get('delivery');

        return is_string($value) ? json_decode($value, true) : ($value ?? []);
    }
}
