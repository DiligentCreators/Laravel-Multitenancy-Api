<?php

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaxRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRegionController extends Controller
{
    public function index(): JsonResponse
    {
        $regions = TaxRegion::withCount('taxRates')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax regions retrieved.',
            'data' => $regions->items(),
            'meta' => [
                'current_page' => $regions->currentPage(),
                'last_page' => $regions->lastPage(),
                'per_page' => $regions->perPage(),
                'total' => $regions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:tax_regions,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $region = TaxRegion::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax region created.',
            'data' => $region,
        ], 201);
    }

    public function show(TaxRegion $taxRegion): JsonResponse
    {
        $taxRegion->load('taxRates');

        return response()->json([
            'status' => 'success',
            'message' => 'Tax region retrieved.',
            'data' => $taxRegion,
        ]);
    }

    public function update(Request $request, TaxRegion $taxRegion): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:10|unique:tax_regions,code,'.$taxRegion->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $taxRegion->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax region updated.',
            'data' => $taxRegion,
        ]);
    }

    public function destroy(TaxRegion $taxRegion): JsonResponse
    {
        $taxRegion->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tax region deleted.',
        ]);
    }
}
