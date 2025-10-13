<x-mail::message>
# Account Approved!

Hello {{ $name }},

Congratulations! Your account on EduForm has been approved by the administrator.

You can now log in to your account and access all the features available to students.

<x-mail::button :url="config('app.frontend_url', url('/login'))">
Login to Your Account
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
