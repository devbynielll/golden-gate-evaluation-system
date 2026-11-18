<?php

function ggcClientSeed(): void
{
    [$root, $runtime] = ggcClientPaths(); $metadata = ggcClientMetadata();
    if (config('database.default') !== 'sqlite' || ggcClientCanonical(\Illuminate\Support\Facades\DB::connection()->getDatabaseName()) !== $runtime.'/database.sqlite') throw new RuntimeException('Synthetic fixtures require the owned disposable database.');
    if (\App\Models\User::exists()) throw new RuntimeException('Synthetic setup refuses an existing roster. Use the explicitly confirmed local reset instead.');
    \Illuminate\Support\Facades\DB::transaction(function () use ($metadata) {
        $department = \App\Models\Department::create(['code'=>'DEMO','name'=>'Demonstration Department']);
        $program = $department->programs()->create(['code'=>'DEMO','name'=>'Demonstration Program']);
        $section = $program->classSections()->create(['year_level'=>1,'block'=>'A']);
        $office = \App\Models\Office::create(['code'=>'REG','name'=>'Demonstration Registrar']);
        $library = \App\Models\Office::create(['code'=>'LIB','name'=>'Demonstration Library']);
        $year = (int) date('Y');
        $current = \App\Models\ReviewPeriod::create(['academic_year'=>$year.'-'.($year+1),'semester'=>'1st Semester',
            'start_date'=>now()->subMonth()->toDateString(),'end_date'=>now()->addMonth()->toDateString(),'is_active'=>true,'is_submission_open'=>true]);
        $historical = \App\Models\ReviewPeriod::create(['academic_year'=>($year-1).'-'.$year,'semester'=>'2nd Semester',
            'start_date'=>now()->subYear()->subMonth()->toDateString(),'end_date'=>now()->subYear()->toDateString(),'is_active'=>false,'is_submission_open'=>false]);
        $faculty = $program->faculty()->create(['faculty_id'=>'DEMO-F01','name'=>'Demonstration Faculty']);
        $subject = $program->subjects()->create(['code'=>'DEMO101','description'=>'Demonstration Subject','year_level'=>1]);
        foreach ([$current,$historical] as $period) $offerings[$period->id] = \App\Models\ClassSubject::create([
            'review_period_id'=>$period->id,'class_section_id'=>$section->id,'subject_id'=>$subject->id,'faculty_id'=>$faculty->id]);
        foreach (['faculty','office'] as $kind) {
            $category = \App\Models\CriteriaCategory::create(['evaluatee_type'=>$kind,'section_number'=>1,'name'=>'Demonstration '.$kind.' service']);
            foreach ([1,2] as $order) $questions[$kind][] = $category->questions()->create(['text'=>$order===1?'Provides clear and helpful guidance.':'Treats students respectfully.','order'=>$order]);
        }
        $shared = \App\Models\StaffCriteriaCategory::create(['section_number'=>1,'name'=>'Professional service']);
        $private = \App\Models\StaffCriteriaCategory::create(['office_id'=>$office->id,'section_number'=>2,'name'=>'Records assistance']);
        $staffQuestions = [$shared->questions()->create(['text'=>'Provides courteous assistance.','order'=>1]),
            $shared->questions()->create(['text'=>'Explains the required steps clearly.','order'=>2]),
            $private->questions()->create(['text'=>'Assists with records requests accurately.','order'=>1])];
        $member = $office->staff()->create(['name'=>'Demonstration Registrar Staff','position'=>'Records Assistant']);
        $unused = $office->staff()->create(['name'=>'Demonstration New Staff','position'=>'Service Assistant']);
        $library->staff()->create(['name'=>'Demonstration Library Staff','position'=>'Library Assistant']);
        $roles = ['ADMIN'=>'super_admin','DEPARTMENT'=>'department_admin','OFFICE'=>'office_admin','LIBRARY'=>'office_admin'];
        foreach ($roles as $label=>$role) \App\Models\User::create(['name'=>'Demonstration '.$label,
            'id_number'=>'DEMO-'.$metadata['installation'].'-'.$label, 'email'=>strtolower($label).'-'.$metadata['installation'].'@example.test',
            'password'=>\Illuminate\Support\Facades\Hash::make($metadata['password']), 'role'=>$role,
            'office_id'=>$label==='OFFICE'?$office->id:($label==='LIBRARY'?$library->id:null)])->forceFill(['email_verified_at'=>now()])->save();
        foreach (range(1,6) as $n) {
            $user = new \App\Models\User(['name'=>'Demonstration Student '.$n, 'id_number'=>'DEMO-'.$metadata['installation'].'-STUDENT'.$n,
                'email'=>'student'.$n.'-'.$metadata['installation'].'@example.test', 'password'=>\Illuminate\Support\Facades\Hash::make($metadata['password']), 'role'=>'student']);
            $user->email_verified_at = now(); $user->save();
            $student = $program->students()->create(['user_id'=>$user->id,'student_number'=>$user->id_number,'email'=>$user->email,
                'first_name'=>'Demonstration','last_name'=>'Student '.$n,'class_section_id'=>$section->id]);
            if ($n===6) continue; // Client's clean test account; all submissions remain optional/eligible as normal.
            foreach ([$current,$historical] as $period) {
                foreach (['faculty','office'] as $kind) {
                    $evaluation = \App\Models\Evaluation::create(['student_id'=>$student->id,'review_period_id'=>$period->id,'evaluatee_type'=>$kind,
                        'class_subject_id'=>$kind==='faculty'?$offerings[$period->id]->id:null,'office_id'=>$kind==='office'?$office->id:null,'submitted_at'=>now()]);
                    foreach ($questions[$kind] as $question) $evaluation->answers()->create(['criteria_question_id'=>$question->id,'rating'=>4]);
                }
                if ($period->id === $current->id && $n===5) continue;
                $evaluation = \App\Models\StaffEvaluation::create(['student_id'=>$student->id,'review_period_id'=>$period->id,'office_staff_id'=>$member->id,
                    'office_id'=>$office->id,'staff_name'=>$member->name,'staff_position'=>$member->position,'office_code'=>$office->code,'office_name'=>$office->name,
                    'academic_year'=>$period->academic_year,'semester'=>$period->semester,'submitted_at'=>now(),'comments'=>'Synthetic feedback: deliberately excluded from staff reports.']);
                foreach ($staffQuestions as $i=>$question) {
                    $question->freezeForHistory(); $category=$question->category;
                    $evaluation->answers()->create(['staff_criteria_question_id'=>$question->id,'staff_criteria_category_id'=>$category->id,
                        'category_name'=>$category->name,'section_number'=>$category->section_number,'is_office_specific'=>$category->office_id!==null,
                        'question_text'=>$question->text,'question_order'=>$question->order,'rating'=>[4,3,2][$i]]);
                }
            }
        }
        // Use the actual after-commit queue path; the start command runs the normal worker.
        \App\Jobs\GenerateEvaluationNotifications::dispatch($historical->id);
    });
}
