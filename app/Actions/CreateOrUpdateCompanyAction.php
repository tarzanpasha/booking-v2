<?php

namespace App\Actions;

use App\Models\Company;

class CreateOrUpdateCompanyAction
{
    public function execute(int $companyId, ?string $name = null): Company
    {
        $company = Company::find($companyId);

        if (!$company) {
            $company = Company::create([
                'id' => $companyId,
                'name' => $name ?? 'Компания_' . $companyId,
            ]);
        }

        return $company;
    }
}
