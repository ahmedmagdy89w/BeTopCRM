<script setup lang="ts">
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import { useI18n } from 'vue-i18n'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'
import type { DashboardRevenueMonth } from '@/types'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, Filler)

const { t } = useI18n()

const props = defineProps<{
  months: DashboardRevenueMonth[]
}>()

const chartData = computed(() => {
  const labels = props.months.map(m => {
    const [year, month] = m.month.split('-')
    return new Date(Number(year), Number(month) - 1).toLocaleDateString(undefined, {
      month: 'short',
      year: 'numeric',
    })
  })

  return {
    labels,
    datasets: [
      {
        label: t('dashboard.revenue'),
        data: props.months.map(m => parseFloat(m.revenue)),
        borderColor: '#0d6efd',
        backgroundColor: 'rgba(13, 110, 253, 0.1)',
        fill: true,
        tension: 0.3,
        pointBackgroundColor: '#0d6efd',
        pointRadius: 4,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: (value: string | number) => `$${Number(value).toLocaleString()}`,
      },
    },
  },
}
</script>

<template>
  <div style="height: 280px;">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
