<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrUpdateCompanyAction;
use App\Actions\StoreResourceAction;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceController extends Controller
{
    public function __construct(
        private CreateOrUpdateCompanyAction $createOrUpdateCompanyAction,
        private StoreResourceAction $storeResourceAction
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $resources = Resource::with(['company', 'timetable', 'resourceType'])->get();
        return ResourceResource::collection($resources);
    }

    public function store(StoreResourceRequest $request): ResourceResource
    {
        $data = $request->validated();

        // Создаем или обновляем компанию если указан company_id
        if (isset($data['company_id'])) {
            $this->createOrUpdateCompanyAction->execute($data['company_id']);
        }

        $resource = $this->storeResourceAction->execute($data);

        return new ResourceResource($resource->load(['company', 'timetable', 'resourceType']));
    }

    public function show(Resource $resource): ResourceResource
    {
        return new ResourceResource($resource->load(['company', 'timetable', 'resourceType']));
    }

    public function update(StoreResourceRequest $request, Resource $resource): ResourceResource
    {
        $resource->update($request->validated());
        return new ResourceResource($resource->load(['company', 'timetable', 'resourceType']));
    }

    public function destroy(Resource $resource): JsonResponse
    {
        $resource->delete();
        return response()->json(['message' => 'Resource deleted successfully']);
    }
}
