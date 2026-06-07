<?php

namespace App\Http\Controllers;

use App\ERP\Inventory\Models\Shelf;
use App\ERP\Inventory\Models\ShelfSlot;
use App\Http\Resources\ShelfItemResource;
use App\Http\Resources\ShelfResource;
use App\Models\MasterProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShelfController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('ERP/Inventory/ShelfMap');
    }

    public function index(): JsonResponse
    {
        $shelves = Shelf::with('slots')
            ->orderBy('row_position')
            ->orderBy('col_position')
            ->get();

        return response()->json(ShelfResource::collection($shelves));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:shelves,code',
            'name' => 'required|string|max:255',
            'row_position' => 'required|integer|min:0',
            'col_position' => 'required|integer|min:0',
        ]);

        $shelf = Shelf::create($validated);

        return response()->json(new ShelfResource($shelf->load('slots')), 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $shelf = Shelf::findOrFail($id);
        $shelf->delete();

        return response()->json(['deleted' => true]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $shelf = Shelf::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:shelves,code,'.$id,
            'name' => 'required|string|max:255',
            'row_position' => 'required|integer|min:0',
            'col_position' => 'required|integer|min:0',
        ]);

        $shelf->update($validated);

        return response()->json(new ShelfResource($shelf->load('slots')));
    }

    public function items(int $id): JsonResponse
    {
        $shelf = Shelf::with('slots.product')->findOrFail($id);

        return response()->json(new ShelfItemResource($shelf));
    }

    public function storeSlot(Request $request, int $shelfId): JsonResponse
    {
        $request->validate([
            'tier' => 'required|integer|between:1,4',
            'slot_position' => 'required|integer|min:0',
            'product_id' => 'nullable|integer|exists:master_products,id',
            'qty' => 'required|integer|min:0',
            'min_qty' => 'required|integer|min:0',
        ]);

        $shelf = Shelf::findOrFail($shelfId);

        // Max 6 slots per tier
        $currentCount = $shelf->slots()->where('tier', $request->integer('tier'))->count();
        $slotPosition = $request->integer('slot_position');
        $existing = $shelf->slots()->where('tier', $request->integer('tier'))->where('slot_position', $slotPosition)->first();

        if (! $existing && $currentCount >= 6) {
            return response()->json(['message' => 'Maksimal 6 slot per tingkat.'], 422);
        }

        $slot = $shelf->slots()->updateOrCreate(
            [
                'tier' => $request->integer('tier'),
                'slot_position' => $slotPosition,
            ],
            [
                'product_id' => $request->integer('product_id'),
                'qty' => $request->integer('qty'),
                'min_qty' => $request->integer('min_qty'),
            ]
        );

        return response()->json([
            'id' => $slot->id,
            'tier' => $slot->tier,
            'slot_position' => $slot->slot_position,
            'product_name' => $slot->product?->name ?? 'Kosong',
            'sku' => $slot->product?->sku ?? '-',
            'qty' => $slot->qty,
            'min_qty' => $slot->min_qty,
        ], $slot->wasRecentlyCreated ? 201 : 200);
    }

    public function updatePosition(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'row_position' => 'required|integer|min:0',
            'col_position' => 'required|integer|min:0',
        ]);

        $shelf = Shelf::findOrFail($id);
        $shelf->update([
            'row_position' => $request->integer('row_position'),
            'col_position' => $request->integer('col_position'),
        ]);

        return response()->json(new ShelfResource($shelf));
    }

    public function updateSlot(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'qty' => 'required|integer|min:0',
        ]);

        $slot = ShelfSlot::findOrFail($id);
        $slot->update(['qty' => $request->integer('qty')]);

        return response()->json(['id' => $slot->id, 'qty' => $slot->qty]);
    }

    public function destroySlot(int $id): JsonResponse
    {
        $slot = ShelfSlot::findOrFail($id);
        $slot->delete();

        return response()->json(['deleted' => true]);
    }

    public function moveSlot(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'slot_position' => 'required|integer|min:0',
        ]);

        $slot = ShelfSlot::findOrFail($id);
        $newPos = $request->integer('slot_position');
        $oldPos = $slot->slot_position;
        $tier = $slot->tier;
        $shelfId = $slot->shelf_id;

        if ($newPos === $oldPos) {
            return response()->json(['moved' => false]);
        }

        // Shift other slots in same tier to make room
        if ($newPos > $oldPos) {
            ShelfSlot::where('shelf_id', $shelfId)
                ->where('tier', $tier)
                ->where('slot_position', '>', $oldPos)
                ->where('slot_position', '<=', $newPos)
                ->decrement('slot_position');
        } else {
            ShelfSlot::where('shelf_id', $shelfId)
                ->where('tier', $tier)
                ->where('slot_position', '>=', $newPos)
                ->where('slot_position', '<', $oldPos)
                ->increment('slot_position');
        }

        $slot->update(['slot_position' => $newPos]);

        return response()->json(['moved' => true]);
    }

    public function productSearch(Request $request): JsonResponse
    {
        $q = $request->string('q', '')->toString();

        $products = MasterProduct::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qry) use ($q) {
                    $qry->where('sku', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'sku', 'name']);

        return response()->json($products);
    }
}
