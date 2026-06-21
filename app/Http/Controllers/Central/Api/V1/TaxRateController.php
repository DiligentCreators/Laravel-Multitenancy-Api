<?php

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function index(): JsonResponse
    {
        $rates = TaxRate::with('taxRegion')
            ->orderByDesc('is_active')
            ->orderBy('rate')
            ->paginate(50);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rates retrieved.',
            'data' => $rates->items(),
            'meta' => [
                'current_page' => $rates->currentPage(),
                'last_page' => $rates->lastPage(),
                'per_page' => $rates->perPage(),
                'total' => $rates->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tax_region_id' => 'required|exists:tax_regions,id',
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'string|in:percentage,fixed',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $rate = TaxRate::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate created.',
            'data' => $rate->load('taxRegion'),
        ], 201);
    }

    public function show(TaxRate $taxRate): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate retrieved.',
            'data' => $taxRate->load('taxRegion'),
        ]);
    }

    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $validated = $request->validate([
            'tax_region_id' => 'exists:tax_regions,id',
            'name' => 'string|max:255',
            'rate' => 'numeric|min:0|max:100',
            'type' => 'string|in:percentage,fixed',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $taxRate->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate updated.',
            'data' => $taxRate->load('taxRegion'),
        ]);
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        $taxRate->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tax rate deleted.',
        ]);
    }
}
