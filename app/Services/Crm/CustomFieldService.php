<?php

namespace App\Services\Crm;

use App\Models\Crm\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomFieldService
{
    public function query(?string $entityType = null): Builder
    {
        $query = CustomFieldDefinition::query()->orderBy('order');

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query;
    }

    public function paginate(?string $entityType = null, int $perPage = 25)
    {
        return $this->query($entityType)->paginate($perPage);
    }

    public function find(int $id): CustomFieldDefinition
    {
        return CustomFieldDefinition::findOrFail($id);
    }

    public function create(array $data): CustomFieldDefinition
    {
        $data['key'] = $data['key'] ?? Str::slug($data['name']);

        return CustomFieldDefinition::create($data);
    }

    public function update(CustomFieldDefinition $field, array $data): CustomFieldDefinition
    {
        $field->update($data);

        return $field;
    }

    public function delete(CustomFieldDefinition $field): void
    {
        $field->delete();
    }

    public function getFieldsForEntity(string $entityType)
    {
        return CustomFieldDefinition::where('entity_type', $entityType)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    public function validateValues(string $entityType, array $values): array
    {
        $definitions = $this->getFieldsForEntity($entityType)->keyBy('key');
        $errors = [];
        $validated = [];

        foreach ($values as $key => $value) {
            $definition = $definitions->get($key);

            if (! $definition) {
                continue;
            }

            if ($definition->is_required && ($value === null || $value === '')) {
                $errors[$key] = "{$definition->name} is required";

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $validated[$key] = $this->castValue($value, $definition);
        }

        foreach ($definitions as $definition) {
            if ($definition->is_required && ! array_key_exists($definition->key, $validated)) {
                $errors[$definition->key] = "{$definition->name} is required";
            }
        }

        return ['validated' => $validated, 'errors' => $errors];
    }

    public function setFieldValues(string $entityType, Model $entity, array $values): Model
    {
        $result = $this->validateValues($entityType, $values);

        if (! empty($result['errors'])) {
            throw new \InvalidArgumentException(json_encode($result['errors']));
        }

        $customFields = array_merge(
            $entity->custom_fields ?? [],
            $result['validated']
        );

        $entity->custom_fields = $customFields;
        $entity->save();

        return $entity;
    }

    public function getFieldValues(Model $entity): array
    {
        return $entity->custom_fields ?? [];
    }

    private function castValue(mixed $value, CustomFieldDefinition $definition): mixed
    {
        return match ($definition->type) {
            'number' => (int) $value,
            'decimal' => (float) $value,
            'checkbox' => (bool) $value,
            'multiselect' => is_array($value) ? $value : [$value],
            default => (string) $value,
        };
    }
}
