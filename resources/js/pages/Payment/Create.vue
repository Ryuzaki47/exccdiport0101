<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle, Clock } from 'lucide-vue-next';
import { computed } from 'vue';
const { formatCurrency } = useDataFormatting();

// ─── Types ────────────────────────────────────────────────────────────────────
interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
    percentage: number;
    amount: number;
    balance: number;
    due_date: string | null;
    status: string;
    remarks: string | null;
    paid_date: string | null;
}

interface PendingApprovalPayment {
    id: number;
    reference: string;
    amount: number;
    selected_term_id: number | null;
    term_name: string;
    created_at: string;
}

interface Props {
    studentName: string;
    outstandingBalance: number;
    paymentTerms: PaymentTerm[];
    latestAssessment?: any;
    pendingApprovalPayments: PendingApprovalPayment[];
}

// ─── Props ────────────────────────────────────────────────────────────────────
const props = withDefaults(defineProps<Props>(), {
    paymentTerms: () => [],
    pendingApprovalPayments: () => [],
});

// ─── Navigation ───────────────────────────────────────────────────────────────
const breadcrumbs = [
    { title: 'Dashboard', href: route('student.dashboard') },
    { title: 'My Account', href: route('student.account') },
    { title: 'Make Payment' },
];

// ─── Form ─────────────────────────────────────────────────────────────────────
const form = useForm({
    amount: 0 as number,
    payment_method: 'gcash' as string,
    paid_at: new Date().toISOString().split('T')[0],
    selected_term_id: null as number | null,
});

// ─── Computed helpers ─────────────────────────────────────────────────────────

/** Map term_id → total pending approval amount for that term */
const pendingByTerm = computed<Record<number, number>>(() => {
    const map: Record<number, number> = {};
    props.pendingApprovalPayments.forEach((p) => {
        if (p.selected_term_id !== null) {
            map[p.selected_term_id] = (map[p.selected_term_id] || 0) + p.amount;
        }
    });
    return map;
});

const getPendingForTerm = (termId: number) => pendingByTerm.value[termId] || 0;

const hasPendingPayments = computed(() => props.pendingApprovalPayments.length > 0);

/** Unpaid terms sorted by order, enriched with pending/selectable flags */
const availableTerms = computed(() => {
    const unpaid = props.paymentTerms.filter((t) => t.balance > 0).sort((a, b) => a.term_order - b.term_order);

    return unpaid.map((term, index) => {
        const pendingAmount = getPendingForTerm(term.id);
        const hasPending = pendingAmount > 0;
        return {
            ...term,
            isSelectable: index === 0 && !hasPending,
            hasPending,
            pendingAmount,
        };
    });
});

/** Effective payable balance = balance minus any amount already pending approval */
const effectiveBalance = computed(() => {
    const totalBalance = props.paymentTerms.reduce((s, t) => s + t.balance, 0);
    const totalPending = props.pendingApprovalPayments.reduce((s, p) => s + p.amount, 0);
    return Math.max(0, Math.round((totalBalance - totalPending) * 100) / 100);
});

const selectedTerm = computed(() => (form.selected_term_id ? (availableTerms.value.find((t) => t.id === form.selected_term_id) ?? null) : null));

const selectedTermHasPending = computed(() => form.selected_term_id !== null && getPendingForTerm(form.selected_term_id) > 0);

const canSubmit = computed(
    () =>
        effectiveBalance.value > 0 &&
        form.amount > 0 &&
        form.amount <= effectiveBalance.value &&
        form.selected_term_id !== null &&
        !selectedTermHasPending.value &&
        !form.processing,
);

const amountError = computed(() => {
    const amt = Number(form.amount) || 0;
    if (amt <= 0 && form.amount) return 'Amount must be greater than zero.';
    if (amt > effectiveBalance.value) return `Amount cannot exceed available balance of ${formatCurrency(effectiveBalance.value)}.`;
    if (selectedTerm.value && amt > selectedTerm.value.balance)
        return `Amount cannot exceed selected term balance of ${formatCurrency(selectedTerm.value.balance)}.`;
    return '';
});

const disabledReason = computed(() => {
    if (props.outstandingBalance <= 0) return 'No outstanding balance.';
    if (effectiveBalance.value <= 0 && hasPendingPayments.value) return 'Your full balance is currently awaiting accounting approval.';
    if (!form.selected_term_id) return 'Select a payment term.';
    const pending = getPendingForTerm(form.selected_term_id);
    if (pending > 0) return `₱${formatCurrency(pending)} for this term is awaiting approval. Wait before submitting another payment.`;
    if (form.amount <= 0) return 'Enter a payment amount.';
    if (form.amount > effectiveBalance.value) return 'Amount exceeds available balance.';
    return '';
});

