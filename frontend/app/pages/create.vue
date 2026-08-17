<script setup lang="ts">
import type { LifeMoment, Opportunity } from '~/types/product'

const { apiFetch } = usePersonalApi()
const opportunities = ref<Opportunity[]>([])
const moments = ref<LifeMoment[]>([])

async function createFromMoment(moment: LifeMoment, format: string = 'carousel') {
  const response = await apiFetch<{ remix: { id: number } }>(`/api/moments/${moment.id}/create-content`, { method: 'POST', body: { format } })
  await navigateTo(`/remix/${response.remix.id}`)
}

onMounted(async () => {
  const [ops, momentData] = await Promise.all([apiFetch<{ opportunities: Opportunity[] }>('/api/opportunities'), apiFetch<{ moments: LifeMoment[] }>('/api/moments')])
  opportunities.value = ops.opportunities; moments.value = momentData.moments
})
</script>

<template>
  <main class="mx-auto max-w-5xl px-5 py-10 md:px-10 md:py-14">
    <p class="text-[11px] font-semibold uppercase tracking-[.17em] text-[#918d85]">Create</p><h1 class="mt-4 font-serif text-4xl tracking-[-.04em] md:text-[54px]">Start with something real.</h1><p class="mt-4 max-w-2xl text-[16px] leading-7 text-[#716e67]">The strongest idea is usually a pattern that works, connected to a story only you can tell.</p>
    <section class="mt-10 rounded-[28px] bg-[#22221f] p-7 text-white md:p-9"><p class="text-[10px] font-semibold uppercase tracking-[.16em] text-[#d7a999]">Personal’s pick</p><h2 class="mt-4 max-w-3xl font-serif text-3xl leading-tight">{{ opportunities[0]?.title || 'Tell the story behind your latest product decision.' }}</h2><p class="mt-3 max-w-2xl text-sm leading-6 text-white/60">{{ opportunities[0]?.explanation || 'A concrete decision creates tension, insight and a useful takeaway.' }}</p><button v-if="opportunities[0]?.life_moment" class="mt-7 rounded-full bg-white px-5 py-3 text-sm font-medium text-[#22221f]" @click="createFromMoment(opportunities[0].life_moment!)">Create this →</button></section>
    <div class="mt-12 grid gap-5 md:grid-cols-3">
      <button v-for="(item, index) in [{ title:'Tell a story', copy:'Turn a life moment into a narrative people remember.', format:'carousel' },{ title:'Teach something', copy:'Shape one lesson into a useful carousel.', format:'carousel' },{ title:'Share an opinion', copy:'Build a clear, credible talking-head reel.', format:'reel' }]" :key="item.title" class="rounded-[22px] border border-[var(--line)] bg-[#fbfaf7] p-6 text-left transition hover:-translate-y-0.5 hover:shadow-lg" @click="moments[index % Math.max(moments.length, 1)] && createFromMoment(moments[index % moments.length], item.format)"><span class="grid h-9 w-9 place-items-center rounded-full bg-[#eee9e0] text-sm">0{{ index + 1 }}</span><h3 class="mt-6 font-serif text-2xl">{{ item.title }}</h3><p class="mt-2 text-sm leading-6 text-[#77736c]">{{ item.copy }}</p><span class="mt-6 inline-flex items-center gap-2 text-xs font-medium">Begin <AppIcon name="arrow" :size="14"/></span></button>
    </div>
    <div class="mt-12 flex items-center justify-between border-b border-[var(--line)] pb-4"><h2 class="text-sm font-medium">Your best raw material</h2><NuxtLink to="/moments" class="text-xs text-[#77736c]">View all moments →</NuxtLink></div>
    <div class="mt-4 divide-y divide-[var(--line)]"><button v-for="moment in moments.slice(0,3)" :key="moment.id" class="flex w-full items-center gap-5 py-5 text-left" @click="createFromMoment(moment)"><span class="font-serif text-2xl text-[#a44f36]">{{ moment.story_score }}</span><p class="flex-1 text-sm md:text-base">{{ moment.content }}</p><span class="text-xs text-[#88847d]">Turn into content →</span></button></div>
  </main>
</template>
