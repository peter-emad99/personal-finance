<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:40'],
            'channel' => ['nullable', 'in:web,mcp,cli,unknown'],
            'entity' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::query()->latest('id');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['channel'])) {
            $filters['channel'] === 'unknown'
                ? $query->whereNull('channel')
                : $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['entity'])) {
            $query->where('entity_type', $filters['entity']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.addcslashes($filters['search'], '%_').'%';
            $query->where(function ($query) use ($search): void {
                $query->where('tool_name', 'like', $search)
                    ->orWhere('agent_id', 'like', $search)
                    ->orWhere('request_id', 'like', $search)
                    ->orWhere('entity_type', 'like', $search)
                    ->orWhere('action', 'like', $search)
                    ->orWhere('before_state', 'like', $search)
                    ->orWhere('after_state', 'like', $search);
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $paginator = $query->paginate(30)->withQueryString();
        $entries = collect($paginator->items())
            ->map(fn (AuditLog $audit): array => $this->entry($audit))
            ->values();

        return Inertia::render('activity-log', [
            'entries' => $entries,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'action' => $filters['action'] ?? '',
                'channel' => $filters['channel'] ?? '',
                'entity' => $filters['entity'] ?? '',
                'search' => $filters['search'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->map(fn (string $action): array => ['value' => $action, 'label' => Str::headline($action)])
                ->values(),
            'entities' => AuditLog::query()
                ->select('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type')
                ->map(fn (string $entity): array => [
                    'value' => $entity,
                    'label' => Str::headline(class_basename($entity)),
                ])
                ->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function entry(AuditLog $audit): array
    {
        $before = is_array($audit->before_state) ? $audit->before_state : [];
        $after = is_array($audit->after_state) ? $audit->after_state : [];
        $fields = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $field = (string) $field;
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;
            if ($beforeValue !== $afterValue) {
                $fields[] = [
                    'field' => $field,
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }
        $state = $after ?: $before;

        return [
            'id' => $audit->id,
            'action' => $audit->action,
            'actionLabel' => Str::headline($audit->action),
            'channel' => $audit->channel ?: 'unknown',
            'entityType' => class_basename((string) $audit->entity_type),
            'entityClass' => $audit->entity_type,
            'entityId' => $audit->entity_id,
            'entityLabel' => $this->entityLabel($audit, $state),
            'toolName' => $audit->tool_name,
            'agentId' => $audit->agent_id,
            'requestId' => $audit->request_id,
            'beforeState' => $before,
            'afterState' => $after,
            'changedFields' => $fields,
            'dashboardVersion' => $audit->dashboard_version,
            'createdAt' => $audit->created_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $state */
    private function entityLabel(AuditLog $audit, array $state): string
    {
        foreach (['name', 'description', 'file_name', 'title'] as $key) {
            if (filled($state[$key] ?? null)) {
                return (string) $state[$key];
            }
        }

        $type = Str::headline(class_basename((string) $audit->entity_type));

        return $audit->entity_id === null ? $type : $type.' #'.$audit->entity_id;
    }
}
