<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/guardians/index');
    }

    public function create(): void
    {
        //
    }

    public function store(Request $request): void
    {
        //
    }

    public function edit(Guardian $guardian): void
    {
        //
    }

    public function update(Request $request, Guardian $guardian): void
    {
        //
    }

    public function destroy(Guardian $guardian): void
    {
        //
    }

    public function reissuePassword(Guardian $guardian): void
    {
        //
    }
}
