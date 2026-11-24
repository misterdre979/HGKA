<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\LessonNote;
use App\Models\Staff;
use App\Models\Student;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private file storage for student documents, staff employment documents and lesson-note files.
 * Files live on the non-public disk under random names and are only served through authorised endpoints.
 */
class AttachmentController extends Controller
{
    private const TYPES = [
        'student' => ['model' => Student::class, 'read' => ['students.view'], 'write' => ['students.update']],
        'staff' => ['model' => Staff::class, 'read' => ['staff.view'], 'write' => ['staff.update']],
        'lesson-note' => ['model' => LessonNote::class, 'read' => ['lesson-notes.view', 'lesson-notes.manage'], 'write' => ['lesson-notes.manage']],
    ];

    private function type(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404, 'Unknown attachment type.');

        return self::TYPES[$type];
    }

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $t = $this->type($type);
        abort_unless($request->user()->hasAnyPermission($t['read']), 403, 'You do not have permission to perform this action.');
        $t['model']::findOrFail($id);

        return response()->json(Attachment::where('attachable_type', $type)->where('attachable_id', $id)->latest()->get());
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $t = $this->type($type);
        abort_unless($request->user()->hasAnyPermission($t['write']), 403, 'You do not have permission to perform this action.');
        $owner = $t['model']::findOrFail($id);

        if ($type === 'lesson-note' && ! $request->user()->hasPermission('academics.manage')) {
            abort_unless((int) $owner->created_by === (int) $request->user()->id, 403, 'You can only attach files to your own lesson notes.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt'],
            'title' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'string', 'max:60'],
        ]);

        $file = $request->file('file');
        $path = Storage::disk('local')->putFile("attachments/{$type}/{$id}", $file);

        $attachment = Attachment::create([
            'attachable_type' => $type, 'attachable_id' => $id, 'kind' => $data['kind'] ?? null,
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 250),
            'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
        Audit::log('attachments.uploaded', $attachment, null, ['type' => $type, 'id' => $id]);

        return response()->json($attachment, 201);
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $t = $this->type($attachment->attachable_type);
        abort_unless($request->user()->hasAnyPermission($t['read']), 403, 'You do not have permission to perform this action.');
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'File not found.');

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $t = $this->type($attachment->attachable_type);
        abort_unless($request->user()->hasAnyPermission($t['write']), 403, 'You do not have permission to perform this action.');

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();
        Audit::log('attachments.deleted', $attachment);

        return response()->json(['message' => 'File removed.']);
    }
}
