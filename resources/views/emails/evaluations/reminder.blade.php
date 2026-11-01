<x-mail::message>
# Hi, {{ $name }}!

This is a friendly reminder that you still have a pending evaluation for **{{ $evaluateeLabel }}**.

@if (count($pending))
Still to evaluate:

@foreach ($pending as $item)
- {{ $item }}
@endforeach

@endif
Please log in and complete it before the review period closes.

<x-mail::button :url="route('evaluation.index')">
Complete Evaluation
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
