<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoAuthController extends Controller
{
    /**
     * Redirect user to SSO Server for OAuth authorization.
     */
    public function redirect(Request $request)
    {
        if (! config('services.sso.enabled')) {
            return redirect()->route('login')->withErrors([
                'sso' => 'SSO tidak diaktifkan pada aplikasi ini.',
            ]);
        }

        // Logout any existing session so re-authentication works
        // when SSO user changes (e.g. impersonation on SSO server)
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        $query = http_build_query([
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => config('services.sso.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'read-user',
            'state' => $state,
        ]);

        $ssoUrl = config('services.sso.base_url').'/oauth/authorize?'.$query;

        Log::info('SSO redirect initiated', ['url' => $ssoUrl, 'state' => $state]);

        return redirect()->away($ssoUrl);
    }

    /**
     * Handle callback from SSO Server after authorization.
     */
    public function callback(Request $request)
    {
        if (! config('services.sso.enabled')) {
            return redirect()->route('login')->withErrors([
                'sso' => 'SSO tidak diaktifkan pada aplikasi ini.',
            ]);
        }

        Log::info('SSO callback received', [
            'has_code' => $request->has('code'),
            'has_state' => $request->has('state'),
            'has_error' => $request->has('error'),
        ]);

        // Check for error from SSO server first
        if ($request->has('error')) {
            Log::warning('SSO callback error from server', [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);

            return redirect()->route('login')->withErrors([
                'sso' => $request->input('error_description', 'Autentikasi SSO gagal.'),
            ]);
        }

        // Validate state to prevent CSRF
        $storedState = $request->session()->pull('sso_state');
        $incomingState = $request->input('state');

        Log::info('SSO state check', [
            'stored' => $storedState,
            'incoming' => $incomingState,
            'match' => $storedState === $incomingState,
        ]);

        if (! $storedState || $storedState !== $incomingState) {
            Log::warning('SSO callback: State mismatch', [
                'stored' => $storedState,
                'incoming' => $incomingState,
            ]);

            return redirect()->route('login')->withErrors([
                'sso' => 'Sesi SSO tidak valid. Silakan coba lagi.',
            ]);
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('login')->withErrors([
                'sso' => 'Kode otorisasi tidak ditemukan.',
            ]);
        }

        try {
            // Exchange authorization code for access token
            Log::info('SSO exchanging code for token');

            $http = Http::asForm();
            if (! config('services.sso.verify_ssl', true)) {
                $http = $http->withoutVerifying();
            }

            $tokenResponse = $http->post(config('services.sso.base_url').'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
                'redirect_uri' => config('services.sso.redirect_uri'),
                'code' => $code,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('SSO token exchange failed', [
                    'status' => $tokenResponse->status(),
                ]);

                return redirect()->route('login')->withErrors([
                    'sso' => 'Gagal mendapatkan token dari SSO Server.',
                ]);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (! $accessToken) {
                Log::error('SSO no access_token in response');

                return redirect()->route('login')->withErrors([
                    'sso' => 'Token akses tidak valid.',
                ]);
            }

            Log::info('SSO token obtained, fetching user info');

            // Fetch user info from SSO Server
            $userHttp = Http::withToken($accessToken);
            if (! config('services.sso.verify_ssl', true)) {
                $userHttp = $userHttp->withoutVerifying();
            }

            $userResponse = $userHttp->get(config('services.sso.base_url').'/api/user');

            if ($userResponse->failed()) {
                Log::error('SSO user fetch failed', [
                    'status' => $userResponse->status(),
                ]);

                return redirect()->route('login')->withErrors([
                    'sso' => 'Gagal mengambil data user dari SSO Server.',
                ]);
            }

            $ssoUser = $userResponse->json();

            Log::info('SSO user data received', [
                'email' => $ssoUser['email'] ?? 'N/A',
                'name' => $ssoUser['name'] ?? 'N/A',
            ]);

            // Find or create user in local database
            $user = $this->findOrCreateUser($ssoUser);

            if (! $user->is_active) {
                return redirect()->route('login')->withErrors([
                    'sso' => 'Akun Anda tidak aktif. Hubungi administrator.',
                ]);
            }

            // Store SSO tokens in session for later use (e.g., logout)
            session([
                'sso_access_token' => $accessToken,
                'sso_refresh_token' => $tokenData['refresh_token'] ?? null,
            ]);

            // Login user locally
            Auth::login($user, true);
            $request->session()->regenerate();

            Log::info('SSO login successful', ['user_id' => $user->id, 'email' => $user->email]);

            $request->session()->flash('sso_success', "Selamat datang, {$user->name}! Anda berhasil login melalui SSO.");

            return redirect()->intended(route('dashboard'));

        } catch (\Exception $e) {
            Log::error('SSO authentication error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors([
                'sso' => 'Terjadi kesalahan saat autentikasi SSO. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Find existing user or create a new one from SSO data.
     * SSO users always get email_verified_at set and default 'user' role.
     */
    protected function findOrCreateUser(array $ssoUser): User
    {
        if (empty($ssoUser['email']) || empty($ssoUser['name'])) {
            abort(403, 'Data user dari SSO Server tidak valid.');
        }

        $user = User::where('email', $ssoUser['email'])->first();

        if ($user) {
            // Update existing user with latest SSO data
            $updateData = ['name' => $ssoUser['name']];

            // Ensure email is verified for SSO users
            if (! $user->email_verified_at) {
                $updateData['email_verified_at'] = now();
            }

            $user->update($updateData);

            // Ensure user has at least 'user' role
            if (method_exists($user, 'hasRole') && ! $user->hasAnyRole($user->getRoleNames())) {
                $user->assignRole('user');
            }

            Log::info('SSO existing user updated', ['user_id' => $user->id]);
        } else {
            // Create new user from SSO data
            $user = User::create([
                'name' => $ssoUser['name'],
                'email' => $ssoUser['email'],
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            // Assign default 'user' role
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }

            Log::info('SSO new user created', ['user_id' => $user->id, 'email' => $user->email]);
        }

        return $user;
    }
}
