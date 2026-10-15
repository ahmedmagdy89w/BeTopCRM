<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { useI18n } from 'vue-i18n'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import { LEAD_STATUS_COLORS } from '@/utils/constants'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const { t } = useI18n()

const props = defineProps<{
  byStatus: Record<string, number>
}>()

const BOOTSTRAP_COLORS: Record<string, string> = {
  info: '#0dcaf0',
  primary: '#0d6efd',
  warning: '#ffc107',
  secondary: '#6c757d',
  dark: '#212529',
  success: '#198754',
  danger: '#dc3545',
}

const chartData = computed(() => {
  const statuses = Object.keys(props.byStatus)
  return {
    labels: statuses.map(s => t(`customers.leadStatuses.${s || 'new'}`)),
    datasets: [
      {
        data: statuses.map(s => props.byStatus[s] ?? 0),
        backgroundColor: statuses.map(s => {
          const status = s || 'new'
          const variant = LEAD_STATUS_COLORS[status as keyof typeof LEAD_STATUS_COLORS] ?? 'secondary'
          return BOOTSTRAP_COLORS[variant] ?? '#6c757d'
        }),
        borderRadius: 4,
      },
    ],
  }
})

const chartOptions = {
  indexAxis: 'y' as const,
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 } },
  },
}
</script>

<template>
  <div style="height: 280px;">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>
