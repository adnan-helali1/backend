<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    public function ledger(Request $request, string $storeId)
    {
        $store = Store::findOrFail($storeId);

        $entries = StoreLedgerEntry::query()
            ->where('store_id', $store->id)
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 20));

        $totalCredits = StoreLedgerEntry::where('store_id', $store->id)->where('type', 'credit')->sum('amount');
        $totalDebits = StoreLedgerEntry::where('store_id', $store->id)->where('type', 'debit')->sum('amount');
        $balance = (float) $totalCredits - (float) $totalDebits;

        return response()->json([
            'data' => [
                'store' => $store,
                'entries' => $entries,
                'summary' => [
                    'total_credits' => (float) $totalCredits,
                    'total_debits' => (float) $totalDebits,
                    'balance' => $balance,
                ],
            ],
            'message' => 'Success',
            'errors' => null,
        ]);
    }

    public function payment(Request $request, string $storeId)
    {
        $store = Store::findOrFail($storeId);
        $admin = Auth::guard('admin_api')->user();

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $entry = StoreLedgerEntry::create([
            'store_id' => $store->id,
            'type' => 'credit',
            'source_type' => 'payment',
            'source_id' => null,
            'amount' => (float) $data['amount'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by_admin_id' => $admin?->id,
        ]);

        return response()->json([
            'data' => $entry,
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }

    public function adjustment(Request $request, string $storeId)
    {
        $store = Store::findOrFail($storeId);
        $admin = Auth::guard('admin_api')->user();

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => null,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $entry = StoreLedgerEntry::create([
            'store_id' => $store->id,
            'type' => (string) $data['type'],
            'source_type' => 'adjustment',
            'source_id' => null,
            'amount' => (float) $data['amount'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by_admin_id' => $admin?->id,
        ]);

        return response()->json([
            'data' => $entry,
            'message' => 'Created',
            'errors' => null,
        ], 201);
    }
}
