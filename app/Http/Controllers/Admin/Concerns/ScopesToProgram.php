<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Department;
use App\Models\Program;

trait ScopesToProgram
{
    // make sure the ids in the URL actually belong together
    protected function ensureProgramBelongsToDepartment(Department $department, Program $program): void
    {
        abort_unless($program->department_id === $department->id, 404);
    }
}
