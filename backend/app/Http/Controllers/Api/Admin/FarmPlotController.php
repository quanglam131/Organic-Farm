<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FarmPlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmPlotController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FarmPlot::withCount('logs')->orderBy('code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(creating: true));

        return response()->json(FarmPlot::create($data), 201);
    }

    public function update(Request $request, FarmPlot $farmPlot): JsonResponse
    {
        $farmPlot->update($request->validate($this->rules(creating: false, ignoreId: $farmPlot->id)));

        return response()->json($farmPlot->fresh());
    }

    public function destroy(FarmPlot $farmPlot): JsonResponse
    {
        $farmPlot->delete();

        return response()->json(['message' => 'Đã xóa lô canh tác.']);
    }

    private function rules(bool $creating, ?int $ignoreId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'code' => [
                $required,
                'string',
                'max:30',
                Rule::unique('farm_plots', 'code')->ignore($ignoreId),
            ],
            'name' => [$required, 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:255'],
            'area_m2' => ['nullable', 'numeric', 'min:0'],
            'certification' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ];
    }
}
