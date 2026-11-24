<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\NotificationService;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private NotificationService $notify)
    {
    }

    /** Issue, return or adjust stock. Stock can never go negative. */
    public function stock(Request $request, InventoryItem $item): JsonResponse
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(['issue', 'return', 'adjustment'])],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'issued_to' => ['nullable', 'string', 'max:255', 'required_if:type,issue'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($d['type'] !== 'adjustment' && $d['quantity'] < 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be positive.']);
        }

        $movement = DB::transaction(function () use ($item, $d, $request) {
            $locked = InventoryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $delta = $d['type'] === 'issue' ? -$d['quantity'] : $d['quantity'];
            $after = $locked->quantity + $delta;
            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => "Only {$locked->quantity} {$locked->unit}(s) in stock."]);
            }
            $locked->update(['quantity' => $after]);

            return StockMovement::create([
                'inventory_item_id' => $locked->id, 'type' => $d['type'], 'quantity' => abs($d['quantity']),
                'quantity_after' => $after, 'reference' => $d['reference'] ?? null, 'issued_to' => $d['issued_to'] ?? null,
                'notes' => $d['notes'] ?? null, 'user_id' => $request->user()->id,
            ]);
        });

        $fresh = $item->fresh();
        if ($d['type'] !== 'return' && $fresh->quantity <= $fresh->reorder_level && Settings::get('low_stock_notify', '1') === '1') {
            $this->notify->toRoles(['school-admin', 'bursar'], 'Low stock', "{$fresh->name} is down to {$fresh->quantity} (reorder level {$fresh->reorder_level}).", 'inventory');
        }
        Audit::log('inventory.stock_'.$d['type'], $item, null, ['qty' => $d['quantity'], 'after' => $movement->quantity_after]);

        return response()->json(['item' => $fresh, 'movement' => $movement], 201);
    }

    public function purchase(Request $request): JsonResponse
    {
        $d = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchase = DB::transaction(function () use ($d, $request) {
            $item = InventoryItem::whereKey($d['inventory_item_id'])->lockForUpdate()->firstOrFail();
            $after = $item->quantity + $d['quantity'];
            $item->update(['quantity' => $after, 'unit_cost' => $d['unit_cost']]);

            StockMovement::create([
                'inventory_item_id' => $item->id, 'type' => 'purchase', 'quantity' => $d['quantity'], 'quantity_after' => $after,
                'reference' => $d['invoice_no'] ?? null, 'notes' => $d['notes'] ?? null, 'user_id' => $request->user()->id,
            ]);

            return Purchase::create($d + ['total' => round($d['quantity'] * $d['unit_cost'], 2), 'recorded_by' => $request->user()->id]);
        });
        Audit::log('inventory.purchase', $purchase);

        return response()->json($purchase->load(['supplier', 'inventoryItem']), 201);
    }

    public function lowStock(): JsonResponse
    {
        return response()->json(InventoryItem::with('inventoryCategory')->whereColumn('quantity', '<=', 'reorder_level')->orderBy('name')->get());
    }
}
