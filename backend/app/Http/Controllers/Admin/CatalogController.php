<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Discovery\AdminCatalogImport;
use App\Models\AdminCatalogImport as AdminCatalogImportRecord;
use App\Models\Creator;
use App\Services\View\ContentPostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    public function creators(Request $request): JsonResponse
    {
        $this->authorizeCatalog($request);
        $data = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = trim((string) ($data['q'] ?? ''));

        $creators = Creator::query()
            ->where('curation_status', '!=', 'inactive')
            ->where('safety_status', '!=', 'blocked')
            ->when($query !== '', fn ($builder) => $builder->where(function ($builder) use ($query): void {
                $builder->where('username', 'like', '%'.$query.'%')
                    ->orWhere('display_name', 'like', '%'.$query.'%');
            }))
            ->orderBy('username')
            ->limit((int) ($data['limit'] ?? 100))
            ->get(['id', 'username', 'display_name', 'followers', 'primary_vertical', 'market']);

        return response()->json(['items' => $creators->map(fn (Creator $creator): array => [
            'id' => $creator->id,
            'username' => $creator->username,
            'display_name' => $creator->display_name,
            'followers' => $creator->followers,
            'vertical' => $creator->primary_vertical,
            'country_code' => $creator->market,
        ])->values()]);
    }

    public function index(Request $request, ContentPostView $posts): JsonResponse
    {
        $this->authorizeCatalog($request);
        $data = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'items' => AdminCatalogImportRecord::query()
                ->with(['creator', 'contentPost.creator'])
                ->latest('id')
                ->limit((int) ($data['limit'] ?? 50))
                ->get()
                ->map(fn (AdminCatalogImportRecord $import): array => $this->render($import, $request, $posts))
                ->values(),
        ]);
    }

    public function show(Request $request, AdminCatalogImportRecord $import, ContentPostView $posts): JsonResponse
    {
        $this->authorizeCatalog($request);

        return response()->json(['import' => $this->render($import->load(['creator', 'contentPost.creator']), $request, $posts)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeCatalog($request);
        $verticals = array_keys((array) config('creator_catalog.verticals'));
        $data = $request->validate([
            'type' => ['required', Rule::in(['creator', 'post'])],
            'url' => ['required', 'url', 'max:2048'],
            'vertical' => ['required', Rule::in($verticals)],
            'country_code' => ['required', Rule::in(config('creator_catalog.markets'))],
            'creator_id' => ['nullable', 'integer', Rule::exists('creators', 'id')],
        ]);
        $parts = $this->instagramUrl($data['url'], $data['type']);

        if ($data['type'] === 'post' && empty($data['creator_id'])) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'A creator is required for a post import.');
        }

        $creator = ! empty($data['creator_id']) ? Creator::query()->findOrFail($data['creator_id']) : null;
        $import = AdminCatalogImportRecord::query()->create([
            'initiated_by' => $request->user()->id,
            'creator_id' => $creator?->id,
            'type' => $data['type'],
            'url' => trim($data['url']),
            'creator_username' => $parts['username'] ?? $creator?->username,
            'vertical' => $data['vertical'],
            'country_code' => $data['country_code'],
            'status' => 'queued',
        ]);

        AdminCatalogImport::dispatch($import->id);

        return response()->json(['import' => $this->render($import, $request)], Response::HTTP_ACCEPTED);
    }

    private function authorizeCatalog(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->user()->email), config('app.catalog_admin_emails', []), true), Response::HTTP_NOT_FOUND);
    }

    /** @return array{username?: string} */
    private function instagramUrl(string $url, string $type): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        abort_unless(in_array($host, ['instagram.com', 'www.instagram.com'], true), Response::HTTP_UNPROCESSABLE_ENTITY, 'Use an Instagram URL.');
        $segments = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))));

        if ($type === 'creator') {
            abort_unless(count($segments) === 1 && preg_match('/^[A-Za-z0-9._]{1,30}$/', $segments[0]) === 1, Response::HTTP_UNPROCESSABLE_ENTITY, 'Use an Instagram profile URL.');

            return ['username' => $segments[0]];
        }

        abort_unless(count($segments) >= 2 && in_array(strtolower($segments[0]), ['p', 'reel', 'tv'], true), Response::HTTP_UNPROCESSABLE_ENTITY, 'Use an Instagram post URL.');

        return [];
    }

    /** @return array<string, mixed> */
    private function render(AdminCatalogImportRecord $import, Request $request, ?ContentPostView $posts = null): array
    {
        $post = $import->contentPost;
        $postView = $post && $posts ? $posts->make($post, $request->user(), null, false) : null;
        $creator = $import->creator ?: $post?->creator;

        return [
            'id' => $import->id,
            'type' => $import->type,
            'url' => $import->url,
            'creator_username' => $import->creator_username,
            'vertical' => $import->vertical,
            'country_code' => $import->country_code,
            'status' => $import->status,
            'error' => $import->error,
            'started_at' => $import->started_at,
            'completed_at' => $import->completed_at,
            'created_at' => $import->created_at,
            'creator' => $creator ? [
                'id' => $creator->id,
                'username' => $creator->username,
                'display_name' => $creator->display_name,
                'followers' => $creator->followers,
                'average_views' => $creator->average_views,
                'vertical' => $creator->primary_vertical,
                'country_code' => $creator->market,
            ] : null,
            'content_post' => $postView,
        ];
    }
}
