<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreWhatsAppAccountRequest;
use App\Http\Requests\Crm\UpdateWhatsAppAccountRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\WhatsAppAccountResource;
use App\Http\Resources\Tenant\Api\V1\Crm\WhatsAppPhoneNumberResource;
use App\Models\Crm\WhatsAppAccount;
use App\Services\ApiResponseService;
use App\Services\Crm\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WhatsAppAccountController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly WhatsAppAccountService $whatsAppAccountService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', WhatsAppAccount::class);

        $perPage = min((int) request('per_page', 25), 100);
        $accounts = $this->whatsAppAccountService->paginateWithFilters(request()->only([
            'search', 'status', 'provider', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('WhatsApp accounts retrieved successfully', WhatsAppAccountResource::collection($accounts));
    }

    public function store(StoreWhatsAppAccountRequest $request): JsonResponse
    {
        Gate::authorize('create', WhatsAppAccount::class);

        $account = $this->whatsAppAccountService->create($request->validated());

        return $this->api->success('WhatsApp account created successfully', new WhatsAppAccountResource($account), 201);
    }

    public function show(WhatsAppAccount $whatsAppAccount): JsonResponse
    {
        Gate::authorize('view', $whatsAppAccount);

        $account = $this->whatsAppAccountService->find($whatsAppAccount->id);

        return $this->api->success('WhatsApp account retrieved successfully', new WhatsAppAccountResource($account));
    }

    public function update(UpdateWhatsAppAccountRequest $request, WhatsAppAccount $whatsAppAccount): JsonResponse
    {
        Gate::authorize('update', $whatsAppAccount);

        $account = $this->whatsAppAccountService->update($whatsAppAccount, $request->validated());

        return $this->api->success('WhatsApp account updated successfully', new WhatsAppAccountResource($account));
    }

    public function destroy(WhatsAppAccount $whatsAppAccount): JsonResponse
    {
        Gate::authorize('delete', $whatsAppAccount);

        $this->whatsAppAccountService->delete($whatsAppAccount);

        return $this->api->success('WhatsApp account deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', WhatsAppAccount::class);

        $this->whatsAppAccountService->restore($id);

        return $this->api->success('WhatsApp account restored successfully');
    }

    public function connect(StoreWhatsAppAccountRequest $request): JsonResponse
    {
        Gate::authorize('create', WhatsAppAccount::class);

        $account = $this->whatsAppAccountService->connect($request->validated());

        return $this->api->success('WhatsApp account connected successfully', new WhatsAppAccountResource($account), 201);
    }

    public function disconnect(WhatsAppAccount $whatsAppAccount): JsonResponse
    {
        Gate::authorize('update', $whatsAppAccount);

        $this->whatsAppAccountService->disconnect($whatsAppAccount);

        return $this->api->success('WhatsApp account disconnected successfully');
    }

    public function syncPhoneNumbers(WhatsAppAccount $whatsAppAccount): JsonResponse
    {
        Gate::authorize('update', $whatsAppAccount);

        $phoneNumbers = request()->input('phone_numbers');

        $this->whatsAppAccountService->syncPhoneNumbers($whatsAppAccount, $phoneNumbers);

        $whatsAppAccount->load('phoneNumbers');

        return $this->api->success('Phone numbers synced successfully', WhatsAppPhoneNumberResource::collection($whatsAppAccount->phoneNumbers));
    }
}
