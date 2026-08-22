<script setup lang="ts">
defineProps<{ name: string, size?: number, filled?: boolean, strokeWidth?: number }>()
</script>

<template>
  <svg :width="size || 19" :height="size || 19" viewBox="0 0 24 24" :fill="filled ? 'currentColor' : 'none'" stroke="currentColor" :stroke-width="strokeWidth || 1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="name === 'sparkles'"><path d="m12 3-1.3 3.7L7 8l3.7 1.3L12 13l1.3-3.7L17 8l-3.7-1.3L12 3Z"/><path d="m5 14-.8 2.2L2 17l2.2.8L5 20l.8-2.2L8 17l-2.2-.8L5 14Zm13-1-1 2.7-2.7 1 2.7 1 1 2.7 1-2.7 2.7-1-2.7-1L18 13Z"/></template>
    <template v-else-if="name === 'plus'"><path d="M12 5v14M5 12h14"/></template>
    <template v-else-if="name === 'moments'"><path d="M6 3h12v18l-6-3-6 3V3Z"/><path d="M9 8h6M9 12h4"/></template>
    <template v-else-if="name === 'user'"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></template>
    <template v-else-if="name === 'bookmark'"><path d="M6 3h12v18l-6-4-6 4V3Z"/></template>
    <template v-else-if="name === 'settings'"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1v4H21a1.7 1.7 0 0 0-1.6 1Z"/></template>
    <template v-else-if="name === 'arrow'"><path d="M5 12h14m-5-5 5 5-5 5"/></template>
    <template v-else-if="name === 'dots'"><circle cx="5" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="19" cy="12" r="1" fill="currentColor"/></template>
    <template v-else-if="name === 'chat'"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.5 9.5 0 0 1-3.4-.6L3 21l1.3-3.5A8.4 8.4 0 1 1 21 11.5Z"/></template>
    <template v-else-if="name === 'close'"><path d="M6 6l12 12M18 6 6 18"/></template>
    <template v-else-if="name === 'send'"><path d="M12 19V5m0 0-6 6m6-6 6 6"/></template>
    <template v-else-if="name === 'trend'"><path d="M4 16.5 10 10l3.5 3.5L20 7"/><path d="M15 7h5v5"/></template>
    <template v-else-if="name === 'shield'"><path d="M12 3 5 6v6c0 4.2 2.9 7.6 7 9 4.1-1.4 7-4.8 7-9V6l-7-3Z"/><path d="m9.2 12 1.9 1.9 3.7-4"/></template>
    <template v-else-if="name === 'chevron'"><path d="m9 6 6 6-6 6"/></template>
    <template v-else-if="name === 'heart'"><path d="M20.8 5.9a4.9 4.9 0 0 0-7 0L12 7.7l-1.8-1.8a4.9 4.9 0 1 0-7 7l8.8 8.8 8.8-8.8a4.9 4.9 0 0 0 0-7Z"/></template>
    <template v-else-if="name === 'paper-plane'"><path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7Z"/></template>
    <template v-else-if="name === 'carousel'"><rect x="8.5" y="2.5" width="13" height="13" rx="2.6"/><path d="M15.5 18.6a2.9 2.9 0 0 1-2.9 2.9H5.4a2.9 2.9 0 0 1-2.9-2.9v-7.2a2.9 2.9 0 0 1 2.9-2.9"/></template>
    <template v-else-if="name === 'reel'"><rect x="2.5" y="2.5" width="19" height="19" rx="4.5"/><path d="M2.6 8.2h18.8M8.6 2.6 11.6 8.2m3.8-5.6 3 5.6"/><path d="m10.4 12.1 4.3 2.5-4.3 2.5v-5Z"/></template>
    <template v-else-if="name === 'eye'"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3.1"/></template>
    <template v-else-if="name === 'eye-off'"><path d="M4 4l16 16"/><path d="M9.6 5.9A8.9 8.9 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.4 4.1M6.4 7.9A17 17 0 0 0 2.5 12S6 18.5 12 18.5c1 0 1.9-.2 2.7-.5"/><path d="M10 10a3.1 3.1 0 0 0 4.2 4.2"/></template>
    <template v-else-if="name === 'text'"><path d="M4 6.5h16M4 12h16M4 17.5h9"/></template>
    <template v-else-if="name === 'copy'"><rect x="9" y="9" width="12" height="12" rx="2.4"/><path d="M6 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V6"/></template>
    <template v-else-if="name === 'refresh'"><path d="M20 11a8 8 0 1 0-.6 4"/><path d="M20 4.5V11h-6.5"/></template>
    <template v-else-if="name === 'trash'"><path d="M4 7h16M9.5 7V4.8h5V7M6.5 7l.8 12.2a1.6 1.6 0 0 0 1.6 1.5h6.2a1.6 1.6 0 0 0 1.6-1.5L17.5 7"/></template>
    <template v-else-if="name === 'arrow-up'"><path d="M12 19V5m0 0-6.5 6.5M12 5l6.5 6.5"/></template>
    <template v-else-if="name === 'clock'"><circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.4 2"/></template>
    <template v-else-if="name === 'mic'"><rect x="9" y="2.5" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/></template>
    <template v-else-if="name === 'mail'"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/></template>
    <template v-else-if="name === 'check'"><path d="m5 12.5 4.2 4.2L19 7"/></template>
    <template v-else-if="name === 'alert'"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.2M12 16.5h.01"/></template>
    <template v-else><circle cx="12" cy="12" r="9"/><path d="m9.5 12 1.7 1.7 3.8-4"/></template>
  </svg>
</template>
