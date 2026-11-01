<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ScopesToProgram;
use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// copies a term's class schedule into another, only filling gaps. enrollments are not copied
class ScheduleCopyController extends Controller
{
    use ScopesToProgram;

    public function store(Request $request, Department $department, Program $program): RedirectResponse
    {
        $this->ensureProgramBelongsToDepartment($department, $program);

        $data = $request->validate([
            'from' => ['required', 'integer', 'exists:review_periods,id', 'different:to'],
            'to' => ['required', 'integer', 'exists:review_periods,id'],
        ]);

        $existing = ClassSubject::where('review_period_id', $data['to'])
            ->whereHas('classSection', fn ($q) => $q->where('program_id', $program->id))
            ->get(['class_section_id', 'subject_id'])
            ->map(fn ($cs) => "{$cs->class_section_id}:{$cs->subject_id}")
            ->flip();

        $copied = 0;
        ClassSubject::where('review_period_id', $data['from'])
            ->whereHas('classSection', fn ($q) => $q->where('program_id', $program->id))
            ->get()
            ->each(function (ClassSubject $offering) use ($data, $existing, &$copied) {
                if ($existing->has("{$offering->class_section_id}:{$offering->subject_id}")) {
                    return;
                }
                ClassSubject::create([
                    'class_section_id' => $offering->class_section_id,
                    'subject_id' => $offering->subject_id,
                    'faculty_id' => $offering->faculty_id,
                    'review_period_id' => $data['to'],
                ]);
                $copied++;
            });

        Cache::flush();

        return back()->with('status', $copied
            ? "Copied {$copied} class ".($copied === 1 ? 'offering' : 'offerings').'.'
            : 'Nothing to copy: the term already has everything the other term has.');
    }
}
