<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SettingDefinition;

class SettingDefinitionObserver
{
    public function creating(SettingDefinition $settingDefinition): void {}

    public function created(SettingDefinition $settingDefinition): void {}

    public function updating(SettingDefinition $settingDefinition): void {}

    public function updated(SettingDefinition $settingDefinition): void {}

    public function saving(SettingDefinition $settingDefinition): void {}

    public function saved(SettingDefinition $settingDefinition): void {}
}
