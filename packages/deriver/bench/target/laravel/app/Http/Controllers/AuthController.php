<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\{Registered, Verified};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Hash, Password};
use Illuminate\Validation\Rules\Password as PasswordRule;

final class AuthController
{
    public function register(Request $request): JsonResponse
    {
        $input = $request->validate(['name' => 'required|string|min:2|max:80', 'email' => 'required|email|max:254|unique:users,email', 'password' => ['required','confirmed',PasswordRule::min(12)->mixedCase()->numbers()]]);
        $user = User::create($input);
        event(new Registered($user));
        return response()->json(['token' => $user->createToken('harbor-client', ['*'], now()->addDays(30))->plainTextToken, 'verification_required' => true], 201);
    }
    public function login(Request $request): JsonResponse
    {
        $input = $request->validate(['email' => 'required|email', 'password' => 'required|string|max:200']);
        $user = User::where('email', $input['email'])->first();
        $valid = Hash::check($input['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        abort_unless($user !== null && $valid && $user->isActive(), 401, 'Invalid credentials.');
        return response()->json(['token' => $user->createToken('harbor-client', ['*'], now()->addDays(30))->plainTextToken]);
    }
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['revoked' => true]);
    }
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        if (!$user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }
        return response()->json(['verified' => true]);
    }
    public function resend(Request $request): JsonResponse
    {
        if (!$request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }
        return response()->json(['sent' => true]);
    }
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));
        return response()->json(['message' => 'If this account exists, password reset instructions will be sent.']);
    }
    public function reset(Request $request): JsonResponse
    {
        $input = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required','confirmed',PasswordRule::min(12)->mixedCase()->numbers()]]);
        $status = Password::reset($input, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => \Illuminate\Support\Str::random(60)])->save();
            $user->tokens()->delete();
            event(new \Illuminate\Auth\Events\PasswordReset($user));
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, __($status));
        return response()->json(['reset' => true]);
    }
}
