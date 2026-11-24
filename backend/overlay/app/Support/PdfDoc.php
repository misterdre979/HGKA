<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Renders branded PDF documents (receipts, report cards, payslips, reports). */
class PdfDoc
{
    private const CREST = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="M32 3 56 12v20c0 14-10 24-24 29C18 56 8 46 8 32V12z" fill="#102b50"/><path d="M32 8 51 15v17c0 11-8 19-19 24C21 51 13 43 13 32V15z" fill="none" stroke="#e5a52b" stroke-width="1.5"/><path d="M32 40c-5-3-11-3-16-1V25c5-2 11-2 16 1 5-3 11-3 16-1v14c-5-2-11-2-16 1z" fill="#246bce"/><path d="M32 26v14" stroke="#fff" stroke-width="1.5"/><path d="m32 11 2.2 4.6 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5-3.6-3.5 5-.7z" fill="#e5a52b"/></svg>';

    /** Data URI of the uploaded school logo, or the built-in HGKA crest. */
    public static function logo(): string
    {
        $path = Settings::get('logo_path');
        if ($path && Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
        }

        return 'data:image/svg+xml;base64,'.base64_encode(self::CREST);
    }

    public static function download(string $view, array $data, string $filename, string $paper = 'a4', string $orientation = 'portrait'): Response
    {
        $data += ['logo' => self::logo(), 'school' => Settings::all()];

        return Pdf::loadView($view, $data)->setPaper($paper, $orientation)->download($filename);
    }
}
