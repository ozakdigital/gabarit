<script setup lang="ts">
import { onMounted } from 'vue'
import CodeBlock from '../components/CodeBlock.vue'
import { loadMatrix, matrix, matrixError } from '../matrix'

onMounted(() => void loadMatrix())
</script>

<template>
  <p class="lead">
    Toutes les options viennent du fichier <code>config/matrix.yaml</code>, la source de vérité de Gabarit.
    Une combinaison absente est refusée avant toute installation. Ce tableau est lu en direct depuis la matrice.
  </p>

  <p v-if="matrixError" class="alert">{{ matrixError }}</p>
  <p v-else-if="!matrix" class="muted">Chargement…</p>
  <template v-else>
    <h2>Frameworks</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Framework</th><th>Version</th><th>PHP</th><th>Bases</th><th>Fronts</th></tr></thead>
        <tbody>
          <template v-for="(fw, key) in matrix.frameworks" :key="key">
            <tr v-for="(v, version) in fw.versions" :key="version">
              <td><strong>{{ fw.label }}</strong></td>
              <td>{{ version }}</td>
              <td>{{ v.php.join(', ') }}</td>
              <td>{{ fw.databases.map((d) => matrix?.databases[d]?.label ?? d).join(', ') }}</td>
              <td>{{ fw.frontends.join(', ') }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <h2>Fronts</h2>
    <ul>
      <li v-for="(f, key) in matrix.frontends" :key="key"><code>{{ key }}</code> — {{ f.label }}{{ f.spa ? ' (application séparée dans frontend/)' : '' }}</li>
    </ul>

    <h2>Services</h2>
    <ul>
      <li v-for="(s, key) in matrix.services" :key="key"><code>{{ key }}</code> — {{ s.label }}</li>
    </ul>
  </template>

  <h2>Depuis le terminal</h2>
  <CodeBlock code="gabarit matrix
gabarit matrix --json" />
</template>
