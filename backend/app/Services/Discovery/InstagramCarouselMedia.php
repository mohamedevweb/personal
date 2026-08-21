<?php

namespace App\Services\Discovery;

class InstagramCarouselMedia
{
    /**
     * Normalize the child-media shapes returned by each discovery provider.
     *
     * @param  array<string, mixed>  $post
     * @return list<string>
     */
    public static function urls(array $post, ?string $fallback = null): array
    {
        $children = $post['carousel_media']
            ?? $post['childPosts']
            ?? $post['child_posts']
            ?? data_get($post, 'edge_sidecar_to_children.edges')
            ?? [];

        $urls = collect(is_array($children) ? $children : [])
            ->map(function (mixed $child): ?string {
                if (! is_array($child)) {
                    return null;
                }

                $media = is_array($child['node'] ?? null) ? $child['node'] : $child;
                $url = $media['thumbnail_url']
                    ?? $media['thumbnail_src']
                    ?? $media['display_uri']
                    ?? $media['display_url']
                    ?? $media['displayUrl']
                    ?? $media['image_url']
                    ?? $media['media_url']
                    ?? data_get($media, 'image_versions2.candidates.0.url')
                    ?? data_get($media, 'image_versions.0.url')
                    ?? null;

                return is_string($url) && $url !== '' ? $url : null;
            })
            ->filter()
            ->values()
            ->all();

        if ($urls === [] && $fallback) {
            $urls[] = $fallback;
        }

        return array_values(array_unique($urls));
    }
}
