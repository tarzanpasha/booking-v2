<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Actions\StoreTimetableAction;
use App\Actions\UpdateTimetableAction;
use App\Http\Requests\StoreTimetableRequest;
use App\Http\Requests\UpdateTimetableRequest;
use App\Http\Resources\TimetableResource;
use App\Models\Timetable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimetableController extends Controller
{
    public function __construct(
        private CreateOrUpdateCompanyAction $createOrUpdateCompanyAction,
        private StoreTimetableAction $storeTimetableAction,
        private UpdateTimetableAction $updateTimetableAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $timetables = Timetable::with(['company', 'resourceTypes', 'resources'])->get();
        return TimetableResource::collection($timetables);
    }

    public function store(StoreTimetableRequest $request): TimetableResource
    {
        $data = $request->validated();

        // Создаем или обновляем компанию
        $this->createOrUpdateCompanyAction->execute($data['company_id']);

        $timetable = $this->storeTimetableAction->execute($data);

        return new TimetableResource($timetable->load(['company', 'resourceTypes', 'resources']));
    }

    public function show(Timetable $timetable): TimetableResource
    {
        return new TimetableResource($timetable->load(['company', 'resourceTypes', 'resources']));
    }

    public function update(UpdateTimetableRequest $request, Timetable $timetable): TimetableResource
    {
        $data = $request->validated();

        $timetable = $this->updateTimetableAction->execute($timetable, $data);

        return new TimetableResource($timetable->load(['company', 'resourceTypes', 'resources']));
    }

    public function destroy(Timetable $timetable): JsonResponse
    {
        $timetable->delete();
        return response()->json(['message' => 'Timetable deleted successfully']);
    }

    public function attachResource(Timetable $timetable, Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id'
        ]);

        $resource = \App\Models\Resource::find($request->resource_id);
        $resource->update(['timetable_id' => $timetable->id]);

        return response()->json(['message' => 'Resource attached to timetable successfully']);
    }

    public function detachResource(Timetable $timetable, Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id'
        ]);

        $resource = \App\Models\Resource::find($request->resource_id);
        if ($resource->timetable_id === $timetable->id) {
            $resource->update(['timetable_id' => null]);
        }

        return response()->json(['message' => 'Resource detached from timetable successfully']);
    }

    public function attachResourceType(Timetable $timetable, Request $request): JsonResponse
    {
        $request->validate([
            'resource_type_id' => 'required|exists:resource_types,id'
        ]);

        $resourceType = \App\Models\ResourceType::find($request->resource_type_id);
        $resourceType->update(['timetable_id' => $timetable->id]);

        return response()->json(['message' => 'Resource type attached to timetable successfully']);
    }

    public function detachResourceType(Timetable $timetable, Request $request): JsonResponse
    {
        $request->validate([
            'resource_type_id' => 'required|exists:resource_types,id'
        ]);

        $resourceType = \App\Models\ResourceType::find($request->resource_type_id);
        if ($resourceType->timetable_id === $timetable->id) {
            $resourceType->update(['timetable_id' => null]);
        }

        return response()->json(['message' => 'Resource type detached from timetable successfully']);
    }
}
