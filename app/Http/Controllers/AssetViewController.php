<?php

namespace App\Http\Controllers;

use App\Models\AssetType;
use App\Models\AssetView;
use App\Models\AssetViewLine;
use App\Services\AssetViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssetViewController extends Controller
{
    public function page(AssetViewService $views): Response
    {
        return Inertia::render('asset-views', [
            'assetViews' => $views->availableViews(null, true)->map(fn (AssetView $view): array => $views->payload($view))->values(),
            'assetTypes' => AssetType::query()->availableToOwner()->where('is_active', true)->orderBy('label')->get()->map(fn (AssetType $type): array => [
                'id' => $type->id,
                'key' => $type->key,
                'label' => $type->label,
                'class' => $type->class,
            ])->values(),
        ]);
    }

    public function index(AssetViewService $views): JsonResponse
    {
        return response()->json($views->availableViews(null, true)->map(fn (AssetView $view): array => $views->payload($view))->values());
    }

    public function store(Request $request, AssetViewService $views): JsonResponse|RedirectResponse
    {
        $data = $this->viewData($request);
        $view = DB::transaction(function () use ($data, $request): AssetView {
            $view = AssetView::create($data + ['user_id' => $request->user()->id, 'is_system' => false, 'is_core' => false, 'is_active' => true]);
            AssetViewLine::create(['asset_view_id' => $view->id, 'key' => 'unassigned_other', 'label' => 'Unassigned / Other', 'sort_order' => 9999, 'is_active' => true, 'is_fallback' => true]);

            return $view;
        });

        return $this->respond($request, $views->payload($view->fresh('lines.assetTypes')), 'Asset view created.', 201);
    }

    public function update(Request $request, AssetView $assetView, AssetViewService $views): JsonResponse|RedirectResponse
    {
        $this->assertCustomView($request, $assetView);
        $assetView->update($this->viewData($request, true));

        return $this->respond($request, $views->payload($assetView->fresh('lines.assetTypes')), 'Asset view updated.');
    }

    public function archive(Request $request, AssetView $assetView): RedirectResponse
    {
        $this->assertCustomView($request, $assetView);
        $assetView->update(['is_active' => false]);

        return back()->with('success', 'Asset view archived.');
    }

    public function restore(Request $request, AssetView $assetView): RedirectResponse
    {
        $this->assertCustomView($request, $assetView);
        $assetView->update(['is_active' => true]);

        return back()->with('success', 'Asset view restored.');
    }

    public function storeLine(Request $request, AssetView $assetView, AssetViewService $views): JsonResponse|RedirectResponse
    {
        $this->assertCustomView($request, $assetView);
        $data = $this->lineData($request);
        $line = $assetView->lines()->create($data + ['is_active' => true, 'is_fallback' => false]);

        return $this->respond($request, $views->payload($assetView->fresh('lines.assetTypes')), 'View line created.', 201);
    }

    public function updateLine(Request $request, AssetView $assetView, AssetViewLine $line, AssetViewService $views): JsonResponse|RedirectResponse
    {
        $this->assertLine($request, $assetView, $line);
        $line->update($this->lineData($request, true));

        return $this->respond($request, $views->payload($assetView->fresh('lines.assetTypes')), 'View line updated.');
    }

    public function archiveLine(Request $request, AssetView $assetView, AssetViewLine $line): RedirectResponse
    {
        $this->assertLine($request, $assetView, $line);
        $line->update(['is_active' => false]);

        return back()->with('success', 'View line archived.');
    }

    public function restoreLine(Request $request, AssetView $assetView, AssetViewLine $line): RedirectResponse
    {
        $this->assertLine($request, $assetView, $line);
        $line->update(['is_active' => true]);

        return back()->with('success', 'View line restored.');
    }

    public function reorderLines(Request $request, AssetView $assetView): RedirectResponse
    {
        $this->assertCustomView($request, $assetView);
        $ids = $request->validate(['line_ids' => ['required', 'array'], 'line_ids.*' => ['integer', 'distinct']])['line_ids'];
        $lines = $assetView->lines()->where('is_fallback', false)->whereIn('id', $ids)->get();
        abort_if($lines->count() !== count($ids), 422, 'Every line must belong to this view.');
        foreach (array_values($ids) as $index => $id) {
            $lines->firstWhere('id', $id)?->update(['sort_order' => $index * 10]);
        }

        return back()->with('success', 'View lines reordered.');
    }

    public function assignType(Request $request, AssetView $assetView, AssetViewLine $line, AssetViewService $views): JsonResponse|RedirectResponse
    {
        $this->assertLine($request, $assetView, $line);
        $typeId = (int) $request->validate(['asset_type_id' => ['required', 'integer']])['asset_type_id'];
        abort_unless(AssetType::query()->availableToOwner()->whereKey($typeId)->where('is_active', true)->exists(), 422, 'That asset type is not available.');
        abort_if(DB::table('asset_view_line_asset_type')->where('asset_view_id', $assetView->id)->where('asset_type_id', $typeId)->exists(), 422, 'This asset type is already assigned in this view.');
        $line->assetTypes()->attach($typeId, ['asset_view_id' => $assetView->id, 'sort_order' => 100]);

        return $this->respond($request, $views->payload($assetView->fresh('lines.assetTypes')), 'Asset type assigned.');
    }

    public function removeType(Request $request, AssetView $assetView, AssetViewLine $line, AssetType $assetType): RedirectResponse
    {
        $this->assertLine($request, $assetView, $line);
        $line->assetTypes()->detach($assetType->id);

        return back()->with('success', 'Asset type removed from this view.');
    }

    private function assertCustomView(Request $request, AssetView $view): void
    {
        abort_if($view->is_system || $view->user_id !== $request->user()->id, 404);
    }

    private function assertLine(Request $request, AssetView $view, AssetViewLine $line): void
    {
        $this->assertCustomView($request, $view);
        abort_if($line->asset_view_id !== $view->id || $line->is_fallback, 404);
    }

    /** @return array<string, mixed> */
    private function viewData(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'key' => [$editing ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('asset_views')->where(fn ($query) => $query->where('user_id', $request->user()->id))->ignore($editing ? $request->route('assetView')?->id : null)],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function lineData(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'key' => [$editing ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    private function respond(Request $request, array $payload, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json($payload, $status) : back()->with('success', $message);
    }
}
