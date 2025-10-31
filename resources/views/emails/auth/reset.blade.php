<x-mail::message>
# Reset your password

Hello,

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="config('app.frontend_url', url('/')) . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email)">
Reset Password
</x-mail::button>

This password reset link will expire in :count minutes.

If you did not request a password reset, no further action is required.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
