<script setup lang="ts">
import type { ContentPost } from '~/types/product'

const post: ContentPost = {
  id: 1,
  format: 'Carousel',
  hook: 'what makes someone unforgettable to you?',
  caption: 'what makes someone unforgettable to you? A carousel with an expired frame.',
  source_url: null,
  thumbnail_url: 'https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&w=1200&q=80',
  video_url: null,
  media_urls: [
    'https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&w=1200&q=80',
    'http://localhost:8000/api/media/content/1/1?signature=expired',
    'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80'
  ],
  views: 0,
  likes: 13100,
  comments: 83,
  shares: 0,
  published_at: new Date().toISOString(),
  performance_ratio: 5.5,
  outlier_score: 5.5,
  engagement_rate: 0.2,
  tags: [],
  why_it_works: '',
  hook_analysis: '',
  structure_analysis: '',
  is_saved: false,
  creator: {
    username: 'jun_yuh',
    display_name: 'Jun Yuh',
    avatar_url: null,
    niche: 'tech',
    niche_topics: [],
    followers: 5300000,
    average_views: 100000
  }
}
</script>

<template>
  <div class="mx-auto max-w-[420px] p-4">
    <ContentCard :post="post" />
  </div>
</template>
