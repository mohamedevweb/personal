export type InstagramSyncStatus =
  | 'connecting'
  | 'importing_content'
  | 'understanding_niche'
  | 'learning_style'
  | 'finding_patterns'
  | 'completed'
  | 'failed'

export interface InstagramAccount {
  username: string
  display_name: string | null
  profile_picture_url: string | null
  account_type: string | null
  followers_count: number | null
  media_count: number | null
  imported_media_count: number
  sync_status: InstagramSyncStatus
  sync_error: string | null
  connected_at: string
  last_synced_at: string | null
}

export interface CreatorProfile {
  niche: string | null
  topics: string[] | null
  tone: string[] | null
}

/** The steps the onboarding loader shows, in the order the analysis runs them. */
export type HandleAnalysisStage =
  | 'reading_profile'
  | 'importing_posts'
  | 'reading_voice'
  | 'mapping_audience'
  | 'transcribing_reels'

export type HandleAnalysisStatus =
  | 'idle'
  | 'queued'
  | HandleAnalysisStage
  | 'completed'
  | 'failed'

/**
 * What Personal has read off the public profile behind a handle, and how far it
 * got. Onboarding polls this and keeps the creator on the loader until it is
 * completed, so the next step never opens on an empty profile.
 */
export interface HandleAnalysis {
  status: HandleAnalysisStatus
  /** A stable reason key, translated on the client. */
  error: string | null
  stages: HandleAnalysisStage[]
  posts_target: number
  followers_count: number | null
  analyzed_posts_count: number | null
  bio: string | null
  niche: string | null
  tone: string[] | null
  audience_description: string | null
}

export interface InstagramStatusResponse {
  connected: boolean
  instagram_username?: string | null
  inspiration_count: number
  onboarding_complete: boolean
  analysis?: HandleAnalysis
  account?: InstagramAccount
  profile?: CreatorProfile | null
}

export interface CreatorInspiration {
  username: string
  display_name: string
  avatar_url: string | null
  followers: number
  niche: string | null
  is_selected: boolean
  is_measured: boolean
}

export interface CreatorInspirationResponse {
  selected: CreatorInspiration[]
  suggestions: CreatorInspiration[]
  suggestion_limit: number
  minimum: number
  maximum: number
}
