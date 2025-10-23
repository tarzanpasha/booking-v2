<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $companies = Company::all();
        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): CompanyResource
    {
        $company = Company::create($request->validated());
        return new CompanyResource($company);
    }

    public function show(Company $company): CompanyResource
    {
        return new CompanyResource($company);
    }

    public function update(StoreCompanyRequest $request, Company $company): CompanyResource
    {
        $company->update($request->validated());
        return new CompanyResource($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();
        return response()->json(['message' => 'Company deleted successfully']);
    }
}
