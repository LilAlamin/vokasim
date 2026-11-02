<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = $request->user();
        $userId = $authenticated?->getAuthIdentifier() ?? $request->session()->get('login.id');

        if (! $userId) {
            return $next($request);
        }

        $account = User::query()->whereKey($userId)->first();
        $versionKey = $authenticated ? 'auth_version' : 'login.auth_version';
        $sessionVersion = $request->session()->get($versionKey, 1);

        if (! $account || ! $account->is_active || $sessionVersion !== $account->auth_version) {
            $message = $account && ! $account->is_active ? 'Akun Anda dinonaktifkan. Hubungi administrator.' : 'Sesi Anda tidak berlaku lagi. Silakan masuk kembali.';
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        if ($authenticated) {
            $request->session()->put('auth_version', $account->auth_version);
        }

        return $next($request);
    }
}
