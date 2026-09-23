<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(): void
    {
        //
    }

    public function update(Request $request, Lesson $lesson, Student $student): void
    {
        //
    }
}
