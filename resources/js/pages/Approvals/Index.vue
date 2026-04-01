<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, RotateCcw, Search, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface WorkflowMeta {
    transaction_id: number;
    amount: number;
    payment_method: string;
    term_name: string;
    year?: number | string;
    semester?: string;
    student_user_id: number;
    submitted_at: string;
}

interface Approval {
    id: number;
    step_name: string;
    status: 'pending' | 'approved' | 'rejected';
    comments: string | null;
    created_at: string;
    workflow_instance: {
        metadata: WorkflowMeta;
        workflow: { name: string };
        workflowable: {
            reference: string;
            amount: number;
            payment_channel?: string;
            meta?: { term_name?: string };
            type?: string;
            user?: { first_name: string; last_name: string; account_id: string };
        };
    };
}

const props = defineProps<{
    approvals: { data: Approval[]; links: any[] };
    filters: { status?: string; year?: string; semester?: string };
}>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('accounting.dashboard') },
    { title: 'Payment Approvals', href: route('approvals.index') },
];

const { formatCurrency } = useDataFormatting();

// Initialise all three filter keys with explicit defaults so none are ever `undefined`.
const filters = ref({
    status: props.filters.status ?? '',
    year: props.filters.year ?? '',
    semester: props.filters.semester ?? '',
});

const searchQuery = ref('');
const showRejectDialog = ref(false);
const selectedApprovalId = ref<number | null>(null);
const approveProcessing = ref<number | null>(null); // tracks which approval is being approved

const rejectForm = useForm({ comments: '' });

// Extract unique years from the current page of approvals for the year dropdown.
const uniqueYears = computed(() => {
    const years = new Set<string | number>();
    props.approvals.data.forEach((approval) => {
        const year = approval.workflow_instance.metadata?.year;
        if (year) years.add(year);
    });
    return Array.from(years).sort((a, b) => {
        const aNum = typeof a === 'string' ? parseInt(a) : a;
        const bNum = typeof b === 'string' ? parseInt(b) : b;
        return bNum - aNum;
    });
});

const pendingCount = computed(() => props.approvals.data.filter((a) => a.status === 'pending').length);

const approvedCount = computed(() => props.approvals.data.filter((a) => a.status === 'approved').length);

const rejectedCount = computed(() => props.approvals.data.filter((a) => a.status === 'rejected').length);

const filterStatus = ref<'all' | 'pending' | 'approved' | 'rejected'>('all');

const filterOptions = ['all', 'pending', 'approved', 'rejected'] as const;

const termOptions = ['Upon Registration', 'Prelim', 'Midterm', 'Semi-Final', 'Final'];

const formatDate = (date: string) => new Date(date).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

const formatMethod = (method: string) =>
    ({
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
        credit_card: 'Credit Card',
        debit_card: 'Debit Card',
    })[method] ?? method;

const getStudentName = (approval: Approval) => {
    const w = approval.workflow_instance.workflowable;
    if (w.user) return `${w.user.last_name}, ${w.user.first_name}`;
    return 'Unknown Student';
};

// Client-side filter on top of server-side status filter.
const filteredApprovals = computed(() => {
    let result = props.approvals.data;

    if (filters.value.year) {
        result = result.filter((a) => String(a.workflow_instance.metadata?.year) === filters.value.year);
    }

    if (filters.value.semester) {
        result = result.filter((a) => {
            const termName = a.workflow_instance.workflowable.meta?.term_name ?? a.workflow_instance.workflowable.type;
            return termName === filters.value.semester;
        });
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter((a) => {
            const studentName = getStudentName(a).toLowerCase();
            const ref = a.workflow_instance.workflowable.reference.toLowerCase();
            const accountId = a.workflow_instance.workflowable.user?.account_id?.toLowerCase() ?? '';
            return studentName.includes(query) || ref.includes(query) || accountId.includes(query);
        });
    }

    return result;
});

// Flatten approval data for easy template access
const approvalMetadata = computed(() => {
    const map = new Map<number, any>();
    props.approvals.data.forEach((approval) => {
        const w = approval.workflow_instance.workflowable;
        map.set(approval.id, {
            reference: w.reference,
            student_name: getStudentName(approval),
            account_id: w.user?.account_id ?? 'N/A',
            term_name: w.meta?.term_name ?? w.type ?? 'N/A',
            payment_method: w.payment_channel ?? 'N/A',
            amount: w.amount,
        });
    });
    return map;
});

// Push only non-empty filter values to the URL so the query string stays clean.
const applyFilter = () => {
    const params: Record<string, string> = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.year) params.year = String(filters.value.year);
    if (filters.value.semester) params.semester = filters.value.semester;
    router.get(route('approvals.index'), params, { preserveState: true, replace: true });
};

