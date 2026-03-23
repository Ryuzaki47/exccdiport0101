<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDataFormatting } from '@/composables/useDataFormatting';
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
    status:   props.filters.status   ?? '',
    year:     props.filters.year     ?? '',
    semester: props.filters.semester ?? '',
});

const searchQuery        = ref('');
const showRejectDialog   = ref(false);
const selectedApprovalId = ref<number | null>(null);
const approveProcessing  = ref<number | null>(null); // tracks which approval is being approved

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

const termOptions = ['Upon Registration', 'Prelim', 'Midterm', 'Semi-Final', 'Final'];

const formatDate = (date: string) =>
    new Date(date).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

const formatMethod = (method: string) =>
    ({
        cash:          'Cash',
        gcash:         'GCash',
        bank_transfer: 'Bank Transfer',
        credit_card:   'Credit Card',
        debit_card:    'Debit Card',
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
        result = result.filter(
            (a) => String(a.workflow_instance.metadata?.year) === filters.value.year,
        );
    }

    if (filters.value.semester) {
        result = result.filter((a) => {
            const termName =
                a.workflow_instance.workflowable.meta?.term_name ??
                a.workflow_instance.workflowable.type;
            return termName === filters.value.semester;
        });
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter((a) => {
            const studentName = getStudentName(a).toLowerCase();
            const ref         = a.workflow_instance.workflowable.reference.toLowerCase();
            const accountId   = a.workflow_instance.workflowable.user?.account_id?.toLowerCase() ?? '';
            return studentName.includes(query) || ref.includes(query) || accountId.includes(query);
        });
    }

    return result;
});

// Push only non-empty filter values to the URL so the query string stays clean.
const applyFilter = () => {
    const params: Record<string, string> = {};
    if (filters.value.status)   params.status   = filters.value.status;
    if (filters.value.year)     params.year      = String(filters.value.year);
    if (filters.value.semester) params.semester  = filters.value.semester;
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

const openRejectDialog = (approvalId: number) => {
    selectedApprovalId.value = approvalId;
    rejectForm.reset();
    showRejectDialog.value = true;
};

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
    <Head title="Payment Approvals" />
    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div>
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold">Payment Approvals</h1>
                            <span
                                v-if="pendingCount > 0"
                                class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-sm font-semibold text-yellow-800"
                            >
                                {{ pendingCount }} pending
                            </span>
                        </div>
                        <p class="text-gray-500">Review and verify student payment submissions</p>
                    </div>
                    <button
                        @click="refreshApprovals"
                        title="Refresh approvals"
                        class="rounded-lg border border-gray-300 bg-white p-2 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
                    >
                        <RotateCcw :size="20" />
                    </button>
                </div>

                <!-- Filters Row -->
                <div class="flex flex-col gap-4 md:flex-row">
                    <div class="relative flex-1">
                        <Search class="absolute top-3 left-3 text-gray-400" :size="18" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by student name, ID, or reference..."
                            class="w-full rounded-lg border border-gray-300 py-2 pr-4 pl-10 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Year Dropdown -->
                    <select
                        v-model="filters.year"
                        @change="applyFilter"
                        class="min-w-[140px] rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Years</option>
                        <option v-for="year in uniqueYears" :key="year" :value="String(year)">
                            {{ year }}
                        </option>
                    </select>

                    <!-- Term Dropdown -->
                    <select
                        v-model="filters.semester"
                        @change="applyFilter"
                        class="min-w-[180px] rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Terms</option>
                        <option v-for="term in termOptions" :key="term" :value="term">
                            {{ term }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="filters.status"
                        @change="applyFilter"
                        class="min-w-[120px] rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 focus:border-transparent focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="filteredApprovals.length === 0" class="py-16 text-center text-gray-400">
                {{ searchQuery || filters.year || filters.semester || filters.status
                    ? 'No approvals match your filters.'
                    : 'No approvals found.' }}
            </div>

            <!-- Approvals list -->
            <div v-else class="space-y-4">
                <div
                    v-for="approval in filteredApprovals"
                    :key="approval.id"
                    class="rounded-xl border bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-semibold">{{ getStudentName(approval) }}</h3>
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': approval.status === 'pending',
                                        'bg-green-100 text-green-800':  approval.status === 'approved',
                                        'bg-red-100 text-red-800':      approval.status === 'rejected',
                                    }"
                                >{{ approval.status }}</span>
                            </div>
                            <p class="text-sm text-gray-500">
                                Ref: <span class="font-mono">{{ approval.workflow_instance.workflowable.reference }}</span>
                            </p>
                        </div>
                        <p class="whitespace-nowrap text-2xl font-bold text-blue-700">
                            {{ formatCurrency(approval.workflow_instance.metadata?.amount ?? approval.workflow_instance.workflowable.amount) }}
                        </p>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-4">
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Term</p>
                            <p>{{ approval.workflow_instance.workflowable.meta?.term_name ?? approval.workflow_instance.workflowable.type ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Method</p>
                            <p>{{ formatMethod(approval.workflow_instance.workflowable.payment_channel ?? '') }}</p>
                        </div>
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Account ID</p>
                            <p>{{ approval.workflow_instance.workflowable.user?.account_id ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs tracking-wide text-gray-400 uppercase">Submitted</p>
                            <p>{{ formatDate(approval.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Approve / Decline buttons — only visible on pending approvals -->
                    <div v-if="approval.status === 'pending'" class="mt-4 flex flex-wrap gap-3">
                        <button
                            @click="approve(approval.id)"
                            :disabled="approveProcessing === approval.id || approveForm.processing"
                            class="group inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-green-500 to-green-600 px-4 py-2 font-semibold text-white shadow-md transition-all duration-200 hover:scale-105 hover:from-green-600 hover:to-green-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-green-300 disabled:scale-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <CheckCircle2 :size="18" class="transition-transform group-hover:scale-110" />
                            <span>{{ approveProcessing === approval.id ? 'Approving…' : 'Approve' }}</span>
                        </button>
                        <button
                            @click="openRejectDialog(approval.id)"
                            :disabled="approveProcessing === approval.id || approveForm.processing"
                            class="group inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-red-500 to-red-600 px-4 py-2 font-semibold text-white shadow-md transition-all duration-200 hover:scale-105 hover:from-red-600 hover:to-red-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-red-300 disabled:scale-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <XCircle :size="18" class="transition-transform group-hover:scale-110" />
                            <span>Decline</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Decline Dialog -->
        <Dialog v-model:open="showRejectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Decline Payment</DialogTitle>
                    <DialogDescription>Provide a reason. The student will be notified.</DialogDescription>
                </DialogHeader>
                <div class="mt-2 space-y-4">
                    <textarea
                        v-model="rejectForm.comments"
                        class="min-h-[100px] w-full rounded-lg border p-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"
                        placeholder="Enter rejection reason (required)..."
                    />
                    <p v-if="rejectForm.errors.comments" class="text-sm text-red-500">
                        {{ rejectForm.errors.comments }}
                    </p>
                    <div class="flex justify-end gap-3">
                        <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                        <Button
                            variant="destructive"
                            :disabled="rejectForm.processing || !rejectForm.comments.trim()"
                            @click="submitRejection"
                        >
                            <span v-if="rejectForm.processing">Declining…</span>
                            <span v-else>Confirm Decline</span>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>