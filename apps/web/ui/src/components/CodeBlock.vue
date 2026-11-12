<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ code: string; title?: string }>()
const copied = ref(false)

async function copy(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.code)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch {
    // presse-papiers indisponible (HTTP non sécurisé) : on ne fait rien
  }
}
</script>

<template>
  <div class="glass code">
    <div class="code-bar">
      <span>{{ title ?? '' }}</span>
      <button type="button" class="copy" @click="copy">{{ copied ? 'Copié' : 'Copier' }}</button>
    </div>
    <pre><code>{{ code }}</code></pre>
  </div>
</template>
