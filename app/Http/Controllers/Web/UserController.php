<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->withCount('tokens')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['role' => Role::Operator, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::query()->create([...$validated, 'is_active' => true]);

        return redirect()->route('users.index')->with('status', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $isActive = $request->boolean('is_active');
        $isSelf = $request->user()->is($user);

        if ($isSelf && (! $isActive || $validated['role'] !== Role::Owner->value)) {
            return back()->withInput()->withErrors(['role' => 'You cannot deactivate or demote your own account.']);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update([...$validated, 'is_active' => $isActive]);

        if (! $isActive || isset($validated['password'])) {
            $user->tokens()->delete();
        }

        return redirect()->route('users.index')->with('status', 'Account updated.');
    }
}
