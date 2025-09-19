<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\SecurityAlert;
use App\Models\FailedLoginAttempt;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            Log::warning('Tentativo di accesso con email inesistente', [
                'email' => $credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            $this->recordFailedAttempt($credentials['email'], null, 'non_existent_user', $request);

            return response([
                'message' => 'Utente inesistente',
            ], 401);
        }

        if ($user['email_verified_at'] == null) {
            Log::warning('Tentativo di accesso con utente non verificato', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            $this->recordFailedAttempt($credentials['email'], $user->id, 'unverified_user', $request);

            return response([
                'message' => 'Utenza non attivata. seguire le indicazioni nella mail di attivazione.',
            ], 401);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            Log::warning('Tentativo di accesso con password errata', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            $this->recordFailedAttempt($credentials['email'], $user->id, 'invalid_credentials', $request);

            return response([
                'message' => 'Le credenziali non corrispondono',
            ], 401);
        }

        if ($user['is_deleted'] == 1) {
            Log::warning('Tentativo di accesso con utente disabilitato', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            $this->recordFailedAttempt($credentials['email'], $user->id, 'disabled_user', $request);

            return response([
                'message' => 'Utente disabilitato',
            ], 401);
        }

        $user->createOtp();

        $request->authenticate();

        $request->session()->regenerate();

        return response()->noContent();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function storeMicrosoft(Request $request): Response
    {

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->token),
                'microsoft_token' => $request->token,
                'microsoft_access_token' => $request->access_token,
                'is_admin' => true,
            ]);

            event(new Registered($user));
        } else {
            // Aggiorna il token di accesso se l'utente esiste già
            $user->update([
                'microsoft_access_token' => $request->access_token,
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        return response()->noContent();
    }

    public function validateOtp(Request $request)
    {

        $user = Auth::user();

        $otp = Otp::where([
            'email' => $user->email,
            'otp' => $request->otp,
        ])->latest()->first();

        if ($otp) {

            if ($otp->isExpired()) {
                Log::warning('Tentativo di accesso con OTP scaduto', [
                    'email' => $user->email,
                    'user_id' => $user->id,
                    'otp_provided' => $request->otp,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now(),
                ]);

                $this->recordFailedAttempt($user->email, $user->id, 'expired_otp', $request, [
                    'otp_provided' => $request->otp,
                ]);

                return response([
                    'message' => 'OTP scaduto',
                ], 401);
            }

            return response()->json([
                'success' => true,
            ], 200);
        } else {
            Log::warning('Tentativo di accesso con OTP non valido', [
                'email' => $user->email,
                'user_id' => $user->id,
                'otp_provided' => $request->otp,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            $this->recordFailedAttempt($user->email, $user->id, 'invalid_otp', $request, [
                'otp_provided' => $request->otp,
            ]);

            return response([
                'message' => 'OTP non valido',
            ], 401);
        }
    }

    public function resendOtp(Request $request)
    {
        $user = User::find(Auth::user()->id);
        $user->createOtp();

        return response()->json([
            'success' => true,
        ], 200);
    }

    /**
     * Registra un tentativo di accesso fallito e invia mail se necessario
     */
    private function recordFailedAttempt(string $email, ?int $userId, string $attemptType, Request $request, array $additionalData = []): void
    {
        // Registra il tentativo fallito
        FailedLoginAttempt::create([
            'email' => $email,
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempt_type' => $attemptType,
            'additional_data' => $additionalData,
        ]);

        // Conta i tentativi recenti
        $recentAttempts = FailedLoginAttempt::countRecentFailedAttempts($email);

        // Se raggiungiamo 5 tentativi, invia la mail di avviso
        if ($recentAttempts >= 5) {
            $failedAttempts = FailedLoginAttempt::getRecentFailedAttempts($email, 5);

            $adminEmail = config('mail.to_address');

            if ($adminEmail) {
                try {
                    Mail::to($adminEmail)->send(new SecurityAlert($email, $failedAttempts, $recentAttempts));

                    Log::info('Mail di sicurezza inviata per multipli tentativi falliti', [
                        'email' => $email,
                        'total_attempts' => $recentAttempts,
                        'admin_email' => $adminEmail,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Errore nell\'invio della mail di sicurezza', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