const projectedBalance = computed(() => Math.max(0, effectiveBalance.value - (Number(form.amount) || 0)));

// ─── Helpers ──────────────────────────────────────────────────────────────────

const formatDate = (date: string) => new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

const isOverdue = (dueDate: string) => {
    const d = new Date(dueDate);
    const today = new Date();
    d.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    return d < today;
};

const getTermStatusClass = (status: string) => {
    const map: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        partial: 'bg-orange-100 text-orange-800',
        paid: 'bg-green-100 text-green-800',
        overdue: 'bg-red-100 text-red-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-800';
};

// ─── Submit ───────────────────────────────────────────────────────────────────
const submitPayment = () => {
    if (!canSubmit.value) {
        if (!form.selected_term_id) form.setError('selected_term_id', 'Please select a payment term.');
        if (!form.amount) form.setError('amount', 'Please enter an amount.');
        return;
    }

    form.post(route('account.pay-now'), {
        preserveScroll: true,
        onSuccess: () => router.visit(route('student.account', { tab: 'history' })),
    });
};
</script>

<template>
    <Head title="Make Payment" />

    <AppLayout>
        <div class="mx-auto max-w-2xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Make Payment</h1>
                <p class="text-gray-500">Submit your payment for accounting review</p>
            </div>

            <!-- Balance Summary -->
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium tracking-wider text-gray-500 uppercase">Outstanding Balance</p>
                    <p class="mt-1 text-3xl font-bold" :class="outstandingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                        {{ formatCurrency(outstandingBalance) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium tracking-wider text-gray-500 uppercase">Available to Pay</p>
                    <p class="mt-1 text-3xl font-bold text-indigo-600">
                        {{ formatCurrency(effectiveBalance) }}
                    </p>
                    <p v-if="hasPendingPayments" class="mt-1 text-xs text-amber-600">
                        ({{ formatCurrency(outstandingBalance - effectiveBalance) }} awaiting approval)
                    </p>
                </div>
            </div>

            <!-- Fully Paid -->
            <div v-if="outstandingBalance <= 0" class="rounded-lg border border-green-200 bg-green-50 p-4">
                <div class="flex items-center gap-2">
                    <CheckCircle class="h-5 w-5 text-green-600" />
                    <p class="font-semibold text-green-800">Account fully paid!</p>
                </div>
                <p class="mt-1 text-sm text-green-700">There is no outstanding balance.</p>
            </div>

            <!-- Pending Approval Banner -->
            <div v-if="hasPendingPayments" class="rounded-lg border border-amber-300 bg-amber-50 p-4">
                <div class="mb-2 flex items-center gap-2">
                    <Clock class="h-5 w-5 text-amber-600" />
                    <p class="font-semibold text-amber-900">Payments Awaiting Approval ({{ pendingApprovalPayments.length }})</p>
                </div>
                <div class="space-y-1.5">
                    <div
                        v-for="p in pendingApprovalPayments"
                        :key="p.id"
                        class="flex items-center justify-between rounded border border-amber-200 bg-white px-3 py-2 text-sm"
                    >
                        <div>
                            <p class="font-medium text-gray-800">{{ p.term_name }}</p>
                            <p class="text-xs text-gray-500">{{ p.reference }} · {{ formatDate(p.created_at) }}</p>
                        </div>
                        <span class="font-semibold text-amber-700">{{ formatCurrency(p.amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <form v-if="outstandingBalance > 0" @submit.prevent="submitPayment" class="space-y-5 rounded-xl border bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Payment Details</h2>

                <!-- Student Name (read-only) -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Student Name</label>
                    <input type="text" :value="studentName" disabled class="w-full rounded-lg border bg-gray-50 px-4 py-2 text-gray-600" />
                </div>

                <!-- Select Term -->
                <div>
                    <label for="selected_term_id" class="mb-1 block text-sm font-medium text-gray-700">
                        Payment Term <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="selected_term_id"
                        v-model.number="form.selected_term_id"
                        required
                        :disabled="effectiveBalance <= 0 || availableTerms.length === 0"
                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                    >
                        <option :value="null">-- Choose a payment term --</option>
                        <option v-for="term in availableTerms" :key="term.id" :value="term.id" :disabled="!term.isSelectable">
                            {{ term.term_name }}
                            {{
                                term.hasPending ? `— ⏳ ₱${formatCurrency(term.pendingAmount)} pending` : `— ₱${formatCurrency(term.balance)} balance`
                            }}
                            {{ !term.isSelectable && !term.hasPending ? ' (pay earlier terms first)' : '' }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Only the first unpaid term is available. Overpayments carry over automatically.</p>
                    <p v-if="form.errors.selected_term_id" class="mt-1 text-sm text-red-600">{{ form.errors.selected_term_id }}</p>
                </div>

                <!-- Selected Term Detail -->
                <div v-if="selectedTerm" class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="font-semibold text-gray-900">{{ selectedTerm.term_name }}</p>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="getTermStatusClass(selectedTerm.status)">
                            {{ selectedTerm.status }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 border-t border-blue-200 pt-2">
                        <div>
                            <p class="text-xs text-gray-500">Balance Due</p>
                            <p class="font-semibold text-blue-700">{{ formatCurrency(selectedTerm.balance) }}</p>
                        </div>
                        <div v-if="selectedTerm.due_date">
                            <p class="text-xs text-gray-500">Due Date</p>
                            <p class="font-semibold" :class="isOverdue(selectedTerm.due_date) ? 'text-red-600' : 'text-gray-700'">
                                {{ formatDate(selectedTerm.due_date) }}
                                <span v-if="isOverdue(selectedTerm.due_date)" class="ml-1 text-xs">⚠️ Overdue</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div>
                    <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">
                        Payment Amount <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute top-2.5 left-3 text-gray-500">₱</span>
                        <input
                            id="amount"
                            v-model.number="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="effectiveBalance"
                            required
                            :disabled="effectiveBalance <= 0"
                            placeholder="0.00"
                            class="w-full rounded-lg border py-2 pr-4 pl-8 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                            :class="{ 'border-red-400': amountError }"
                        />
                    </div>
                    <p v-if="amountError" class="mt-1 text-sm text-red-600">{{ amountError }}</p>
                    <p v-else class="mt-1 text-xs text-gray-500">Maximum payable: {{ formatCurrency(effectiveBalance) }}</p>
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>

                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="mb-1 block text-sm font-medium text-gray-700">
                        Payment Method <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="payment_method"
                        v-model="form.payment_method"
                        required
                        :disabled="effectiveBalance <= 0"
                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                    >
                        <option value="gcash">GCash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                    </select>
                    <p v-if="form.errors.payment_method" class="mt-1 text-sm text-red-600">{{ form.errors.payment_method }}</p>
                </div>

                <!-- Payment Date -->
                <div>
                    <label for="paid_at" class="mb-1 block text-sm font-medium text-gray-700">
                        Payment Date <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="paid_at"
                        v-model="form.paid_at"
                        type="date"
                        required
                        :disabled="effectiveBalance <= 0"
                        class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-100"
                    />
                    <p v-if="form.errors.paid_at" class="mt-1 text-sm text-red-600">{{ form.errors.paid_at }}</p>
                </div>

                <!-- Payment Preview -->
                <div v-if="form.amount > 0 && !amountError" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm">
                    <p class="mb-2 text-xs font-semibold tracking-wider text-gray-500 uppercase">Payment Preview</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-xs text-gray-500">Available Balance</p>
                            <p class="font-semibold text-red-600">{{ formatCurrency(effectiveBalance) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Payment Amount</p>
                            <p class="font-semibold text-blue-600">− {{ formatCurrency(form.amount) }}</p>
                        </div>
                        <div class="col-span-2 flex justify-between border-t border-green-200 pt-2">
                            <span class="text-xs font-medium text-gray-500">Balance After Payment</span>
                            <span class="font-bold" :class="projectedBalance > 0 ? 'text-red-600' : 'text-green-600'">
                                {{ formatCurrency(projectedBalance) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Disabled Reason -->
                <div
                    v-if="disabledReason && effectiveBalance > 0"
                    class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                >
                    <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>{{ disabledReason }}</span>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 border-t pt-4">
                    <button
                        type="button"
                        @click="router.visit(route('student.account'))"
                        class="flex-1 rounded-lg border bg-white px-6 py-2.5 font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="flex-1 rounded-lg bg-indigo-600 px-6 py-2.5 font-medium text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span v-if="form.processing">Submitting…</span>
                        <span v-else-if="outstandingBalance <= 0">No Balance to Pay</span>
                        <span v-else-if="selectedTermHasPending">⏳ Awaiting Approval</span>
                        <span v-else>Submit Payment</span>
                    </button>
                </div>
            </form>

            <!-- Info note -->
            <div class="rounded border-l-4 border-blue-500 bg-blue-50 p-4 text-sm text-blue-800">
                <strong>Note:</strong> After submission, your payment will be reviewed by the accounting department. You will receive a confirmation
                notification once it has been verified.
            </div>
        </div>
    </AppLayout>
</template>
