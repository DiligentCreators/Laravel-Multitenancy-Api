<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailTemplateService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'slug', 'subject', 'is_active', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected EmailTemplate $emailTemplate,
        protected EmailTemplateVersion $version,
    ) {}

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->emailTemplate
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $ids = EmailTemplate::search($search)->keys();
                $query->whereIn((new EmailTemplate)->getQualifiedKeyName(), $ids);
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

    public function find(int|string $id): EmailTemplate
    {
        return $this->emailTemplate->query()->findOrFail($id);
    }

    public function create(array $data): EmailTemplate
    {
        return DB::transaction(function () use ($data) {
            if (! isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $data['variables'] = $this->extractVariables($data['body']);

            $template = $this->emailTemplate->create($data);

            $this->createVersion($template, 1);

            return $template;
        });
    }

    public function update(EmailTemplate $emailTemplate, array $data): EmailTemplate
    {
        $emailTemplate->update($data);

        return $emailTemplate;
    }

    public function delete(EmailTemplate $emailTemplate): void
    {
        $emailTemplate->delete();
    }

    public function preview(EmailTemplate $emailTemplate, array $variables = []): array
    {
        $subject = $this->replaceVariables($emailTemplate->subject, $variables);
        $body = $this->replaceVariables($emailTemplate->body, $variables);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public function sendTest(EmailTemplate $emailTemplate, string $recipient, array $variables = []): void
    {
        $preview = $this->preview($emailTemplate, $variables);

        Mail::raw($preview['body'], function ($message) use ($recipient, $preview) {
            $message->to($recipient)
                ->subject($preview['subject']);
        });
    }

    public function duplicate(EmailTemplate $emailTemplate): EmailTemplate
    {
        $data = $emailTemplate->toArray();
        $data['name'] = $data['name'].' (Copy)';
        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);

        return $this->emailTemplate->create($data);
    }

    public function createVersion(EmailTemplate $emailTemplate, ?int $versionNumber = null): EmailTemplateVersion
    {
        $latestVersion = $emailTemplate->versions()->max('version') ?? 0;
        $versionNumber ??= $latestVersion + 1;

        return $emailTemplate->versions()->create([
            'version' => $versionNumber,
            'subject' => $emailTemplate->subject,
            'body' => $emailTemplate->body,
            'variables' => $emailTemplate->variables,
        ]);
    }

    public function getVersions(EmailTemplate $emailTemplate): Collection
    {
        return $emailTemplate->versions()->orderBy('version', 'desc')->get();
    }

    private function extractVariables(string $content): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $content, $matches);

        return array_unique($matches[1]);
    }

    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        return $content;
    }
}
