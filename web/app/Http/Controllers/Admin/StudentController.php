<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/students/index');
    }

    public function create(): void
    {
        //
    }

    public function store(Request $request): void
    {
        //
    }

    public function edit(Student $student): void
    {
        //
    }

    public function update(Request $request, Student $student): void
    {
        //
    }

    public function destroy(Student $student): void
    {
        //
    }
}
