<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

// Import jobs list endpoint
Route::get('/import-jobs', function () {
    $jobIds = Cache::get('import_jobs_list', []);
    $jobs = [];

    // Reverse to show newest first
    foreach (array_reverse($jobIds) as $jobId) {
        $progress = Cache::get("import_progress_{$jobId}");
        if ($progress) {
            $progress['job_id'] = $jobId;
            $jobs[] = $progress;
        }
    }

    return response()->json(['jobs' => $jobs]);
});

// Import logs endpoint
Route::get('/import-logs/{jobId}', function ($jobId) {
    $logs = Cache::get("import_logs_{$jobId}", []);
    return response()->json(['logs' => $logs]);
});

// Import progress endpoint
Route::get('/import-progress/{jobId}', function ($jobId) {
    $progress = Cache::get("import_progress_{$jobId}");
    return response()->json($progress);
});

// Enrichment logs endpoint
Route::get('/enrich-logs/{jobId}', function ($jobId) {
    $logs = Cache::get("enrich_logs_{$jobId}", []);
    return response()->json(['logs' => $logs]);
});

// Enrichment progress endpoint
Route::get('/enrich-progress/{jobId}', function ($jobId) {
    $progress = Cache::get("enrich_progress_{$jobId}");
    return response()->json($progress);
});