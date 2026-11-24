<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Fmt;
use App\Support\Resources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lightweight {id, label} lists for select inputs. Requires view or create permission on the target resource. */
class LookupController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $cfg = Resources::get($resource);
        abort_unless($cfg, 404, 'Unknown resource.');
        abort_unless(
            $request->user()->hasAnyPermission(array_merge($cfg['perms']['view'], $cfg['perms']['create'])),
            403,
            'You do not have permission to perform this action.'
        );

        $q = $cfg['model']::query();
        $relation = $cfg['lbl_rel'] ? \Illuminate\Support\Str::camel($cfg['lbl_rel']) : null;
        if ($relation) {
            $q->with($relation);
        }

        if ($ids = array_filter((array) $request->query('ids', []))) {
            $q->whereIn('id', $ids);
        } else {
            if (($s = trim((string) $request->query('q', ''))) !== '') {
                $cols = $cfg['search'] ?: $cfg['lbl'];
                $q->where(function ($w) use ($cols, $s) {
                    foreach ($cols as $c) {
                        $w->orWhere($c, 'like', '%'.$s.'%');
                    }
                });
            }
            foreach ((array) $request->query('filter', []) as $col => $val) {
                if ($val !== null && $val !== '' && in_array($col, $cfg['filters'], true)) {
                    $q->where($col, $val);
                }
            }
            $q->limit(200);
        }

        $q->orderBy($cfg['order'][0], $cfg['order'][1]);

        $rows = $q->get()->map(function ($m) use ($cfg, $relation) {
            $label = Fmt::label($m, $cfg['lbl'], $cfg['lbl_fmt']);
            if ($relation && $m->{$relation}) {
                $label = trim(($m->{$relation}->name ?? '').' '.$label);
            }

            return ['id' => $m->id, 'label' => $label];
        });

        return response()->json($rows->values());
    }
}
