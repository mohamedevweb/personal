<?php

namespace App\Services;

use App\Models\ContentPost;
use App\Models\User;

class ContentPostView
{
    /**
     * @param  bool|null  $isSaved  Pass a known value when rendering a batch; leaving
     *                              it null costs one query per post.
     * @return array<string, mixed>
     */
    public function make(
        ContentPost $post,
        User $user,
        ?float $recommendationScore = null,
        ?bool $isSaved = null,
    ): array {
        $post->loadMissing('creator');

        return [
            'id' => $post->id,
            'format' => $post->format,
            'hook' => $post->hook,
            'caption' => $post->caption,
            'thumbnail_url' => $post->thumbnail_url,
            'views' => $post->views,
            'likes' => $post->likes,
            'comments' => $post->comments,
            'published_at' => $post->published_at,
            'performance_ratio' => $post->performance_ratio,
            'outlier_score' => $post->outlier_score,
            'engagement_rate' => $post->engagement_rate,
            'tags' => $post->tags ?? [],
            'why_it_works' => $post->why_it_works,
            'hook_analysis' => $post->hook_analysis,
            'structure_analysis' => $post->structure_analysis,
            'recommendation_score' => $recommendationScore,
            'is_saved' => $isSaved ?? $user->savedContent()->where('content_post_id', $post->id)->exists(),
            'creator' => [
                'username' => $post->creator->username,
                'display_name' => $post->creator->display_name,
                'avatar_url' => $post->creator->avatar_url,
                'niche' => $post->creator->niche,
                'niche_topics' => $post->creator->niche_topics ?? [],
                'followers' => $post->creator->followers,
                'average_views' => $post->creator->average_views,
            ],
        ];
    }
}
