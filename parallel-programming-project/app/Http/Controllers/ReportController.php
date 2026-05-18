<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDailyReport;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = Report::orderBy('date', 'desc')->get();

        return response()->json($reports);
    }

    public function generate(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        GenerateDailyReport::dispatchSync($date);

        return response()->json(['message' => 'Full report generation started']);
    }

    public function show(Report $report)
    {
        return response()->json($report);
    }
}
