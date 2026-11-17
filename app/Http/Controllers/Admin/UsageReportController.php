<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PixelforgeException;
use App\Http\Controllers\Controller;
use App\Services\PixelforgeClient;
use App\Support\Nanodollars;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operators' view: what users were charged, what Pixelforge charged us, and the profit,
 * per UTC day (of completion, the same day Pixelforge bills on) and per user. Each day's
 * provider cost is checked against Pixelforge's own GET /v1/usage.
 */
final class UsageReportController extends Controller
{
    private const DAYS = 30;

    public function __invoke(PixelforgeClient $pixelforge): Response
    {
        $end = now()->startOfDay(); // app timezone is UTC, like Pixelforge's billing days
        $start = $end->subDays(self::DAYS - 1);

        // One row per (day, user). Includes the rare generation we didn't charge for
        // but Pixelforge billed anyway (see GenerationService), so costs still match.
        $rows = DB::table('generations')
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->where('generations.provider_cost_nanos', '>', 0)
            ->whereBetween('generations.completed_at', [$start, $end->endOfDay()])
            ->groupBy('day', 'users.id', 'users.name', 'users.email')
            ->orderByDesc('day')
            ->orderBy('users.name')
            ->get([
                DB::raw('DATE(generations.completed_at) as day'),
                'users.id as user_id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as generations'),
                DB::raw('SUM(COALESCE(generations.price_nanos, 0)) as charged_nanos'),
                DB::raw('SUM(generations.provider_cost_nanos) as provider_cost_nanos'),
            ])
            ->map(fn (object $row): array => [
                'day' => (string) $row->day,
                'user' => ['id' => (int) $row->user_id, 'name' => (string) $row->name, 'email' => (string) $row->email],
                'generations' => (int) $row->generations,
                'charged_nanos' => (int) $row->charged_nanos,
                'provider_cost_nanos' => (int) $row->provider_cost_nanos,
                'profit_nanos' => (int) $row->charged_nanos - (int) $row->provider_cost_nanos,
            ])
            ->all();

        // Pixelforge's own numbers for the same days.
        $pixelforgeByDay = [];
        $pixelforgeError = null;
        try {
            foreach ($pixelforge->usage($start->toDateString(), $end->toDateString())['data'] as $day) {
                $pixelforgeByDay[$day['date']] = Nanodollars::fromDecimalString($day['cost_usd']);
            }
        } catch (PixelforgeException $e) {
            $pixelforgeError = 'Could not load Pixelforge usage: '.$e->getMessage();
        }

        // Per-day totals. Days that only Pixelforge has costs for show up too.
        $days = [];
        foreach ($rows as $row) {
            $days[$row['day']] ??= ['day' => $row['day'], 'charged_nanos' => 0, 'provider_cost_nanos' => 0, 'profit_nanos' => 0];
            $days[$row['day']]['charged_nanos'] += $row['charged_nanos'];
            $days[$row['day']]['provider_cost_nanos'] += $row['provider_cost_nanos'];
            $days[$row['day']]['profit_nanos'] += $row['profit_nanos'];
        }
        foreach ($pixelforgeByDay as $date => $cost) {
            if ($cost > 0) {
                $days[$date] ??= ['day' => $date, 'charged_nanos' => 0, 'provider_cost_nanos' => 0, 'profit_nanos' => 0];
            }
        }
        foreach ($days as $date => $day) {
            $days[$date]['pixelforge_cost_nanos'] = $pixelforgeError === null ? ($pixelforgeByDay[$date] ?? 0) : null;
        }
        krsort($days);

        return Inertia::render('admin/UsageReport', [
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'days' => array_values($days),
            'rows' => $rows,
            'pixelforgeError' => $pixelforgeError,
        ]);
    }
}
