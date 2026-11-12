<script setup lang="ts">
import { computed } from 'vue'
import { docs } from '../docs'
import { link, route } from '../router'

const index = computed(() => Math.max(0, docs.findIndex((d) => d.slug === route.value.slug)))
const current = computed(() => docs[index.value]!)
const previous = computed(() => docs[index.value - 1])
const next = computed(() => docs[index.value + 1])
const groupSlug = computed(() =>
  current.value.group
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, '-'),
)

const groups = computed(() => {
  const result: { name: string; pages: typeof docs }[] = []
  for (const page of docs) {
    let group = result.find((g) => g.name === page.group)
    if (!group) result.push((group = { name: page.group, pages: [] }))
    group.pages.push(page)
  }
  return result
})
</script>

<template>
  <div class="docs">
    <aside class="sidebar" aria-label="Sommaire de la documentation">
      <div v-for="g in groups" :key="g.name" class="side-group">
        <div class="side-title">{{ g.name }}</div>
        <a v-for="p in g.pages" :key="p.slug" :href="link('/docs/' + p.slug)" :class="{ active: p.slug === current.slug }">
          {{ p.title }}
        </a>
      </div>
    </aside>

    <article class="prose">
      <p class="crumb">docs / {{ groupSlug }} / <span>{{ current.slug }}</span></p>
      <h1>{{ current.title }}</h1>
      <component :is="current.component" />

      <nav class="pager">
        <a v-if="previous" :href="link('/docs/' + previous.slug)" class="glass prev">
          <small>← précédent</small>{{ previous.title }}
        </a>
        <a v-if="next" :href="link('/docs/' + next.slug)" class="glass next">
          <small>suivant →</small>{{ next.title }}
        </a>
      </nav>
    </article>
  </div>
</template>
