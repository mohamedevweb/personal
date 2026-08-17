<?php

namespace App\Http\Controllers;

use App\Exceptions\InstagramIntegrationException;
use App\Services\Instagram\InstagramAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class InstagramCallbackController extends Controller
{
    public function __invoke(Request $request, InstagramAuthService $auth): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('services.instagram.frontend_url'), '/').'/onboarding';

        if ($request->filled('error')) {
            return redirect()->away($frontendUrl.'?'.http_build_query([
                'instagram' => 'error',
                'message' => $request->string('error_description')->toString() ?: 'Instagram authorization was cancelled.',
            ]));
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $auth->handleCallback(
                $request->string('code')->toString(),
                $request->string('state')->toString(),
            );

            return redirect()->away($frontendUrl.'?instagram=connected');
        } catch (InstagramIntegrationException $exception) {
            report($exception);

            return redirect()->away($frontendUrl.'?'.http_build_query([
                'instagram' => 'error',
                'message' => $exception->getMessage(),
            ]));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->away($frontendUrl.'?'.http_build_query([
                'instagram' => 'error',
                'message' => 'Instagram could not be connected. Please try again.',
            ]));
        }
    }
}
