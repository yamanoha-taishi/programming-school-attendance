<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/attendances/index');
    }

    public function update(Request $request, Lesson $lesson, Student $student): void
    {
        //
    }

    public function updateMakeup(Request $request, Attendance $attendance): void
    {
        //
    }
}
