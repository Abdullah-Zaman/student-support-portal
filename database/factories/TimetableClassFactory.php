<?php

namespace Database\Factories;

use App\Enums\TimetableClassType;
use App\Models\TimetableClass;
use App\Models\TimetableClassSection;
use App\Models\TimetableVersion;
use App\Support\Timetable\SectionExpander;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableClass>
 */
class TimetableClassFactory extends Factory
{
    protected $model = TimetableClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'timetable_version_id' => TimetableVersion::factory(),
            'day' => fake()->numberBetween(1, 5),
            'starts_at' => '08:00',
            'ends_at' => '09:20',
            'room' => 'Lecture Room # '.fake()->numberBetween(10, 30),
            'floor' => '1st Floor',
            'course' => fake()->randomElement(['Software Engineering', 'Discrete Structures', 'Operating Systems (Theory)']),
            'type' => TimetableClassType::Theory,
            'teacher' => 'Mr. '.fake()->lastName(),
            'raw_sections' => 'BSSE-3A',
            'sheet' => 'Time Table',
            'cell' => 'D8',
        ];
    }

    /**
     * Attach the given expanded sections (e.g. "BSSE-3A") to the class.
     *
     * @param  list<string>  $sections
     */
    public function forSections(array $sections): static
    {
        return $this->state(['raw_sections' => implode(' / ', $sections)])
            ->afterCreating(function (TimetableClass $class) use ($sections) {
                foreach ($sections as $section) {
                    TimetableClassSection::create(['timetable_class_id' => $class->id, 'section' => $section, ...SectionExpander::describe($section)]);
                }
            });
    }
}
