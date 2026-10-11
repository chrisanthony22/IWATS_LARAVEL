<?php

namespace App\Http\Controllers;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use App\Services\Audit;
use App\Services\DataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserAccountController extends Controller
{
    public function __construct(private DataService $data) {}

    public function index(Request $request): View
    {
        $q    = trim((string) $request->query('q', ''));
        $type = (int) $request->query('type', 0);

        return view('users.index', [
            'users'     => $this->data->getUserAccounts($q, $type)->withQueryString(),
            'userTypes' => $this->data->getUserTypes(),
            'q'         => $q,
            'type'      => $type,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        set_time_limit(120);   // sending the email can be slow; the DB round trips are slow too

        $d = $request->validate($this->rules(), $this->messages());

        try {
            $user = $this->data->createUserAccount($d, fn (User $u, string $plain) =>
                Mail::to($u->email)->send(new UserCredentialsMail($u, $plain, userType: $this->typeName($u->usertype_id))));
        } catch (\Throwable $e) {
            report($e);

            // nothing was saved (rolled back), so there is no record id: log against the table name only
            Audit::log('email_failed',
                "Could not create user account for {$d['email']}: the credentials email failed to send, so nothing was saved.",
                'users', null, $this->snapshot($d));

            return back()->withInput()->with('error', 'The email could not be sent, so the account was not created. Check the mail settings and try again.');
        }

        Audit::log('created',
            "Created user account {$user->name} <{$user->email}>. Temporary password emailed.",
            $user, null, $this->snapshot($user->only(['name', 'email', 'usertype_id'])));

        return redirect()->route('users.index')
            ->with('success', "Account created. A temporary password was emailed to {$user->email}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $d = $request->validate($this->rules($user->id), $this->messages());

        $before = $this->snapshot($user->only(['name', 'email', 'usertype_id']));

        $this->data->updateUserAccount($user, $d);

        $after = $this->snapshot($user->only(['name', 'email', 'usertype_id']));

        // keep only the fields that really changed, so the "What changed" popup stays clean
        $old = $new = [];
        foreach ($after as $field => $value) {
            if ($before[$field] !== $value) {
                $old[$field] = $before[$field];
                $new[$field] = $value;
            }
        }

        if ($new) {
            Audit::log('updated',
                "Updated user account {$user->name} <{$user->email}> (changed: " . implode(', ', array_keys($new)) . ').',
                $user, $old, $new);
        }

        return back()->with('success', "{$user->name} was updated.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        set_time_limit(120);

        try {
            $this->data->resetUserPassword($user, fn (User $u, string $plain) =>
                Mail::to($u->email)->send(new UserCredentialsMail($u, $plain, isReset: true, userType: $this->typeName($u->usertype_id))));
        } catch (\Throwable $e) {
            report($e);

            Audit::log('email_failed',
                "Could not reset the password of {$user->name} <{$user->email}>: the email failed to send, so the password was not changed.",
                $user);

            return back()->with('error', 'The email could not be sent, so the password was not changed. Try again.');
        }

        // Audit stores the key "password" as [hidden]: it records that it changed, never the value
        Audit::log('password_reset',
            "Admin reset the password of {$user->name} <{$user->email}>. New temporary password emailed.",
            $user, null, ['password' => 'changed']);

        return back()->with('success', "A new temporary password was emailed to {$user->email}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $old  = $this->snapshot($user->only(['name', 'email', 'usertype_id']));   // read before the row is gone
        $name = "{$user->name} <{$user->email}>";

        $this->data->deleteUserAccount($user);

        Audit::log('deleted', "Deleted user account {$name}.", $user, $old);

        return back()->with('success', "{$user->name} was deleted.");
    }

    /**
     * Log-friendly copy of the editable fields: the user type shows its name instead of the id.
     * Only name, email and user_type are ever included (never the password).
     */
    private function snapshot(array $d): array
    {
        return [
            'name'      => $d['name'] ?? null,
            'email'     => $d['email'] ?? null,
            'user_type' => $this->typeName($d['usertype_id'] ?? null),
        ];
    }

    private function typeName(int|string|null $id): string
    {
        if (! $id) {
            return 'Not assigned';
        }

        return $this->data->getUserTypes()->firstWhere('ID', (int) $id)?->UserType ?? "#{$id}";
    }

    /** The same email may have several accounts, but only one per user type. */
    private function rules(?int $ignoreId = null): array
    {
        $typeId = request()->integer('usertype_id');

        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255',
                              Rule::unique('users', 'email')
                                  ->where(fn ($q) => $q->where('usertype_id', $typeId))
                                  ->ignore($ignoreId)],
            'usertype_id' => ['required', 'integer', Rule::exists('IWMS_UserTypes', 'ID')],
        ];
    }

    private function messages(): array
    {
        return ['email.unique' => 'This email already has an account with that user type. Pick a different user type or email.'];
    }
}