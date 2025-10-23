<?php

namespace App\Actions;

use App\Models\Resource;

class StoreResourceAction
{
    public function execute(array $data): Resource
    {
        // Если company_id не указан, берем из типа ресурса
        if (!isset($data['company_id'])) {
            $resourceType = \App\Models\ResourceType::find($data['resource_type_id']);
            if ($resourceType) {
                $data['company_id'] = $resourceType->company_id;
            }
        }

        return Resource::create($data);
    }
}
