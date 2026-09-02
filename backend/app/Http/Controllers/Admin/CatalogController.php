<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Discovery\AdminCatalogImport;
use App\Models\AdminCatalogImport as AdminCatalogImportRecord;
use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\Discovery\CanonicalCreatorVerticals;
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);
        $query = trim((string) ($data['q'] ?? ''));

        $creators = Creator::query()
            ->withCount('posts')
            ->when($query !== '', fn ($builder) => $builder->where(function ($builder) use ($query): void {
                $builder->where('username', 'like', '%'.$query.'%')
                    ->orWhere('display_name', 'like', '%'.$query.'%');
            }))
            ->orderBy('username')
            ->limit((int) ($data['limit'] ?? 500))
            ->get(['id', 'username', 'display_name', 'followers', 'average_views', 'primary_vertical', 'market', 'curation_status', 'safety_status']);

        return response()->json(['items' => $creators->map(fn (Creator $creator): array => $this->renderCreator($creator))->values()]);
    }

    public function updateCreator(Request $request, Creator $creator): JsonResponse
    {
        $this->authorizeCatalog($request);
        $verticals = array_keys((array) config('creator_catalog.verticals'));
        $data = $request->validate([
            'vertical' => ['nullable', Rule::in($verticals)],
        ]);

        $creator->update([
            'primary_vertical' => array_key_exists('vertical', $data)
                ? $data['vertical']
                : $creator->primary_vertical,
        ]);

        return response()->json(['creator' => $this->renderCreator($creator->fresh() ?? $creator)]);
    }

    public function posts(Request $request, CanonicalCreatorVerticals $verticals, ContentPostView $postView): JsonResponse
    {
        $this->authorizeCatalog($request);
        $data = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'vertical' => ['sometimes', 'nullable', Rule::in($verticals->slugs())],
            'creator_id' => ['sometimes', 'integer', Rule::exists('creators', 'id')],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);
        $query = trim((string) ($data['q'] ?? ''));
        $posts = ContentPost::query()
            ->with('creator')
            ->when($query !== '', fn ($builder) => $builder->where(function ($builder) use ($query): void {
                $builder->where('hook', 'like', '%'.$query.'%')
                    ->orWhere('caption', 'like', '%'.$query.'%')
                    ->orWhere('source_url', 'like', '%'.$query.'%')
                    ->orWhereHas('creator', function ($creator) use ($query): void {
                        $creator->where('username', 'like', '%'.$query.'%')
                            ->orWhere('display_name', 'like', '%'.$query.'%');
                    });
            }))
            ->when(array_key_exists('creator_id', $data), fn ($builder) => $builder->where('creator_id', $data['creator_id']))
            ->when(array_key_exists('vertical', $data), function ($builder) use ($data): void {
                $vertical = $data['vertical'] ?? null;

                if ($vertical === null) {
                    $builder->where(function ($builder): void {
                        $builder->whereNull('metadata')
                            ->orWhereNull('metadata->feed_classification->vertical');
                    });

                    return;
                }

                $builder->where('metadata->feed_classification->vertical', $vertical);
            })
            ->latest('published_at')
            ->latest('id')
            ->limit((int) ($data['limit'] ?? 500))
            ->get();

        return response()->json(['items' => $posts->map(fn (ContentPost $post): array => $this->renderPost($post, $request, $postView, $verticals))->values()]);
    }

    public function updatePost(Request $request, ContentPost $post, CanonicalCreatorVerticals $verticals, ContentPostView $postView): JsonResponse
    {
        $this->authorizeCatalog($request);
        $data = $request->validate([
            'vertical' => ['required', Rule::in($verticals->slugs())],
        ]);
        $metadata = is_array($post->metadata) ? $post->metadata : [];
        $classification = is_array($metadata['feed_classification'] ?? null) ? $metadata['feed_classification'] : [];
        $metadata['feed_classification'] = array_replace($classification, ['vertical' => $data['vertical']]);
        $post->update(['metadata' => $metadata]);

        return response()->json(['post' => $this->renderPost($post->fresh('creator'), $request, $postView, $verticals)]);
    }

    public function destroyPost(Request $request, ContentPost $post): Response
    {
        $this->authorizeCatalog($request);
        $post->delete();

        return response()->noContent();
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

    /** @return array<string, mixed> */
    private function renderCreator(Creator $creator): array
    {
        return [
            'id' => $creator->id,
            'username' => $creator->username,
            'display_name' => $creator->display_name,
            'followers' => $creator->followers,
            'average_views' => $creator->average_views,
            'vertical' => $creator->primary_vertical,
            'country_code' => $creator->market,
            'posts_count' => (int) ($creator->posts_count ?? $creator->posts()->count()),
            'curation_status' => $creator->curation_status,
            'safety_status' => $creator->safety_status,
        ];
    }

    /** @return array<string, mixed> */
    private function renderPost(ContentPost $post, Request $request, ContentPostView $postView, CanonicalCreatorVerticals $verticals): array
    {
        $creator = $post->creator;
        $storedVertical = data_get($post->metadata, 'feed_classification.vertical');
        $postData = $postView->make($post, $request->user(), null, false);

        return [
            'id' => $post->id,
            'vertical' => $verticals->canonical(is_string($storedVertical) ? $storedVertical : null),
            'published_at' => $post->published_at,
            'source_url' => $post->source_url,
            'format' => $post->format,
            'hook' => $post->hook,
            'thumbnail_url' => $postData['thumbnail_url'],
            'views' => $post->views,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'engagement_rate' => $post->engagement_rate,
            'outlier_score' => $post->outlier_score,
            'creator' => [
                'id' => $creator->id,
                'username' => $creator->username,
                'display_name' => $creator->display_name,
                'vertical' => $creator->primary_vertical,
            ],
        ];
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
