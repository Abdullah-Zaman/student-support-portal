<?php

namespace App\Livewire\Student;

use App\Support\Timetable\StudentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My Timetable')]
class Timetable extends Component
{
    #[Computed]
    public function schedule(): StudentSchedule
    {
        $user = Auth::user();

        return StudentSchedule::for($user->student, $user->department_id);
    }

    public function render(): View
    {
        return view('livewire.student.timetable');
    }
}
