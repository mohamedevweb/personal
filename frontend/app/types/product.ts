export interface Creator {
  username: string
  display_name: string
  avatar_url: string | null
  niche: string
  niche_topics: string[]
  followers: number
  average_views: number
}

export interface ContentPost {
  id: number
  format: 'Reel' | 'Carousel'
  hook: string
  caption: string
  thumbnail_url: string | null
  media_urls: string[]
  views: number
  likes: number
  comments: number
  shares: number
  published_at: string
  performance_ratio: number
  /** Engagement over this creator's own median post. 1.0 is an average post for them. */
  outlier_score: number
  /** Engagement as a percentage of the creator's followers. */
  engagement_rate: number
  tags: string[]
  why_it_works: string
  hook_analysis: string
  structure_analysis: string
  analysis_status?: 'pending' | 'complete'
  recommendation_score?: number | null
  why_recommended?: string
  signals?: string[]
  is_saved: boolean
  creator: Creator
}

export interface LifeMoment {
  id: number
  content: string
  category: string
  happened_at: string | null
  upcoming_at: string | null
  story_score: number
  story_reasons: string[]
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
  creator_dna?: {
    primary_niche: string | null
    sub_niches: string[]
    topics: string[]
    audience: string[]
    language: string
    content_pillars: string[]
    tone: string[]
    analysis_status?: 'complete' | 'partial' | 'insufficient_evidence'
    analysis_method?: 'llm' | 'heuristic' | 'manual' | 'none'
    confidence?: number
    evidence?: {
      caption_count: number
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
    slides?: { id: number, text: string }[]
    hook?: string
    script?: string
    visual?: string
    cta?: string
    caption?: string
  }
  status: 'generating' | 'failed' | 'draft' | 'ready' | 'archived'
  source_content?: ContentPost
  life_moment?: LifeMoment | null
}

export function compactNumber(value: number): string {
  return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value)
}

export function relativeDate(value: string): string {
  const hours = Math.max(1, Math.round((Date.now() - new Date(value).getTime()) / 3_600_000))
  return hours < 24 ? `${hours}h ago` : `${Math.round(hours / 24)}d ago`
}

// Creators come from Instagram, so their handle is all we need to point back at
// the account the post was found on.
export function creatorProfileUrl(username: string): string {
  return `https://www.instagram.com/${username.replace(/^@/, '')}/`
}
