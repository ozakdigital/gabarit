<script setup lang="ts">
import { ref } from 'vue'
import CodeBlock from './CodeBlock.vue'

export interface OsTab {
  label: string
  code: string
}

const props = defineProps<{ tabs: OsTab[] }>()

// Onglet Windows par défaut sous Windows, sinon le premier.
const isWindows = navigator.userAgent.includes('Windows')
const windowsIndex = props.tabs.findIndex((t) => t.label.includes('Windows'))
const active = ref(isWindows && windowsIndex >= 0 ? windowsIndex : 0)
</script>

<template>
  <div class="tabs">
    <div class="tab-list" role="tablist">
      <button
        v-for="(tab, i) in tabs"
        :key="tab.label"
        type="button"
        role="tab"
        :aria-selected="active === i"
        :class="{ active: active === i }"
        @click="active = i"
      >
        {{ tab.label }}
      </button>
    </div>
    <CodeBlock :code="tabs[active]?.code ?? ''" />
  </div>
</template>
