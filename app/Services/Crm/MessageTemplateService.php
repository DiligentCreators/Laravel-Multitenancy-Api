<?php

namespace App\Services\Crm;

use App\Models\Crm\MessageTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MessageTemplateService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'channel', 'category', 'is_active',
        'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(): Builder
    {
        return MessageTemplate::query()
            ->orderBy('name', 'asc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = MessageTemplate::search($search)->keys();
            $query->whereIn((new MessageTemplate)->getQualifiedKeyName(), $ids);
        }

        if ($channel = $filters['channel'] ?? null) {
            $query->where('channel', $channel);
        }

        if ($category = $filters['category'] ?? null) {
            $query->where('category', $category);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'name';

        $sortOrder = $filters['sort_order'] ?? 'asc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'asc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): MessageTemplate
    {
        return MessageTemplate::findOrFail($id);
    }

    public function create(array $data): MessageTemplate
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return MessageTemplate::create($data);
    }

    public function update(MessageTemplate $template, array $data): MessageTemplate
    {
        $data['updated_by'] = Auth::id();
        $template->update($data);

        return $template;
    }

    public function delete(MessageTemplate $template): void
    {
        $template->delete();
    }

    public function restore(int $id): void
    {
        MessageTemplate::withTrashed()->findOrFail($id)->restore();
    }

    public function forceDelete(int $id): void
    {
        MessageTemplate::withTrashed()->findOrFail($id)->forceDelete();
    }
}
