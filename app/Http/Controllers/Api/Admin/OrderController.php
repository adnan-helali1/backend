<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\StoreProduct;
use App\Models\StoreInventory;
use App\Models\SupplierProduct;
use App\Models\StoreLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::query()->with(['store', 'supplier']);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->query('store_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', (string) $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', (string) $request->query('to_date'));
        }

        $orders = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $orders,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function show(string $id)
    {
        $order = PurchaseOrder::query()
            ->with(['store', 'supplier', 'items.supplierProduct.supplier', 'items.supplierProduct.product.category'])
            ->findOrFail($id);

        return response()->json([
            'data' => $order,
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    /**
     * Update order status (Admin only)
     * 
     * Allowed status transitions:
     * - draft → submitted, cancelled
     * - submitted → received, cancelled
     * - received → (no transitions - final state)
     * - cancelled → (no transitions - final state)
     */
    public function updateStatus(Request $request, string $id)
    {
        // dd($request->status);
        $order = PurchaseOrder::query()
            ->with(['store', 'supplier', 'items.supplierProduct.product'])
            ->findOrFail($id);
// dd($request);
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:draft,submitted,received,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        // dd($request);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->input('status');
        $currentStatus = $order->status;

        // Check if transition is valid
        $validTransitions = [
            'draft' => ['submitted', 'cancelled'],
            'submitted' => ['received', 'cancelled'],
            'received' => [],  // Final state
            'cancelled' => [], // Final state
        ];

        if ($currentStatus === $newStatus) {
            return response()->json([
                'data' => null,
                'message' => 'Status is already set to ' . $newStatus,
                'errors' => ['status' => ['Order is already in this status']],
            ], 422);
        }

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'data' => null,
                'message' => 'Invalid status transition',
                'errors' => [
                    'status' => [
                        "Cannot change status from '{$currentStatus}' to '{$newStatus}'. " .
                        "Allowed transitions: " . implode(', ', $validTransitions[$currentStatus])
                    ]
                ],
            ], 422);
        }

        DB::transaction(function () use ($order, $newStatus, $request, $currentStatus) {
            $adminId = Auth::guard('admin_api')->id();

            // Handle specific status transitions
            if ($newStatus === 'received') {
                // When order is received, add items to store inventory
                foreach ($order->items as $item) {
                    $storeProduct = StoreProduct::where('store_id', $order->store_id)
                        ->where('supplier_product_id', $item->supplier_product_id)
                        ->first();

                    if ($storeProduct) {
                        $inventory = StoreInventory::firstOrCreate(
                            [
                                'store_id' => $order->store_id,
                                'store_product_id' => $storeProduct->id,
                            ],
                            [
                                'quantity' => 0,
                                'min_stock' => 10,
                            ]
                        );

                        $inventory->increment('quantity', $item->quantity);
                    }
                }

                // Add note to ledger entry if exists
                $ledgerEntry = StoreLedgerEntry::where('source_type', 'order')
                    ->where('source_id', $order->id)
                    ->where('type', 'debit')
                    ->first();

                if ($ledgerEntry && !$ledgerEntry->notes) {
                    $ledgerEntry->update([
                        'notes' => 'Order received and processed',
                    ]);
                }
            }

            if ($newStatus === 'cancelled' && $currentStatus !== 'draft') {
                // Return stock to supplier if order was submitted
                foreach ($order->items as $item) {
                    SupplierProduct::whereKey($item->supplier_product_id)
                        ->increment('stock_quantity', $item->quantity);
                }

                // Cancel ledger entry
                $ledgerEntry = StoreLedgerEntry::where('source_type', 'order')
                    ->where('source_id', $order->id)
                    ->where('type', 'debit')
                    ->first();

                if ($ledgerEntry) {
                    StoreLedgerEntry::create([
                        'store_id' => $order->store_id,
                        'type' => 'credit',
                        'source_type' => 'order',
                        'source_id' => $order->id,
                        'amount' => $order->total_buy,
                        'occurred_at' => now(),
                        'notes' => 'Order cancelled by admin' . ($request->input('notes') ? ': ' . $request->input('notes') : ''),
                        'created_by_admin_id' => $adminId,
                    ]);
                }
            }

            // Update order status
            $order->update([
                'status' => $newStatus,
                'notes' => $request->input('notes') 
                    ? ($order->notes ? $order->notes . "\n\n" : '') . '[Admin] ' . $request->input('notes')
                    : $order->notes,
            ]);
        });

        return response()->json([
            'data' => $order->fresh()->load(['store', 'supplier', 'items.supplierProduct.product']),
            'message' => 'Order status updated successfully',
            'errors' => null,
        ]);
    }
}
