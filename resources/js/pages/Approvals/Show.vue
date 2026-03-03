<template>
  <Head title="Approval Details" />

  <AppLayout>
    <div class="space-y-6 w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">Approval Details</h1>
          <p class="text-gray-500">Review and action this workflow approval</p>
        </div>
      </div>

      <!-- Approval Details Card -->
      <div v-if="approval" class="border rounded-xl shadow-sm bg-white p-6 space-y-6">
        <!-- Status Badge -->
        <div class="flex items-center gap-4">
          <span
            :class="{
              'bg-yellow-100 text-yellow-800': approval.status === 'pending',
              'bg-green-100 text-green-800': approval.status === 'approved',
              'bg-red-100 text-red-800': approval.status === 'rejected',
            }"
            class="px-4 py-2 rounded-full font-semibold text-sm"
          >
            {{ approval.status }}
          </span>
        </div>

        <!-- Workflow Item Info -->
        <div class="grid grid-cols-2 gap-4 border-b pb-4">
          <div>
            <p class="text-sm text-gray-600">Type</p>
            <p class="font-semibold">{{ approval.workflowable_type }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Created</p>
            <p class="font-semibold">{{ formatDate(approval.created_at) }}</p>
          </div>
        </div>

        <!-- Approver & Metadata -->
        <div class="grid grid-cols-2 gap-4 border-b pb-4">
          <div>
            <p class="text-sm text-gray-600">Assigned to</p>
            <p class="font-semibold">{{ approval.approver_name || 'Unassigned' }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Last Updated</p>
            <p class="font-semibold">{{ formatDate(approval.updated_at) }}</p>
          </div>
        </div>

        <!-- Comment -->
        <div v-if="approval.comment" class="border-t pt-4">
          <p class="text-sm text-gray-600">Comment</p>
          <p class="mt-2 p-4 bg-gray-50 rounded-lg">{{ approval.comment }}</p>
        </div>

        <!-- Action Buttons (if pending) -->
        <div v-if="approval.status === 'pending'" class="flex gap-3 pt-4 border-t">
          <button
            @click="approve"
            :disabled="processing"
            class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
          >
            {{ processing ? 'Processing...' : 'Approve' }}
          </button>
          <button
            @click="openRejectDialog"
            :disabled="processing"
            class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
          >
            Declined
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else class="text-center py-12">
        <p class="text-gray-500">Loading approval details...</p>
      </div>

      <!-- Reject Reason Dialog -->
      <div v-if="showRejectDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
          <h2 class="text-xl font-bold mb-4">Decline Approval</h2>
          <p class="text-gray-600 mb-4">Please provide a reason for decline:</p>
          <textarea
            v-model="rejectReason"
            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
            placeholder="Enter rejection reason..."
            rows="4"
          ></textarea>
          <div class="flex gap-3 mt-6">
            <button
              @click="showRejectDialog = false"
              class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded-lg font-medium transition-colors"
            >
              Cancel
            </button>
            <button
              @click="reject"
              :disabled="processing"
              class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
            >
              {{ processing ? 'Processing...' : 'Declined' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

interface Approval {
  id: number
  status: string
  workflowable_type: string
  approver_name: string
  comment: string | null
  created_at: string
  updated_at: string
}

interface Props {
  approval: Approval
}

const props = defineProps<Props>()

const processing = ref(false)
const showRejectDialog = ref(false)
const rejectReason = ref('')

const breadcrumbs = [
  { title: 'Dashboard', href: route('admin.dashboard') },
  { title: 'Approvals', href: route('approvals.index') },
  { title: 'Details' },
]

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const approve = async () => {
  processing.value = true
  router.post(route('approvals.approve', props.approval.id), {}, {
    onFinish: () => {
      processing.value = false
    },
  })
}

const openRejectDialog = () => {
  showRejectDialog.value = true
}

const reject = async () => {
  if (!rejectReason.value.trim()) {
    alert('Please provide a rejection reason')
    return
  }

  processing.value = true
  router.post(
    route('approvals.reject', props.approval.id),
    { comment: rejectReason.value },
    {
      onFinish: () => {
        processing.value = false
        showRejectDialog.value = false
      },
    }
  )
}
</script>
