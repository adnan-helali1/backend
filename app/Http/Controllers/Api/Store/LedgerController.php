<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $store = Auth::guard('store_api')->user();

        $entries = StoreLedgerEntry::query()
            ->where('store_id', $store->id)
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 20));

        $totalCredits = StoreLedgerEntry::where('store_id', $store->id)->where('type', 'credit')->sum('amount');
        $totalDebits = StoreLedgerEntry::where('store_id', $store->id)->where('type', 'debit')->sum('amount');
        $balance = (float) $totalCredits - (float) $totalDebits;

        return response()->json([
            'data' => [
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
}
