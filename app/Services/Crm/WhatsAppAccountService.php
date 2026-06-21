<?php

namespace App\Services\Crm;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Models\Crm\WhatsAppAccount;
use App\Models\Crm\WhatsAppPhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WhatsAppAccountService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'provider', 'business_account_id', 'app_id',
        'status', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    private const META_GRAPH_VERSION = 'v22.0';

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function query(): Builder
    {
        return WhatsAppAccount::query()
            ->with(['phoneNumbers'])
            ->orderBy('created_at', 'desc');
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 25)
    {
        $query = $this->query();

        if ($search = $filters['search'] ?? null) {
            $ids = WhatsAppAccount::search($search)->keys();
            $query->whereIn((new WhatsAppAccount)->getQualifiedKeyName(), $ids);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($provider = $filters['provider'] ?? null) {
            $query->where('provider', $provider);
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortBy = in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $sortBy : 'created_at';

        $sortOrder = $filters['sort_order'] ?? 'desc';
        $sortOrder = in_array($sortOrder, self::ALLOWED_DIRECTIONS, true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): WhatsAppAccount
    {
        return WhatsAppAccount::with(['phoneNumbers'])
            ->findOrFail($id);
    }

    public function create(array $data): WhatsAppAccount
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $account = WhatsAppAccount::create($data);

        $this->eventDispatcher->record($account, 'whatsapp.account_connected', 'WhatsApp Account Connected', $account->business_account_id, [
            'provider' => $account->provider->value,
        ], Auth::id());

        return $account;
    }

    public function update(WhatsAppAccount $account, array $data): WhatsAppAccount
    {
        $data['updated_by'] = Auth::id();
        $account->update($data);
        $account->refresh();

        $this->eventDispatcher->record($account, 'whatsapp.account_updated', 'WhatsApp Account Updated', $account->business_account_id, null, Auth::id());

        return $account;
    }

    public function delete(WhatsAppAccount $account): void
    {
        $this->eventDispatcher->record($account, 'whatsapp.account_disconnected', 'WhatsApp Account Disconnected', $account->business_account_id, null, Auth::id());
        $account->delete();
    }

    public function restore(int $id): void
    {
        WhatsAppAccount::withTrashed()->findOrFail($id)->restore();
    }

    public function connect(array $data): WhatsAppAccount
    {
        return $this->create($data);
    }

    public function disconnect(WhatsAppAccount $account): void
    {
        $account->update([
            'status' => WhatsAppAccountStatusEnum::DISCONNECTED,
            'updated_by' => Auth::id(),
        ]);

        $this->eventDispatcher->record($account, 'whatsapp.account_disconnected', 'WhatsApp Account Disconnected', $account->business_account_id, null, Auth::id());
    }

    public function syncPhoneNumbers(WhatsAppAccount $account, ?array $providedNumbers = null): void
    {
        DB::transaction(function () use ($account, $providedNumbers) {
            if ($providedNumbers !== null) {
                foreach ($providedNumbers as $numberData) {
                    WhatsAppPhoneNumber::updateOrCreate(
                        [
                            'tenant_id' => $account->tenant_id,
                            'whatsapp_account_id' => $account->id,
                            'phone_number_id' => $numberData['phone_number_id'],
                        ],
                        [
                            'display_phone_number' => $numberData['display_phone_number'] ?? '',
                            'verified_name' => $numberData['verified_name'] ?? '',
                            'quality_rating' => $numberData['quality_rating'] ?? null,
                            'status' => $numberData['status'] ?? 'connected',
                            'metadata' => $numberData['metadata'] ?? null,
                        ]
                    );
                }

                $this->eventDispatcher->record($account, 'whatsapp.phone_numbers_synced', 'WhatsApp Phone Numbers Synced', null, [
                    'count' => count($providedNumbers),
                ], Auth::id());

                return;
            }

            $response = Http::withToken($account->access_token)
                ->get("https://graph.facebook.com/{$account->business_account_id}/phone_numbers");

            if ($response->successful()) {
                $data = $response->json();

                foreach ($data['data'] ?? [] as $numberData) {
                    WhatsAppPhoneNumber::updateOrCreate(
                        [
                            'tenant_id' => $account->tenant_id,
                            'whatsapp_account_id' => $account->id,
                            'phone_number_id' => $numberData['id'],
                        ],
                        [
                            'display_phone_number' => $numberData['display_phone_number'] ?? '',
                            'verified_name' => $numberData['verified_name'] ?? '',
                            'quality_rating' => $numberData['quality_rating'] ?? null,
                            'status' => $numberData['code_verification_status'] ?? 'connected',
                            'metadata' => $numberData,
                        ]
                    );
                }

                $this->eventDispatcher->record($account, 'whatsapp.phone_numbers_synced', 'WhatsApp Phone Numbers Synced', null, [
                    'count' => count($data['data'] ?? []),
                ], Auth::id());
            }
        });
    }
}
