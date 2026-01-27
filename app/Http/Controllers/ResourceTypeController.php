<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Actions\StoreResourceTypeAction;
use App\Http\Requests\StoreResourceTypeRequest;
use App\Http\Resources\ResourceTypeResource;
use App\Models\ResourceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceTypeController extends Controller
{
    public function __construct(
        private CreateOrUpdateCompanyAction $createOrUpdateCompanyAction,
        private StoreResourceTypeAction $storeResourceTypeAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $resourceTypes = ResourceType::with(['company', 'timetable', 'resources'])->get();
        return ResourceTypeResource::collection($resourceTypes);
    }

    public function store(StoreResourceTypeRequest $request): ResourceTypeResource
    {
        $data = $request->validated();

        // Создаем или обновляем компанию если указан company_id
        if (isset($data['company_id'])) {
            $this->createOrUpdateCompanyAction->execute($data['company_id']);
        }

        $resourceType = $this->storeResourceTypeAction->execute($data);

        return new ResourceTypeResource($resourceType->load(['company', 'timetable', 'resources']));
    }

    public function show(ResourceType $resourceType): ResourceTypeResource
    {
        return new ResourceTypeResource($resourceType->load(['company', 'timetable', 'resources']));
    }

    public function update(StoreResourceTypeRequest $request, ResourceType $resourceType): ResourceTypeResource
    {
        $resourceType->update($request->validated());
        return new ResourceTypeResource($resourceType->load(['company', 'timetable', 'resources']));
    }

    public function destroy(ResourceType $resourceType): JsonResponse
    {
        $resourceType->delete();
        return response()->json(['message' => 'Resource type deleted successfully']);
    }
}
