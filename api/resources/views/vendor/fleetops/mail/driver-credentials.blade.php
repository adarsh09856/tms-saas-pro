<x-mail-layout>
<h2 style="font-size: 18px; font-weight: 600;">Your driver account is ready</h2>

Hi {{ $user->name }},
<br /><br />
{{ $companyName }} has set up your driver account. For security, your password is not included in email.
<br /><br />
Sign in to the Transport Diary Driver app with <strong>{{ $identity }}</strong>. If you need to set or reset your password, choose <strong>Forgot password</strong> in the app and enter the one-time code sent to your registered email address or phone number.
<br /><br />
If you cannot receive a code, contact your transport administrator to confirm the phone number on your driver account.
</x-mail-layout>
