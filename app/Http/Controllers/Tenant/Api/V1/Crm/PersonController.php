<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StorePersonRequest;
use App\Http\Requests\Crm\UpdatePersonRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\PersonResource;
use App\Models\Crm\Person;
use App\Services\ApiResponseService;
use App\Services\Crm\PersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PersonController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PersonService $personService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Person::class);

        $perPage = min((int) request('per_page', 25), 100);
        $people = $this->personService->paginateWithFilters(request()->only([
            'search', 'status_id', 'source_id', 'owner_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('People retrieved successfully', PersonResource::collection($people));
    }

    public function store(StorePersonRequest $request): JsonResponse
    {
        Gate::authorize('create', Person::class);

        $person = $this->personService->create($request->validated());

        return $this->api->success('Person created successfully', new PersonResource($person), 201);
    }

    public function show(Person $person): JsonResponse
    {
        Gate::authorize('view', $person);

        $person = $this->personService->find($person->id);

        return $this->api->success('Person retrieved successfully', new PersonResource($person));
    }

    public function update(UpdatePersonRequest $request, Person $person): JsonResponse
    {
        Gate::authorize('update', $person);

        $person = $this->personService->update($person, $request->validated());

        return $this->api->success('Person updated successfully', new PersonResource($person));
    }

    public function destroy(Person $person): JsonResponse
    {
        Gate::authorize('delete', $person);

        $this->personService->delete($person);

        return $this->api->success('Person deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Person::class);

        $this->personService->restore($id);

        return $this->api->success('Person restored successfully');
    }
}
