<x-mail::message>
# Reset Your Password

Here's your verification code:

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

This code expires in 10 minutes. If you didn't request this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
