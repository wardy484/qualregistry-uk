<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class IngestionOpsController extends Controller
{
    public function runAllEngland(Request $request): JsonResponse
    {
        if (! app()->environment('local')) {
            abort(403, 'Manual ingestion trigger is only enabled in local environment.');
        }

        $runDate = $request->input('run_date');

        $exitCode = Artisan::call('ingest:all-england', array_filter([
            '--run-date' => $runDate,
        ], fn ($value) => is_string($value) && $value !== ''));

        return response()->json([
            'status' => $exitCode === 0 ? 'ok' : 'failed',
            'exit_code' => $exitCode,
            'output' => Artisan::output(),
        ], $exitCode === 0 ? 200 : 500);
    }

    public function reports(Request $request): JsonResponse
    {
        if (! app()->environment('local')) {
            abort(403, 'Report browsing endpoint is only enabled in local environment.');
        }

        $limit = max(1, (int) $request->integer('limit', 10));
        $base = base_path('reports/ingestion');

        if (! File::isDirectory($base)) {
            return response()->json([
                'reports' => [],
                'message' => 'No reports generated yet.',
            ]);
        }

        $reports = collect(File::allFiles($base))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'run-report'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take($limit)
            ->map(fn ($file) => [
                'path' => str_replace(base_path().'/', '', $file->getPathname()),
                'updated_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $file->getMTime()),
            ])
            ->values();

        return response()->json(['reports' => $reports]);
    }
}
