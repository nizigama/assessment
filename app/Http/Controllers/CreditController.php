<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BuyCreditsRequest;
use App\Services\CreditLedger;
use App\Support\Nanodollars;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class CreditController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('credits/Index', [
            'packs' => BuyCreditsRequest::PACKS,
            'transactions' => $request->user()->creditTransactions()
                ->with('generation:id,prompt')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn ($t): array => [
                    'id' => $t->id,
                    'type' => $t->type->value,
                    'amount_nanos' => $t->amount_nanos,
                    'prompt' => $t->generation?->prompt,
                    'created_at' => $t->created_at->toIso8601String(),
                ]),
        ]);
    }

    /** Fake purchase: adds credits without taking any payment. */
    public function store(BuyCreditsRequest $request, CreditLedger $ledger): RedirectResponse
    {
        $dollars = (int) $request->validated('dollars');

        DB::transaction(fn () => $ledger->purchase($request->user(), $dollars * Nanodollars::PER_DOLLAR));

        return back()->with('success', "Added \${$dollars} in credits.");
    }
}
