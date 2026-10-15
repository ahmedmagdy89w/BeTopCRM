<script setup lang="ts">
import SkeletonLoader from '@/components/base/SkeletonLoader.vue'

withDefaults(defineProps<{
  columns?: number
}>(), {
  columns: 5,
})
</script>

<template>
  <tr>
    <td v-for="n in columns" :key="n">
      <SkeletonLoader :width="n === 1 ? '70%' : n === columns ? '50%' : '80%'" />
    </td>
  </tr>
</template>
