<?php

namespace App\Listeners;

use App\Services\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;

/** Logs every sign-in related event automatically. Registered once in AppServiceProvider. */
class AuditAuthEvents
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class         => 'onLogin',
            Logout::class        => 'onLogout',
            Failed::class        => 'onFailed',
            Lockout::class       => 'onLockout',
            PasswordReset::class => 'onPasswordReset',
            Registered::class    => 'onRegistered',
        ];
    }

    /** Login also fires when the "remember me" cookie restores a session. Only count the real sign-in form. */
    public function onLogin(Login $e): void
    {
        if (app()->runningInConsole() || ! (request()->isMethod('post') && request()->is('login'))) {
            return;
        }
        Audit::log('login', 'Logged in', null, null, null, $e->user);
    }

    public function onLogout(Logout $e): void
    {
        Audit::log('logout', 'Logged out', null, null, null, $e->user);   // user is passed in because it is already signed out here
    }

    public function onFailed(Failed $e): void
    {
        // the password is never read or stored; only the e-mail that was tried
        Audit::log('login_failed', 'Failed login attempt for "' . ($e->credentials['email'] ?? '?') . '"', null, null, null, $e->user);
    }

    public function onLockout(Lockout $e): void
    {
        Audit::log('lockout', 'Too many login attempts for "' . $e->request->input('email', '?') . '"');
    }

    public function onPasswordReset(PasswordReset $e): void
    {
        Audit::log('password_reset', 'Password was reset', null, null, null, $e->user);
    }

    public function onRegistered(Registered $e): void
    {
        Audit::log('registered', 'New account registered', null, null, null, $e->user);
    }
}