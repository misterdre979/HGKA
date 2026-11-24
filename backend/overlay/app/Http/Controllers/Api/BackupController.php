<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $disk = Storage::disk('local');

        return response()->json(
            collect($disk->files('backups'))->map(fn ($f) => [
                'name' => basename($f), 'size' => $disk->size($f), 'created_at' => date('c', $disk->lastModified($f)),
            ])->sortByDesc('created_at')->values()
        );
    }

    public function store(): JsonResponse
    {
        $code = Artisan::call('hgka:backup');
        abort_if($code !== 0, 500, 'Backup failed: '.trim(Artisan::output()));
        Audit::log('backup.created');

        return $this->index();
    }

    public function download(Request $request, string $name): StreamedResponse
    {
        abort_unless(preg_match('/^hgka-[0-9\-]+\.(sql\.gz|sqlite)$/', $name) === 1, 404);
        abort_unless(Storage::disk('local')->exists('backups/'.$name), 404, 'Backup not found.');
        Audit::log('backup.downloaded', null, null, ['file' => $name]);

        return Storage::disk('local')->download('backups/'.$name);
    }
}
