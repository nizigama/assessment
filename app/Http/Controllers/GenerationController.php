<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Services\GenerationService;
use App\Services\PixelforgeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GenerationController extends Controller
{
    /** History: the user's generations, newest first. */
    public function index(Request $request): Response
    {
        $generations = $request->user()->generations()->latest()->paginate(12);

        return Inertia::render('generations/Index', [
            'generations' => GenerationResource::collection($generations),
            'hasActive' => $request->user()->generations()
                ->whereIn('status', [GenerationStatus::Pending, GenerationStatus::Queued, GenerationStatus::Processing])
                ->exists(),
        ]);
    }

    /** The Generate form. Pricing tables are passed so the page can show the cost as you type. */
    public function create(PixelforgeClient $pixelforge): Response
    {
        $pricing = $pixelforge->pricing();

        return Inertia::render('generations/Create', [
            'pricing' => ['rates' => $pricing['rates'], 'tokens' => $pricing['tokens']],
            'sizes' => array_column(ImageSize::cases(), 'value'),
            'qualities' => array_column(ImageQuality::cases(), 'value'),
        ]);
    }

    public function store(StoreGenerationRequest $request, GenerationService $generations): RedirectResponse
    {
        $generations->create(
            $request->user(),
            $request->validated('prompt'),
            ImageSize::from($request->validated('size')),
            ImageQuality::from($request->validated('quality')),
            $request->referenceImages(),
        );

        return to_route('generations.index')->with('success', 'Sent to Pixelforge. It usually takes 10 to 40 seconds.');
    }
}
