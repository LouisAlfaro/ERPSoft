<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Src\Organizations\Application\UseCases\CreateCompany;
use Src\Organizations\Application\UseCases\CreateLocal;
use Src\Organizations\Application\UseCases\ListLocalsByCompany;
use Src\Organizations\Application\UseCases\ListCompanies;

class OrganizationController extends Controller
{
    public function createCompany(Request $request, CreateCompany $useCase)
    {
        $data = $request->validate(['name' => ['required','string','min:2','max:100']]);
        $id = $useCase($data['name']);
        return response()->json(['id' => $id], 201);
    }

    public function createLocal(int $companyId, Request $request, CreateLocal $useCase)
    {
        $data = $request->validate(['name' => ['required','string','min:2','max:100']]);
        $id = $useCase($companyId, $data['name']);
        return response()->json(['id' => $id], 201);
    }

    public function listLocals(int $companyId, ListLocalsByCompany $useCase)
    {
        $locals = $useCase($companyId);
        return response()->json(array_map(fn($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'company_id' => $l->companyId,
        ], $locals));
    }

    public function listCompanies(ListCompanies $useCase)
    {
        $companies = $useCase();
        return response()->json(array_map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->name,
        ], $companies));
    }
    
}
