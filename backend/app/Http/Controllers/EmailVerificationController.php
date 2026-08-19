<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    /**
     * Land the signed link baked into the verification email. The click arrives
     * from a plain browser with no bearer token, so the user is resolved from the
     * signed URL itself rather than the auth guard, then bounced to the SPA.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->redirectToFrontend('invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->redirectToFrontend('verified');
    }

    /**
     * Re-send the verification email to the signed-in creator on demand.
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => __('Your email is already verified.')]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => __('Verification link sent.')], Response::HTTP_ACCEPTED);
    }

    private function redirectToFrontend(string $status): RedirectResponse
    {
        return redirect(rtrim((string) config('app.frontend_url'), '/').'/verify-email?status='.$status);
    }
}
