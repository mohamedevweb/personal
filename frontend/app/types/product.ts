export interface Creator {
  username: string
  display_name: string
  avatar_url: string | null
  niche: string
  niche_topics: string[]
  vertical?: PersonalProfile['primary_vertical']
  followers: number
  average_views: number
}

/**
 * What the performance ratio was measured against, so the app can show its work.
 * `format` is null when the account has posted too few of that format to have a
 * normal of its own and the whole account was used instead.
 */
export interface PerformanceBenchmark {
  format: 'reel' | 'carousel' | 'image' | null
  posts: number
  views: number | null
  engagement: number | null
}

export interface ContentPost {
  id: number
  format: 'Reel' | 'Carousel' | 'reel' | 'carousel' | 'image'
  hook: string
  caption: string
  source_url: string | null
  thumbnail_url: string | null
  video_url: string | null
  media_urls: string[]
  views: number
  likes: number
  comments: number
  shares: number
  published_at: string
  performance_ratio: number
  /** Engagement over this creator's own median post of the same format. 1.0 is ordinary for them. */
  outlier_score: number
  benchmark?: PerformanceBenchmark
  /** Engagement as a percentage of the creator's followers. */
  engagement_rate: number
  tags: string[]
  why_it_works: string
  hook_analysis: string
  structure_analysis: string
  /** What was read off the slides of a carousel, in reading order. Empty until the visual analysis has run. */
  carousel_slides?: { position: number, text: string, role: string }[]
  analysis_status?: 'pending' | 'complete'
  recommendation_score?: number | null
  /** Match between this post's creator/topics and the member's Creator DNA. */
  creator_fit_score?: number | null
  why_recommended?: string
  signals?: string[]
  is_saved: boolean
  creator: Creator
}

export interface FeedResponse {
  opportunity_count: number
  personalization: {
    niche: string | null
    primary_vertical: PersonalProfile['primary_vertical']
    topics: string[]
    tone: string[]
  }
  featured_opportunity?: Opportunity | null
  items: ContentPost[]
  explore_items: ContentPost[]
}

export type DismissReason = 'topic' | 'creator' | 'language'

export interface LifeMoment {
  id: number
  content: string
  category: string
  happened_at: string | null
  upcoming_at: string | null
  created_at: string
}

export interface Opportunity {
  id: number
  title: string
  explanation: string
  relevance_score: number
  origin: 'combined' | 'life_moment' | 'trending_content'
  content_post_id?: number | null
  life_moment_id?: number | null
  content_post?: any
  life_moment?: LifeMoment | null
}

export interface PersonalProfile {
  id?: number
  instagram_username?: string | null
  avatar_url?: string | null
  display_name: string | null
  bio: string | null
  niche: string | null
  market: 'FR' | 'GB' | 'US' | null
  market_confidence: number | null
  primary_vertical: 'sport-fitness' | 'food-cooking' | 'personal-branding' | 'tech-ai' | 'beauty-fashion' | 'wellness' | null
  audience_description: string | null
  positioning: string | null
  topics: string[] | null
  tone: string[] | null
  current_projects: string[] | null
  goals: string[] | null
  content_strengths: string[] | null
  voice_profile: string | null
  analysis_status?: 'idle' | 'queued' | 'reading_profile' | 'importing_posts' | 'reading_voice' | 'mapping_audience' | 'transcribing_reels' | 'completed' | 'failed'
  media_enrichment_status?: 'idle' | 'queued' | 'importing_media' | 'processing' | 'completed' | 'failed'
  media_enrichment_error?: string | null
  creator_dna?: {
    primary_niche: string | null
    sub_niches: string[]
    topics: string[]
    audience: string[]
    positioning?: string | null
    language: string
    content_pillars: string[]
    tone: string[]
    current_projects?: string[]
    goals?: string[]
    content_strengths?: string[]
    reasoning_patterns?: string[]
    hook_patterns?: string[]
    visual_patterns?: string[]
    voice_profile?: string | null
    analysis_version?: number
    analysis_status?: 'complete' | 'partial' | 'insufficient_evidence' | 'analysis_unavailable'
    analysis_method?: 'llm' | 'heuristic' | 'manual' | 'none'
    confidence?: number
    evidence?: {
      caption_count: number
      transcript_count?: number
      carousel_count?: number
      carousel_slide_count?: number
      bio_available: boolean
      link_preview_available: boolean
    }
  } | null
}

export interface Remix {
  id: number
  format: 'reel' | 'carousel' | 'caption'
  generated_content: {
    original_pattern: string
    why_it_works: string[]
    your_context: string
    your_version: string
    slides?: {
      id: number
      text: string
      /** Which picture to put on this slide, and how to frame it. */
      image?: string
      /** The slide of the source this one was written against, 1-based. Null on a slide added by hand. */
      source_position?: number | null
    }[]
    hook?: string
    script?: string
    visual?: string
    ending?: string
    cta?: string
    caption?: string
  }
  status: 'generating' | 'failed' | 'draft' | 'ready' | 'archived'
  copy_count?: number
  last_copied_at?: string | null
  created_at?: string
  updated_at?: string
  source_content?: ContentPost
  life_moment?: LifeMoment | null
}

export interface RemixSummary {
  id: number
  format: Remix['format']
  generated_content: Remix['generated_content']
  status: Remix['status']
  updated_at: string
  source_content: {
    id: number
    hook: string
    creator: Pick<Creator, 'username'>
  }
}

export function compactNumber(value: number): string {
  return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value)
}

export function relativeDate(value: string, locale = 'en'): string {
  const hours = Math.max(1, Math.round((Date.now() - new Date(value).getTime()) / 3_600_000))
  const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'always', style: 'short' })
  return hours < 24 ? formatter.format(-hours, 'hour') : formatter.format(-Math.round(hours / 24), 'day')
}

// Creators come from Instagram, so their handle is all we need to point back at
// the account the post was found on.
export function creatorProfileUrl(username: string): string {
  return `https://www.instagram.com/${username.replace(/^@/, '')}/`
}
