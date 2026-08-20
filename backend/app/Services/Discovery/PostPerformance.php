<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

/**
 * The single definition of "this post performed", shared by every job that writes
 * a score so a number means the same thing wherever the post entered the feed.
 *
 * A post is always measured against its own creator's median engagement, never
 * against the batch it was scraped in. A batch mixes accounts of every size, which
 * makes an ordinary post from a large account look like a breakout — and buries a
 * genuine one from a small account.
 */
class PostPerformance
{
    /**
     * The account's normal post, as the median of its recent engagement. The median
     * rather than the mean, so one viral post does not raise the bar for everything
     * else the creator published.
     *
     * @param  Collection<int, DiscoveredPost>  $posts
     */
    public function baseline(Collection $posts): int
    {
        if ($posts->isEmpty()) {
            return 0;
        }

        return max(1, (int) round((float) $posts->map(fn (DiscoveredPost $post): int => $post->engagement())->median()));
    }

    /**
     * How far a post beats its creator's normal post: 1.0 is average for that
     * account, 2.0 is twice its usual reach. Zero when there is no baseline yet,
     * so an unmeasured post sorts below every measured one instead of guessing.
     */
    public function outlierScore(int $engagement, int $baseline): float
    {
        if ($baseline < 1) {
            return 0.0;
        }

        // Clamped to the column ceiling: a freak post against a tiny baseline would
        // otherwise overflow decimal(8,2) and fail the write.
        return min(999999.99, round($engagement / $baseline, 2));
    }

    /** Engagement as a percentage of the audience, comparable across account sizes. */
    public function engagementRate(int $engagement, int $followers): float
    {
        if ($followers < 1) {
            return 0.0;
        }

        return min(99999.999, round($engagement / $followers * 100, 3));
    }
}
