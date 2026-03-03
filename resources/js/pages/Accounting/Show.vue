<template>
  <Head title="Workflow Details" />

  <AppLayout>
    <div class="space-y-6 w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">Accounting Workflow</h1>
          <p class="text-gray-500">Review and process this transaction workflow</p>
        </div>
      </div>

      <!-- Workflow Details -->
      <div v-if="workflow" class="grid grid-cols-3 gap-6">
        <!-- Reference & Status -->
        <div class="border rounded-xl shadow-sm bg-white p-6">
          <p class="text-sm text-gray-600">Reference</p>
          <p class="text-lg font-mono font-bold mt-2">{{ workflow.reference }}</p>
          <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-600">Status</p>
            <span
              :class="{
                'bg-yellow-100 text-yellow-800': workflow.status === 'pending',
                'bg-blue-100 text-blue-800': workflow.status === 'in_progress',
                'bg-green-100 text-green-800': workflow.status === 'approved',
                'bg-red-100 text-red-800': workflow.status === 'rejected',
              }"
              class="inline-block px-3 py-1 rounded-full text-sm font-semibold mt-2"
            >
              {{ workflow.status }}
            </span>
          </div>
        </div>

        <!-- Student Info -->
        <div class="border rounded-xl shadow-sm bg-white p-6">
          <p class="text-sm text-gray-600">Student</p>
          <p class="text-lg font-bold mt-2">{{ workflow.student_name }}</p>
          <p class="text-sm text-gray-600 mt-1">ID: {{ workflow.student_id }}</p>
          <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-600">Year Level</p>
            <p class="font-semibold">{{ workflow.student_year_level }}</p>
          </div>
        </div>

        <!-- Amount Info -->
        <div class="border rounded-xl shadow-sm bg-white p-6">
          <p class="text-sm text-gray-600">Amount</p>
          <p class="text-3xl font-bold text-green-600 mt-2">₱{{ formatCurrency(workflow.amount) }}</p>
          <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-600">Type</p>
            <p class="font-semibold">{{ workflow.type }}</p>
          </div>
        </div>
      </div>

      <!-- Transaction Details -->
      <div v-if="workflow" class="border rounded-xl shadow-sm bg-white p-6 space-y-4">
        <h2 class="text-lg font-bold">Transaction Details</h2>
        <div class="grid grid-cols-2 gap-6">
          <div>
            <p class="text-sm text-gray-600">Payment Method</p>
            <p class="font-semibold">{{ workflow.payment_method }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Created At</p>
            <p class="font-semibold">{{ formatDate(workflow.created_at) }}</p>
          </div>
          <div v-if="workflow.reference_number">
            <p class="text-sm text-gray-600">Reference Number</p>
            <p class="font-semibold">{{ workflow.reference_number }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Last Updated</p>
            <p class="font-semibold">{{ formatDate(workflow.updated_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="workflow && workflow.status === 'pending'" class="flex gap-3">
        <button
          @click="approveWorkflow"
          :disabled="processing"
          class="flex-1 px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
        >
          {{ processing ? 'Processing...' : 'Approve & Process' }}
        </button>
        <button
          @click="rejectWorkflow"
          :disabled="processing"
          class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
        >
          {{ processing ? 'Processing...' : 'Declined' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

interface Workflow {
  id: number
  reference: string
  type: string
  status: string
  student_name: string
  student_id: string
  student_year_level: string
  amount: number
  payment_method: string
  reference_number: string | null
  created_at: string
  updated_at: string
}

interface Props {
  workflow: Workflow
}

const props = defineProps<Props>()
const processing = ref(false)

const breadcrumbs = [
  { title: 'Dashboard', href: route('accounting.dashboard') },
  { title: 'Workflows', href: route('accounting-workflows.index') },
  { title: 'Details' },
]

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const approveWorkflow = async () => {
  processing.value = true
  router.post(
    route('accounting-workflows.submit', props.workflow.id),
    {},
    {
      onFinish: () => {
        processing.value = false
      },
    }
  )
}

const rejectWorkflow = async () => {
  const reason = prompt('Please provide a reason for decline:')
  if (!reason) return

  processing.value = true
  router.delete(route('accounting-workflows.destroy', props.workflow.id), {
    onFinish: () => {
      processing.value = false
    },
  })
}
</script>
