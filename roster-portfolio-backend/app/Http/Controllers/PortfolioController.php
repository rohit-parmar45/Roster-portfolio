<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Services\PortfolioScraperService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class PortfolioController extends Controller
{
    protected $scraperService;

    public function __construct(PortfolioScraperService $scraperService)
    {
        $this->scraperService = $scraperService;
    }

    /**
     * Store or retrieve a scraped portfolio by URL.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid URL provided.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $url = $request->input('url');
        $refresh = $request->boolean('refresh', false);

        try {
            // Use existing data unless force-refresh is requested
            if (!$refresh) {
                $existing = Portfolio::where('url', $url)->with(['employers.videos'])->first();
                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Portfolio already exists.',
                        'data' => $existing,
                    ]);
                }
            }

            // Scrape and store
            $data = $this->scraperService->scrapePortfolio($url);
            $portfolio = $this->scraperService->storePortfolioData($data, $url);

            return response()->json([
                'success' => true,
                'message' => 'Portfolio scraped and stored successfully.',
                'data' => $portfolio->load(['employers.videos']),
            ], 201);

        } catch (Exception $e) {
            Log::error('Portfolio scraping error', ['url' => $url, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to scrape portfolio. Please verify the URL or try a different one.',
            ], 500);
        }
    }

    /**
     * Get all portfolios.
     */
    public function index(): JsonResponse
    {
        $portfolios = Portfolio::with(['employers.videos'])->get();

        return response()->json([
            'success' => true,
            'data' => $portfolios,
        ]);
    }

    /**
     * Get a specific portfolio by ID.
     */
    public function show($id): JsonResponse
    {
        $portfolio = Portfolio::with(['employers.videos'])->find($id);

        if (!$portfolio) {
            return response()->json([
                'success' => false,
                'message' => 'Portfolio not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $portfolio,
        ]);
    }
}
