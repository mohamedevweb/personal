<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QueueStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueDashboardController extends Controller
{
    public function __invoke(Request $request, QueueStatusService $status): JsonResponse
    {
        $email = strtolower(trim((string) $request->user()->email));
        abort_unless(in_array($email, config('app.queue_dashboard_emails', []), true), 404);

        $data = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($status->snapshot((int) ($data['limit'] ?? 50)));
    }
}
