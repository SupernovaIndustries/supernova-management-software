<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    return response()->json([
        'csrf_token' => csrf_token(),
        'session_driver' => config('session.driver'),
        'session_id' => session()->getId(),
        'app_url' => config('app.url'),
        'app_env' => config('app.env'),
    ]);
});

Route::post('/test-post', function () {
    return response()->json(['status' => 'ok']);
})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Datasheet download route
Route::get('/datasheets/{datasheet}/download', function (\App\Models\GeneratedDatasheet $datasheet) {
    // Check if user has permission to download
    if (!auth()->check()) {
        abort(403, 'Access denied');
    }
    
    // Check if file exists
    if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($datasheet->file_path)) {
        abort(404, 'File not found');
    }
    
    // Get file content
    $fileContent = \Illuminate\Support\Facades\Storage::disk('local')->get($datasheet->file_path);
    $fileName = "{$datasheet->title}.{$datasheet->file_format}";
    
    // Determine content type
    $contentType = match($datasheet->file_format) {
        'pdf' => 'application/pdf',
        'html' => 'text/html',
        'markdown' => 'text/markdown',
        default => 'application/octet-stream',
    };
    
    return response($fileContent)
        ->header('Content-Type', $contentType)
        ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"");
})->name('datasheets.download');

// User Manual download route
Route::get('/user-manuals/{manual}/download', function (\App\Models\UserManual $manual) {
    // Check if user has permission to download
    if (!auth()->check()) {
        abort(403, 'Access denied');
    }
    
    // Check if manual is downloadable
    if (!$manual->isDownloadable()) {
        abort(404, 'Manual not ready for download');
    }
    
    // Get file content
    $fileContent = \Illuminate\Support\Facades\Storage::get($manual->file_path);
    $fileName = "{$manual->title} v{$manual->version}.{$manual->format}";
    
    // Clean filename from special characters
    $fileName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $fileName);
    
    // Determine content type
    $contentType = match($manual->format) {
        'pdf' => 'application/pdf',
        'html' => 'text/html',
        'markdown' => 'text/markdown',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        default => 'application/octet-stream',
    };
    
    return response($fileContent)
        ->header('Content-Type', $contentType)
        ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"");
})->name('user-manuals.download');

// Mobile app routes
Route::get('/mobile', [\App\Http\Controllers\MobileController::class, 'index'])->name('mobile.app');

// Mobile API routes
Route::prefix('api/mobile')->group(function () {
    Route::get('/status', [\App\Http\Controllers\MobileController::class, 'status']);
    Route::get('/inventory', [\App\Http\Controllers\MobileController::class, 'inventory']);
    Route::get('/component/{id}', [\App\Http\Controllers\MobileController::class, 'component']);
    Route::get('/aruco/{markerId}', [\App\Http\Controllers\MobileController::class, 'aruco']);
    Route::get('/checklists', [\App\Http\Controllers\MobileController::class, 'checklists']);
    Route::get('/checklist/{id}', [\App\Http\Controllers\MobileController::class, 'checklist']);
    Route::post('/checklist/{checklistId}/item/{itemId}/response', [\App\Http\Controllers\MobileController::class, 'updateChecklistResponse']);
    Route::post('/checklist/{checklistId}/item/{itemId}/attachment', [\App\Http\Controllers\MobileController::class, 'uploadAttachment']);
    Route::get('/projects', [\App\Http\Controllers\MobileController::class, 'projects']);
    Route::get('/project/{id}', [\App\Http\Controllers\MobileController::class, 'project']);
    Route::post('/scan', [\App\Http\Controllers\MobileController::class, 'recordScan']);
});

// Named route for mobile checklist access
Route::get('/mobile/checklist/{id}', function ($id) {
    return redirect("/mobile#checklist-{$id}");
})->name('mobile.checklist');

// Board QR Code download route
Route::get('/board-qr-codes/{qrCode}/download', function (\App\Models\BoardQrCode $qrCode) {
    // Check if user has permission to download
    if (!auth()->check()) {
        abort(403, 'Access denied');
    }

    // Download QR code from Nextcloud
    $qrCodeService = new \App\Services\BoardQrCodeService();
    $tempPath = $qrCodeService->downloadQrCode($qrCode);

    if (!$tempPath || !file_exists($tempPath)) {
        abort(404, 'QR Code file not found');
    }

    // Get file content
    $fileContent = file_get_contents($tempPath);

    // Generate filename from path
    $fileName = basename($qrCode->qr_file_path);

    // Delete temporary file after reading
    if (file_exists($tempPath)) {
        unlink($tempPath);
    }

    return response($fileContent)
        ->header('Content-Type', 'image/svg+xml')
        ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"");
})->name('board-qr-code.download');

// Download all QR codes for an assembly log as ZIP
Route::get('/projects/{project}/board-assembly-logs/{assemblyLog}/download-qr', function (\App\Models\Project $project, \App\Models\BoardAssemblyLog $assemblyLog) {
    if (!auth()->check()) {
        abort(403, 'Access denied');
    }

    $qrCodeService = new \App\Services\BoardQrCodeService();
    $qrCodes = $assemblyLog->qrCodes;

    if ($qrCodes->isEmpty()) {
        abort(404, 'No QR codes found');
    }

    // Create temporary ZIP file
    $zipFileName = "QR_Codes_{$assemblyLog->batch_number}.zip";
    $zipPath = storage_path("app/temp/{$zipFileName}");

    // Ensure temp directory exists
    if (!file_exists(dirname($zipPath))) {
        mkdir(dirname($zipPath), 0755, true);
    }

    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        abort(500, 'Could not create ZIP file');
    }

    // Add each QR code to ZIP
    foreach ($qrCodes as $qrCode) {
        $tempPath = $qrCodeService->downloadQrCode($qrCode);
        if ($tempPath && file_exists($tempPath)) {
            $fileName = basename($qrCode->qr_file_path);
            $zip->addFile($tempPath, $fileName);
        }
    }

    $zip->close();

    // Clean up temp files
    foreach ($qrCodes as $qrCode) {
        $tempPath = storage_path('app/temp/qr-downloads/' . basename($qrCode->qr_file_path));
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
})->name('filament.admin.resources.projects.board-assembly-logs.download-qr');
// DDT viewing route
Route::get('/admin/projects/{project}/board-assembly-logs/{assemblyLog}/view-ddt', function ($project, $assemblyLog) {
    $log = \App\Models\BoardAssemblyLog::findOrFail($assemblyLog);

    if (!$log->hasDdt()) {
        abort(404, 'DDT not found');
    }

    $service = new \App\Services\DdtService();
    $signed = $log->isDdtSigned();
    $pdfPath = $service->downloadDdtPdf($log, $signed);

    if ($pdfPath && file_exists($pdfPath)) {
        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($pdfPath) . '"'
        ]);
    }

    abort(404, 'DDT PDF not found');
})->name('filament.admin.resources.projects.board-assembly-logs.view-ddt');

// Import Progress Monitoring Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/import-monitor', function () {
        return view('import-monitor');
    })->name('import-monitor');

    Route::get('/admin/import-progress/{jobId}', function ($jobId) {
        return view('import-progress', ['jobId' => $jobId]);
    })->name('import-progress');

    Route::get('/admin/enrich-progress/{jobId}', function ($jobId) {
        return view('enrich-progress', ['jobId' => $jobId]);
    })->name('enrich-progress');
});
