<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\ReviewPeriod;
use Illuminate\Support\Collection;

// picks the block an irregular student attends for a subject, never guesses
class EnrollmentSectionResolver
{
    // subject id => offerings for a term (active by default)
    public function offeringsFor(iterable $subjectIds, ?int $periodId = null): array
    {
        return ClassSubject::whereIn('subject_id', $subjectIds)
            ->where('review_period_id', $periodId ?? ReviewPeriod::activeId() ?? 0)
            ->with(['classSection:id,year_level,block', 'faculty:id,name'])
            ->get()
            ->sortBy(fn ($cs) => [$cs->classSection->year_level, $cs->classSection->block])
            ->groupBy('subject_id')
            ->map(fn (Collection $group) => $group->map(fn ($cs) => [
                'sectionId' => $cs->class_section_id,
                'year' => $cs->classSection->year_level,
                'block' => $cs->classSection->block,
                'label' => "{$cs->classSection->year_level}-{$cs->classSection->block}",
                'facultyName' => $cs->faculty?->name,
            ])->values())
            ->all();
    }

    // returns [section id, error]
    public function resolve(string $subjectCode, $offerings, ?int $sectionId = null, ?string $block = null): array
    {
        $offerings = collect($offerings);
        $labels = $offerings->pluck('label')->implode(', ');

        if ($sectionId) {
            return $offerings->contains('sectionId', $sectionId)
                ? [$sectionId, null]
                : [null, "{$subjectCode} isn't offered in the selected block."];
        }

        $block = $block !== null ? $this->normalize($block) : '';
        if ($block !== '') {
            $matches = $offerings->filter(fn ($o) => $block === $this->normalize($o['block'])
                || $block === $this->normalize($o['year'].$o['block']))->values();

            return match ($matches->count()) {
                1 => [$matches[0]['sectionId'], null],
                0 => [null, $offerings->isEmpty()
                    ? "{$subjectCode} has no class scheduled, so block {$block} can't be set."
                    : "{$subjectCode} isn't offered in block {$block} (offered in: {$labels})."],
                default => [null, "{$subjectCode} block {$block} is ambiguous across year levels (offered in: {$labels}); use year+block, e.g. 1A."],
            };
        }

        return match (true) {
            $offerings->isEmpty() => [null, null],
            $offerings->count() === 1 => [$offerings->first()['sectionId'], null],
            default => [null, "{$subjectCode} is offered in several blocks ({$labels}); choose the student's block."],
        };
    }

    private function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
    }
}
