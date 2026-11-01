<x-mail::message>
# Hi, {{ $name }}!

An evaluation account was created for you at Golden Gate Colleges. Here's how to log in:

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
