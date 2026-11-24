<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /** Branding only; safe for the unauthenticated login page. */
    public function publicSettings(): JsonResponse
    {
        $all = Settings::all();
        $out = array_intersect_key($all, array_flip(Settings::PUBLIC_KEYS));
        $out['logo_url'] = $out['logo_path'] ? asset('storage/'.$out['logo_path']) : null;
        unset($out['logo_path']);

        return response()->json($out);
    }

    public function index(): JsonResponse
    {
        $all = Settings::all();
        $all['logo_url'] = $all['logo_path'] ? asset('storage/'.$all['logo_path']) : null;

        return response()->json($all);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_name' => ['sometimes', 'required', 'string', 'max:150'],
            'school_short_name' => ['sometimes', 'required', 'string', 'max:20'],
            'motto' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'currency_symbol' => ['sometimes', 'string', 'max:5'],
            'timezone' => ['sometimes', 'timezone'],
            'primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'max_ca_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_exam_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'library_loan_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'library_fine_per_day' => ['sometimes', 'numeric', 'min:0'],
            'notify_parents_on_absence' => ['sometimes', 'boolean'],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'next_term_begins' => ['nullable', 'date'],
            'low_stock_notify' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['max_ca_score'], $data['max_exam_score']) && $data['max_ca_score'] + $data['max_exam_score'] !== 100) {
            abort(422, 'CA and exam maximum scores must add up to 100.');
        }
        foreach (['notify_parents_on_absence', 'low_stock_notify'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = $data[$k] ? '1' : '0';
            }
        }

        Settings::set($data);
        Audit::log('settings.updated', null, null, $data);

        return $this->index();
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);

        if ($old = Settings::get('logo_path')) {
            Storage::disk('public')->delete($old);
        }
        $path = $request->file('logo')->store('branding', 'public');
        Settings::set(['logo_path' => $path]);
        Audit::log('settings.logo_updated');

        return $this->index();
    }
}
