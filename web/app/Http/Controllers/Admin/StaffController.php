<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/staff/index');
    }

    public function create(): void
    {
        //
    }

    public function store(Request $request): void
    {
        //
    }

    public function edit(Staff $staff): void
    {
        //
    }

    public function update(Request $request, Staff $staff): void
    {
        //
    }

    public function destroy(Staff $staff): void
    {
        //
    }

    public function reissuePassword(Staff $staff): void
    {
        //
    }
}
