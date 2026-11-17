<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, GenerationService $generations): Response
    {
        $event = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'data' => ['array'],
        ]);

        $generations->handleWebhook($event + ['data' => []]);

        return response()->noContent();
    }
}
