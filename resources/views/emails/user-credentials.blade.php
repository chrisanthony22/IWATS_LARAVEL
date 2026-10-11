<x-mail::message>
# Hello {{ $name }},

@if ($isReset)
An administrator reset your {{ config('app.name') }} password. Use the temporary password below to sign in.
@else
An administrator created a {{ config('app.name') }} account for you. Use the details below to sign in.
@endif

<x-mail::panel>
**Email:** {{ $email }}

@if ($userType)
**User type:** {{ $userType }}

@endif
**Temporary password:** {{ $password }}
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

After signing in, open **Profile** and change this password. If you did not expect this email, contact your administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>