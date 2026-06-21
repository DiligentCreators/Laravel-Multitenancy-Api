<?php

namespace Database\Factories\Crm;

use App\Enums\DocumentStatusEnum;
use App\Models\Crm\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $ext = $this->faker->fileExtension();

        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'file_name' => $this->faker->word().'.'.$ext,
            'file_path' => '/uploads/'.$this->faker->uuid().'.'.$ext,
            'mime_type' => $this->faker->mimeType(),
            'file_size' => $this->faker->numberBetween(1024, 10485760),
            'extension' => $ext,
            'version' => '1.0',
            'status' => DocumentStatusEnum::DRAFT,
            'is_locked' => false,
        ];
    }
}
