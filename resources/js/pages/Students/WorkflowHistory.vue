<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface Workflow {
    id: number;
    workflow_type: string;
    description: string;
    status: string;           // pending | in_progress | completed | rejected
    current_step: string;
    approver_name: string | null;
    comment: string | null;
    created_at: string;
    updated_at: string;
    completed_at: string | null;
}

interface Props {
    account_id: string;
    workflows: Workflow[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Students',  href: route('students.index')  },
    { title: `${props.account_id} — Workflow History` },
];

// ── Helpers ──────────────────────────────────────────────────────────────────

const formatDate = (date: string | null): string => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', {
        year:   'numeric',
        month:  'short',
        day:    'numeric',
        hour:   '2-digit',
        minute: '2-digit',
    });
};

const isFinished = (status: string): boolean =>
    status === 'completed' || status === 'rejected';

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        pending:     'Pending',
        in_progress: 'In Progress',
        completed:   'Completed',
        rejected:    'Rejected',
    };
    return labels[status] ?? status;
};

const dotClass = (status: string): string => {
    const map: Record<string, string> = {
        completed:   'bg-green-500',
        rejected:    'bg-red-500',
        in_progress: 'bg-yellow-500',
        pending:     'bg-gray-400',
    };
    return map[status] ?? 'bg-gray-400';
};

const badgeClass = (status: string): string => {
    const map: Record<string, string> = {
        completed:   'bg-green-100 text-green-800',
        rejected:    'bg-red-100 text-red-800',
        in_progress: 'bg-yellow-100 text-yellow-800',
        pending:     'bg-gray-100 text-gray-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="Workflow History" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold">Workflow History</h1>
                <p class="text-gray-500">Approval workflow history for this student</p>
            </div>

            <!-- Timeline -->
            <div class="space-y-4">
                <div v-if="workflows && workflows.length > 0">
                    <div v-for="(workflow, index) in workflows" :key="workflow.id" class="relative">

                        <!-- Connector line between items -->
                        <div
                            v-if="index < workflows.length - 1"
                            class="absolute top-12 left-6 h-12 w-0.5 bg-gray-300"
                        ></div>

                        <div class="flex gap-4">
                            <!-- Status dot -->
                            <div
                                class="z-10 flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full font-bold text-white"
                                :class="dotClass(workflow.status)"
                            >
                                {{ index + 1 }}
                            </div>

                            <!-- Card -->
                            <div class="flex-1 rounded-lg border bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ workflow.workflow_type }}</h3>
                                        <p class="mt-1 text-sm text-gray-600">{{ workflow.description }}</p>
                                        <p class="mt-1 text-xs text-gray-400">
                                            Current step: <span class="font-medium">{{ workflow.current_step }}</span>
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1 text-sm font-semibold capitalize"
                                        :class="badgeClass(workflow.status)"
                                    >
                                        {{ statusLabel(workflow.status) }}
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Started</p>
                                        <p class="font-semibold">{{ formatDate(workflow.created_at) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">
                                            {{ isFinished(workflow.status) ? 'Completed' : 'Last updated' }}
                                        </p>
                                        <p class="font-semibold">
                                            {{ formatDate(workflow.completed_at ?? workflow.updated_at) }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="workflow.approver_name" class="mt-4 border-t pt-4 text-sm">
                                    <p class="text-gray-500">Handled by</p>
                                    <p class="font-semibold">{{ workflow.approver_name }}</p>
                                </div>

                                <div v-if="workflow.comment" class="mt-4 text-sm">
                                    <p class="text-gray-500">Comments</p>
                                    <p class="mt-1 rounded bg-gray-50 p-3">{{ workflow.comment }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="rounded-lg border bg-gray-50 py-12 text-center">
                    <p class="text-lg text-gray-500">No workflow history available</p>
                    <p class="mt-2 text-sm text-gray-400">
                        This student has not been through any approval workflows yet.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>