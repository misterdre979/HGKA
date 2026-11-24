<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Resources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Generic, registry-driven CRUD. Every resource declares its own model, validation rules, permissions,
 * searchable/filterable columns and optional hooks in App\Support\Resources (generated from tools/spec.py).
 */
class CrudController extends Controller
{
    private function cfg(string $resource): array
    {
        $cfg = Resources::get($resource);
        abort_unless($cfg, 404, 'Unknown resource.');

        return $cfg;
    }

    private function authorizeAction(Request $request, array $cfg, string $action): void
    {
        abort_unless(
            $request->user()->hasAnyPermission($cfg['perms'][$action]),
            403,
            'You do not have permission to perform this action.'
        );
    }

    private function isAdminOf(Request $request, array $cfg): bool
    {
        return ! $cfg['own'] || $request->user()->hasPermission($cfg['own']['admin']);
    }

    private function checkOwner(Request $request, array $cfg, Model $model): void
    {
        if ($this->isAdminOf($request, $cfg)) {
            return;
        }
        abort_unless(
            (int) $model->{$cfg['own']['col']} === (int) $request->user()->id,
            403,
            'You can only modify your own records.'
        );
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $cfg = $this->cfg($resource);
        $this->authorizeAction($request, $cfg, 'view');

        $q = $cfg['model']::query()->with($cfg['with']);

        if (($cfg['own']['list'] ?? false) && ! $this->isAdminOf($request, $cfg)) {
            $q->where($cfg['own']['col'], $request->user()->id);
        }

        if (($s = trim((string) $request->query('search', ''))) !== '') {
            $q->where(function ($w) use ($cfg, $s) {
                foreach ($cfg['search'] as $col) {
                    $w->orWhere($col, 'like', '%'.$s.'%');
                }
            });
        }

        foreach ((array) $request->query('filter', []) as $col => $val) {
            if ($val === null || $val === '' || ! in_array($col, $cfg['filters'], true)) {
                continue;
            }
            $q->where($col, $val === 'true' ? 1 : ($val === 'false' ? 0 : $val));
        }

        $sort = (string) $request->query('sort', '');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sort !== '' && in_array($sort, $cfg['sortable'], true)) {
            $q->orderBy($sort, $dir);
        } else {
            $q->orderBy($cfg['order'][0], $cfg['order'][1]);
        }
        $q->orderBy('id', 'desc');

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return response()->json($q->paginate($perPage));
    }

    public function show(Request $request, string $resource, int $id): JsonResponse
    {
        $cfg = $this->cfg($resource);
        $this->authorizeAction($request, $cfg, 'view');
        $model = $cfg['model']::with($cfg['with'])->findOrFail($id);

        if (($cfg['own']['list'] ?? false)) {
            $this->checkOwner($request, $cfg, $model);
        }

        return response()->json($model);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $cfg = $this->cfg($resource);
        abort_if($cfg['readonly'] || $cfg['custom_create'], 405, 'This resource cannot be created here.');
        $this->authorizeAction($request, $cfg, 'create');

        $data = $this->validated($request, $cfg, null);
        $hooks = $cfg['hooks'];
        if ($hooks && method_exists($hooks, 'saving')) {
            $data = $hooks::saving($data, null, $request);
        }
        if ($cfg['creator']) {
            $data[$cfg['creator']] = $request->user()->id;
        }

        $model = DB::transaction(function () use ($cfg, $data, $hooks, $request) {
            $m = $cfg['model']::create($data);
            if ($hooks && method_exists($hooks, 'saved')) {
                $hooks::saved($m, $request, true);
            }

            return $m;
        });

        Audit::log("{$resource}.created", $model, null, $model->getAttributes());

        return response()->json($model->load($cfg['with']), 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $cfg = $this->cfg($resource);
        abort_if($cfg['readonly'], 405, 'This resource is read-only.');
        $this->authorizeAction($request, $cfg, 'update');

        $model = $cfg['model']::findOrFail($id);
        $this->checkOwner($request, $cfg, $model);

        $data = $this->validated($request, $cfg, $model->getKey());
        $hooks = $cfg['hooks'];
        if ($hooks && method_exists($hooks, 'saving')) {
            $data = $hooks::saving($data, $model, $request);
        }

        $old = array_intersect_key($model->getAttributes(), $data);
        DB::transaction(function () use ($model, $data, $hooks, $request) {
            $model->update($data);
            if ($hooks && method_exists($hooks, 'saved')) {
                $hooks::saved($model, $request, false);
            }
        });

        Audit::log("{$resource}.updated", $model, $old, $data);

        return response()->json($model->fresh($cfg['with']));
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $cfg = $this->cfg($resource);
        abort_if($cfg['readonly'], 405, 'This resource is read-only.');
        $this->authorizeAction($request, $cfg, 'delete');

        $model = $cfg['model']::findOrFail($id);
        $this->checkOwner($request, $cfg, $model);

        $hooks = $cfg['hooks'];
        if ($hooks && method_exists($hooks, 'deleting')) {
            $hooks::deleting($model, $request);
        }

        $attrs = $model->getAttributes();
        $model->delete();
        Audit::log("{$resource}.deleted", $model, $attrs);

        return response()->json(['message' => 'Deleted.']);
    }

    private function validated(Request $request, array $cfg, ?int $id): array
    {
        $rules = [];
        foreach ($cfg['rules'] as $field => $fieldRules) {
            if (in_array($field, $cfg['unique'], true)) {
                $unique = Rule::unique($cfg['table'], $field);
                $fieldRules[] = $id ? $unique->ignore($id) : $unique;
            }
            $rules[$field] = $id ? array_merge(['sometimes'], $fieldRules) : $fieldRules;
        }

        $data = $request->validate($rules);

        // Columns that are NOT NULL (have defaults or are required) must never receive an explicit null.
        foreach ($cfg['nonnull'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
