<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMetricRequest;
use App\Models\Site;
use App\Services\MetricIngestionService;
use App\Services\MetricStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricController extends Controller
{
    public function __construct(
        protected MetricIngestionService $metricService,
        protected MetricStatsService $statsService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Get traffic stats for a site.
     */
    public function show(Site $site): JsonResponse
    {
        return response()->json($this->statsService->getStats($site));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMetricRequest $request)
    {
        $data = $request->validated();

        $success = $this->metricService->process($data);

        if (! $success) {
            return response()->json(['error' => 'Invalid, expired, or inactive license'], 401);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
