<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::inRandomOrder()->firstOrFail()->id,
            'lesson_id' => Lesson::inRandomOrder()->firstOrFail()->id,
            'makeup_lesson_id' => null,
            'staff_id' => Staff::inRandomOrder()->first()?->id,
            'status' => fake()->randomElement(['出席', '欠席']),
            'is_late' => false,
            'makeup_type' => null,
            'note' => null,
        ];
    }
}
