<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\SmsTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmsTemplateService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'slug', 'message', 'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected SmsTemplate $smsTemplate,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->smsTemplate
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $ids = SmsTemplate::search($search)->keys();
                $query->whereIn((new SmsTemplate)->getQualifiedKeyName(), $ids);
            })
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($sort, $direction);
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)->paginate($perPage)->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): SmsTemplate
    {
        return $this->smsTemplate->query()->findOrFail($id);
    }

    public function create(array $data): SmsTemplate
    {
        if (! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->smsTemplate->create($data);
    }

    public function update(SmsTemplate $smsTemplate, array $data): SmsTemplate
    {
        $smsTemplate->update($data);

        return $smsTemplate;
    }

    public function delete(SmsTemplate $smsTemplate): void
    {
        $smsTemplate->delete();
    }
}