// Approve — uses useForm so Inertia error handling works correctly.
// Errors from the controller (flash.error) are caught by FlashBanner in AppLayout.
const approveForm = useForm({});

const approve = (approvalId: number) => {
    approveProcessing.value = approvalId;
    approveForm.post(route('approvals.approve', approvalId), {
        preserveScroll: true,
        onFinish: () => {
            approveProcessing.value = null;
        },
    });
};

const approvePayment = (approvalId: number) => approve(approvalId);

const openRejectDialog = (approvalId: number) => {
    selectedApprovalId.value = approvalId;
    rejectForm.reset();
    showRejectDialog.value = true;
};

const rejectPayment = (approvalId: number) => openRejectDialog(approvalId);

const submitRejection = () => {
    if (!selectedApprovalId.value) return;
    rejectForm.post(route('approvals.reject', selectedApprovalId.value), {
        onSuccess: () => {
            showRejectDialog.value = false;
        },
    });
};

const refreshApprovals = () => {
    router.reload();
};
</script>

<template>
    <AppLayout>
        <Head title="Payment Approvals" />

        <div class="w-full space-y-5 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Page Header -->
            <div class="ccdi-page-header">
                <div>
                    <h1 class="ccdi-section-title">Payment Approvals</h1>
                    <p class="ccdi-section-desc">Review and approve student payment submissions</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div class="ccdi-stat-card">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100">
                        <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Pending</p>
                        <p class="text-xl font-bold text-amber-600">{{ pendingCount }}</p>
                        <p class="text-xs text-muted-foreground">Awaiting review</p>
                    </div>
                </div>
                <div class="ccdi-stat-card">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Approved</p>
                        <p class="text-xl font-bold text-emerald-600">{{ approvedCount }}</p>
                        <p class="text-xs text-muted-foreground">This period</p>
                    </div>
                </div>
                <div class="ccdi-stat-card">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-red-100">
                        <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">Rejected</p>
                        <p class="text-xl font-bold text-red-600">{{ rejectedCount }}</p>
                        <p class="text-xs text-muted-foreground">This period</p>
                    </div>
                </div>
            </div>

            <!-- Filter tabs -->
            <div class="flex gap-1 rounded-xl border border-border bg-muted/30 p-1 w-fit">
                <button
                    v-for="f in filterOptions"
                    :key="f"
                    @click="filterStatus = f"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium capitalize transition-all"
                    :class="filterStatus === f ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                >
                    {{ f }}
                    <span v-if="f === 'pending' && pendingCount > 0" class="ml-1.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">{{ pendingCount }}</span>
                </button>
            </div>

            <!-- Approvals Table -->
            <div class="ccdi-card overflow-hidden">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reference</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Payment Term</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Method</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Amount</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">Submitted</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <tr v-for="approval in filteredApprovals" :key="approval.id" class="transition-colors hover:bg-muted/30">
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs text-blue-600">{{ approvalMetadata.get(approval.id)?.reference }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                                        {{ (approvalMetadata.get(approval.id)?.student_name || 'S').charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-foreground">{{ approvalMetadata.get(approval.id)?.student_name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ approvalMetadata.get(approval.id)?.account_id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-muted-foreground">{{ approvalMetadata.get(approval.id)?.term_name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="ccdi-badge-blue">{{ approvalMetadata.get(approval.id)?.payment_method }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-sm font-semibold text-emerald-600">₱{{ Number(approvalMetadata.get(approval.id)?.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-sm text-muted-foreground">{{ formatDate(approval.created_at) }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span :class="approval.status === 'pending' ? 'ccdi-badge-yellow' : approval.status === 'approved' ? 'ccdi-badge-green' : 'ccdi-badge-red'">
                                    {{ approval.status === 'pending' ? 'Pending' : approval.status === 'approved' ? 'Approved' : 'Rejected' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Link :href="route('approvals.show', approval.id)" class="rounded-lg border border-border bg-card p-1.5 text-muted-foreground transition-all hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700" title="View details">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </Link>
                                    <template v-if="approval.status === 'pending'">
                                        <button @click="approvePayment(approval.id)" class="rounded-lg border border-emerald-300 bg-emerald-50 p-1.5 text-emerald-700 transition-all hover:bg-emerald-100" title="Approve">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        </button>
                                        <button @click="rejectPayment(approval.id)" class="rounded-lg border border-red-300 bg-red-50 p-1.5 text-red-700 transition-all hover:bg-red-100" title="Reject">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty state -->
                <div v-if="!filteredApprovals.length" class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                        <svg class="h-6 w-6 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <p class="text-base font-semibold text-foreground">No {{ filterStatus === 'all' ? '' : filterStatus }} approvals</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ filterStatus === 'pending' ? 'All payments are reviewed.' : 'Nothing to show here.' }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
