import type { ContentPost } from '~/types/product'

/**
 * The feed has no cursor: each request carries the ids already on screen and the
 * API answers with the next best-ranked batch. The cap matches the one the API
 * validates against, and bounds the bind parameters on its pool query.
 */
export const FEED_EXCLUDE_LIMIT = 500

export interface FeedRotation {
  /**
   * The ids to exclude from the next request. Past the cap the oldest are
   * dropped: they sit furthest up the page, where a repeat is least visible.
   */
  exclude: () => number[]
  /**
   * Records a batch and returns only the posts that are new to the rotation.
   * An empty result means the request brought nothing the reader has not
   * already scrolled past, and the feed has reached its end.
   */
  accept: (items: ContentPost[]) => ContentPost[]
  /** Starts the rotation over, so the whole catalogue is eligible again. */
  forget: () => void
}

export function createFeedRotation(limit = FEED_EXCLUDE_LIMIT): FeedRotation {
  const seen = new Set<number>()

  return {
    exclude: () => [...seen].slice(-limit),
    accept: (items) => {
      const fresh = items.filter(post => !seen.has(post.id))
      fresh.forEach(post => seen.add(post.id))

      return fresh
    },
    forget: () => seen.clear()
  }
}
