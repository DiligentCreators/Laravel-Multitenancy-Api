<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreAddressRequest;
use App\Http\Requests\Crm\UpdateAddressRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\AddressResource;
use App\Models\Crm\Address;
use App\Services\ApiResponseService;
use App\Services\Crm\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly AddressService $addressService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Address::class);

        $perPage = min((int) request('per_page', 25), 100);
        $addresses = $this->addressService->paginate($perPage);

        return $this->api->success('Addresses retrieved successfully', AddressResource::collection($addresses));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        Gate::authorize('create', Address::class);

        $address = $this->addressService->create($request->validated());

        return $this->api->success('Address created successfully', new AddressResource($address), 201);
    }

    public function show(Address $address): JsonResponse
    {
        Gate::authorize('view', $address);

        return $this->api->success('Address retrieved successfully', new AddressResource($address));
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        Gate::authorize('update', $address);

        $address = $this->addressService->update($address, $request->validated());

        return $this->api->success('Address updated successfully', new AddressResource($address));
    }

    public function destroy(Address $address): JsonResponse
    {
        Gate::authorize('delete', $address);

        $this->addressService->delete($address);

        return $this->api->success('Address deleted successfully');
    }

    public function byEntity(string $type, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Address::class);

        $perPage = min((int) request('per_page', 25), 100);
        $addresses = $this->addressService->getForEntityPaginated($type, $id, $perPage);

        return $this->api->success('Addresses retrieved successfully', AddressResource::collection($addresses));
    }
}
