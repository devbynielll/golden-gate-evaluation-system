<?php
namespace Tests\Feature;

use App\Models\{Department,User,ReviewPeriod,Office,CriteriaCategory,Evaluation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EvaluationRatingBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_ratings_one_through_four_persist_and_five_is_rejected(): void { $this->verifyBoundary('faculty'); }
    public function test_office_ratings_one_through_four_persist_and_five_is_rejected(): void { $this->verifyBoundary('office'); }

    private function verifyBoundary(string $type): void
    {
        $d=Department::create(['code'=>'D','name'=>'Synthetic']);$p=$d->programs()->create(['code'=>'P','name'=>'Synthetic']);
        $section=$p->classSections()->create(['year_level'=>1,'block'=>'A']);
        $subject=$p->subjects()->create(['code'=>'S','description'=>'Synthetic','year_level'=>1]);
        $faculty=$p->faculty()->create(['faculty_id'=>'F','name'=>'Synthetic']);
        $period=ReviewPeriod::create(['academic_year'=>'2026-2027','semester'=>'1st Semester','start_date'=>now()->subDay(),'end_date'=>now()->addMonth(),'is_active'=>true,'is_submission_open'=>true]);
        $offering=$section->classSubjects()->create(['subject_id'=>$subject->id,'faculty_id'=>$faculty->id,'review_period_id'=>$period->id]);
        $office=Office::create(['code'=>'O','name'=>'Synthetic']);
        $question=CriteriaCategory::create(['evaluatee_type'=>$type,'section_number'=>1,'name'=>'Synthetic'])->questions()->create(['text'=>'Synthetic?','order'=>1]);
        $url=route('evaluation.'.$type.'.store',$type==='faculty'?$offering:$office);
        foreach ([0,1,2,3,4,5,6] as $rating) {
            $user=User::factory()->create(['role'=>'student']);
            $student=$p->students()->create(['user_id'=>$user->id,'student_number'=>$user->id_number,'email'=>$user->email,'first_name'=>'Synthetic','last_name'=>'Student','class_section_id'=>$section->id]);
            $before=Evaluation::count();$response=$this->actingAs($user)->postJson($url,['ratings'=>[$question->id=>$rating]]);
            if($rating<1||$rating>4){$response->assertUnprocessable()->assertJsonValidationErrors('ratings.'.$question->id);$this->assertSame($before,Evaluation::count());continue;}
            $response->assertRedirect();$evaluation=Evaluation::where('student_id',$student->id)->sole();
            $this->assertSame($rating,(int)DB::table('evaluation_answers')->where('evaluation_id',$evaluation->id)->value('rating'));
            $snapshot=$evaluation->answers()->get()->toJson();
            $this->postJson($url,['ratings'=>[$question->id=>1]])->assertRedirect();
            $this->assertSame($snapshot,$evaluation->answers()->get()->toJson());
            $this->assertSame(1,Evaluation::where('student_id',$student->id)->count());
        }
        $this->assertSame(4,Evaluation::count());
        $report=app(\App\Services\ReportService::class);
        $rows=$type==='faculty'?$report->facultyReport(null,$period->id,1):$report->officeReport(null,$period->id,1);
        $this->assertSame(2.5,(float)$rows['data'][0]['rating']);$this->assertSame(4,$rows['data'][0]['responses']);
        // Historical out-of-range values are not clamped, rewritten or deleted by validation.
        $old=Evaluation::first();$old->answers()->update(['rating'=>5]);
        $this->assertSame(5,(int)$old->answers()->value('rating'));
        $user=User::factory()->create(['role'=>'office_admin']);$this->actingAs($user)->postJson($url,['ratings'=>[$question->id=>4]])->assertForbidden();
        $this->assertSame(5,(int)$old->answers()->value('rating'));
    }
}
