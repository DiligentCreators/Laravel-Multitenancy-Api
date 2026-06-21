<?php

namespace Database\Factories\Crm;

use App\Models\Crm\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    public function definition(): array
    {
        $ext = $this->faker->fileExtension();

        return [
            'version' => '1.1',
            'file_name' => $this->faker->word().'.'.$ext,
            'file_path' => '/uploads/'.$this->faker->uuid().'.'.$ext,
            'mime_type' => $this->faker->mimeType(),
            'file_size' => $this->faker->numberBetween(1024, 10485760),
        ];
    }
}
