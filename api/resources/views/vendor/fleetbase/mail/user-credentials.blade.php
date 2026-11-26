<x-mail-layout>
<h2 style="font-size: 18px; font-weight: 600;">Your account password was updated</h2>

Hello {{ $user?->name }},
<br /><br />
An administrator updated the password for your {{ config('app.name') }} account. For security, passwords are never included in email.
<br /><br />
If you need to set a new password, open the sign-in page and choose <strong>Forgot password</strong>. A one-time reset link will be sent to your registered email address.
<br /><br />
If you did not expect this change, contact your organization administrator.
</x-mail-layout>
