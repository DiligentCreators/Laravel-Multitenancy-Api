<?php

namespace App\Services\Crm;

use App\Models\Crm\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TagService
{
    public function __construct(
        private readonly MorphableEntityResolver $morphResolver,
    ) {}

    public function query(): Builder
    {
        return Tag::query()->orderBy('name');
    }

    public function paginate(int $perPage = 25)
    {
        return $this->query()->paginate($perPage);
    }

    public function find(int $id): Tag
    {
        return Tag::findOrFail($id);
    }

    public function create(array $data): Tag
    {
        return Tag::create($data);
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->update($data);

        return $tag;
    }

    public function delete(Tag $tag): void
    {
        $tag->delete();
    }

    public function merge(Tag $source, Tag $target): Tag
    {
        DB::transaction(function () use ($source, $target) {
            $existing = $target->taggables()->pluck('taggable_type', 'taggable_id');

            $new = $source->taggables()
                ->whereNotIn('taggable_id', $existing->keys())
                ->get()
                ->map(fn ($t) => [
                    'tag_id' => $target->id,
                    'taggable_type' => $t->taggable_type,
                    'taggable_id' => $t->taggable_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($new->isNotEmpty()) {
                DB::table('crm_taggables')->insert($new->toArray());
            }

            $source->taggables()->delete();
            $source->delete();
        });

        return $target;
    }

    public function bulkAttach(string $entityType, array $entityIds, array $tagIds): void
    {
        $modelClass = $this->resolveMorphClass($entityType);

        $existingIds = $modelClass::whereIn('id', $entityIds)
            ->whereHas('tags', fn ($q) => $q->whereIn('crm_tags.id', $tagIds))
            ->pluck('id');

        $inserts = [];

        foreach ($entityIds as $entityId) {
            if (! $existingIds->contains($entityId)) {
                foreach ($tagIds as $tagId) {
                    $inserts[] = [
                        'tag_id' => $tagId,
                        'taggable_type' => $modelClass,
                        'taggable_id' => $entityId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (! empty($inserts)) {
            DB::table('crm_taggables')->insert($inserts);
        }
    }

    private function resolveMorphClass(string $entityType): string
    {
        return $this->morphResolver->resolve($entityType);
    }
}
