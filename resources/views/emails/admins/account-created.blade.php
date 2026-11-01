<x-mail::message>
# Hi, {{ $name }}!

@if ($reset)
Your password for the Golden Gate Colleges evaluation system was reset by the system administrator. Use the new login details below:
@else
An administrator account was created for you at Golden Gate Colleges{{ $scope ? ", managing {$scope}" : '' }}. Here's how to log in:
@endif

<x-mail::panel>
**User ID:** {{ $idNumber }}<br>
**Password:** {{ $password }}
</x-mail::panel>

<x-mail::button :url="route('login')">
Log In
</x-mail::button>

Please change your password after logging in.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
