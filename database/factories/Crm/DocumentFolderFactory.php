<?php

namespace Database\Factories\Crm;

use App\Models\Crm\DocumentFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFolderFactory extends Factory
{
    protected $model = DocumentFolder::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word().' '.$this->faker->word(),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
