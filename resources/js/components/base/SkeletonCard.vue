<script setup lang="ts">
import SkeletonLoader from '@/components/base/SkeletonLoader.vue'

withDefaults(defineProps<{
  lines?: number
}>(), {
  lines: 3,
})
</script>

<template>
  <div class="card">
    <div class="card-body">
      <SkeletonLoader width="40%" height="1.25rem" class="mb-3" />
      <SkeletonLoader
        v-for="n in lines"
        :key="n"
        :width="n === lines ? '60%' : '100%'"
        height="0.875rem"
        class="mb-2"
      />
    </div>
  </div>
</template>
