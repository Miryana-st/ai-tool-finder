<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreCompanyRoleRequest;
use App\Http\Requests\User\UpdateCompanyRoleRequest;
use App\Http\Resources\User\CompanyRoleResource;
use App\Models\User\CompanyRole;
use App\Services\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CompanyRoleController extends Controller
{
    /**
     * List all company roles. Public, so a registration form can offer them.
     * The same for every user, so it is cached (see CatalogCache).
     */
    public function index(CatalogCache $cache): JsonResponse
    {
        return response()->json($cache->remember('company-roles', fn () => CompanyRoleResource::collection(
            CompanyRole::orderBy('name')->get(),
        )->response()->getData(true)));
    }

    /**
     * Add a new company role.
     */
    public function store(StoreCompanyRoleRequest $request): JsonResponse
    {
        $companyRole = CompanyRole::create($request->validated());

        return (new CompanyRoleResource($companyRole))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Rename a company role.
     */
    public function update(UpdateCompanyRoleRequest $request, CompanyRole $companyRole): CompanyRoleResource
    {
        $companyRole->update($request->validated());

        return new CompanyRoleResource($companyRole);
    }

    /**
     * Delete a company role that no user has.
     */
    public function destroy(CompanyRole $companyRole): JsonResponse|Response
    {
        Gate::authorize('delete', $companyRole);

        if ($companyRole->users()->exists()) {
            return response()->json([
                'message' => 'The company role is assigned to users and cannot be deleted.',
            ], Response::HTTP_CONFLICT);
        }

        $companyRole->delete();

        return response()->noContent();
    }
}
