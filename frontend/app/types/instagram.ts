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

export interface InstagramStatusResponse {
  connected: boolean
  account?: InstagramAccount
  profile?: CreatorProfile | null
}
