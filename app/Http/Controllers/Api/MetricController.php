<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMetricRequest;
use App\Services\MetricIngestionService;
use Illuminate\Http\Request;

class MetricController extends Controller
{
    protected MetricIngestionService $metricService;

    public function __construct(MetricIngestionService $metricService)
    {
        $this->metricService = $metricService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMetricRequest $request)
    {
        $data = $request->validated();

        $success = $this->metricService->process($data);

        if (!$success) {
            return response()->json(['error' => 'Invalid, expired, or inactive license'], 401);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
