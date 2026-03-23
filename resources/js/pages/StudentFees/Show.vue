<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertCircle, ArrowLeft, BookOpen, CheckCircle2, ChevronDown,
    CreditCard, Download, FlaskConical, Plus, Receipt,
    TrendingDown, TrendingUp,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useDataFormatting } from '@/composables/useDataFormatting';

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
    remarks?: string;
}

interface FeeBreakdownItem {
    category: string;
    name: string;
    code?: string;
    units?: number;
    amount: number;
    subject_id?: number;
}

interface Assessment {
    id: number;
    course: string | null;
    semester: string;
    school_year: string;
    year_level: string;
    total_assessment: number;
    tuition_fee: number;
    other_fees: number;
    fee_breakdown: FeeBreakdownItem[];
    paymentTerms?: PaymentTerm[];
}

interface Props {
    student: any;
    assessment: any;
    allAssessments: Assessment[];
    transactions: any[];
    payments: any[];
    feeBreakdown: Array<{ category: string; total: number; items: number }>;
    backUrl: string;
    // NEW — enrolledSubjectsByAssessment[assessmentId] = subjectId[]
    enrolledSubjectsByAssessment: Record<number, number[]>;
}

const props = defineProps<Props>();

// ─── Assessment selector — declared first so all computed below can safely use
const { formatCurrency } = useDataFormatting();

// selectedAssessment.value without a forward-reference issue. ──────────────────

const selectedAssessmentId = ref<number | null>(props.assessment?.id ?? null);

const selectedAssessment = computed(() => {
    if (!selectedAssessmentId.value) return props.assessment;
    return props.allAssessments.find((a) => a.id === selectedAssessmentId.value) ?? props.assessment;
});

const exportUrl = computed(() => {
    const base = route('student-fees.export-pdf', props.student.id);
    return selectedAssessmentId.value ? `${base}?assessment_id=${selectedAssessmentId.value}` : base;
});

// ─── Balance ──────────────────────────────────────────────────────────────────

const remainingBalance = computed(() => {
    // Prefer the currently selected assessment's terms (now included in allAssessments).
    // Falls back to the default latest assessment terms, then account balance.
    const terms: PaymentTerm[] =
        (selectedAssessment.value as any)?.paymentTerms
        ?? props.assessment?.paymentTerms
        ?? [];
    if (terms.length > 0) {
        const termsTotal = terms.reduce((sum, t) => sum + parseFloat(String(t.balance)), 0);
        if (termsTotal > 0) return Math.round(termsTotal * 100) / 100;
    }
    // Fallback: account-level balance (covers edge cases with no term data)
    const accountBal = parseFloat(String(props.student.account?.balance ?? 0));
    return Math.max(0, accountBal);
});

const totalAssessment = computed(() => parseFloat(String(selectedAssessment.value?.total_assessment ?? props.assessment?.total_assessment ?? 0)));
const totalPaid       = computed(() => Math.max(0, totalAssessment.value - remainingBalance.value));

const paymentTimingStatus = computed((): 'behind' | 'on_track' | 'paid' => {
    const terms: PaymentTerm[] =
        (selectedAssessment.value as any)?.paymentTerms
        ?? props.assessment?.paymentTerms
        ?? [];
    if (terms.length === 0) return 'behind';
    if (remainingBalance.value === 0) return 'paid';
    const sorted = [...terms].sort((a, b) => a.term_order - b.term_order);
    const first  = sorted[0];
    if (first.status === 'pending' && parseFloat(String(first.balance)) >= parseFloat(String(first.amount)) * 0.99) return 'behind';
    return 'on_track';
});

const balanceCardConfig = computed(() => {
    switch (paymentTimingStatus.value) {
        case 'paid': return {
            bg: 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-200',
            iconBg: 'bg-green-100', icon: CheckCircle2, iconColor: 'text-green-600',
            labelColor: 'text-green-700', amountColor: 'text-green-700',
            badge: { label: 'Fully Paid', cls: 'bg-green-500 text-white' },
        };
        case 'on_track': return {
            bg: 'bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200',
            iconBg: 'bg-blue-100', icon: TrendingUp, iconColor: 'text-blue-600',
            labelColor: 'text-blue-700', amountColor: 'text-blue-700',
            badge: { label: 'On Track', cls: 'bg-blue-500 text-white' },
        };
        default: return {
            bg: 'bg-gradient-to-r from-red-50 to-rose-50 border-red-200',
            iconBg: 'bg-red-100', icon: TrendingDown, iconColor: 'text-red-600',
            labelColor: 'text-red-700', amountColor: 'text-red-700',
            badge: { label: 'Behind Schedule', cls: 'bg-red-500 text-white' },
        };
    }
});

// ─── Payment Terms ─────────────────────────────────────────────────────────────

const availableTermsForPayment = computed(() => {
    const unpaidTerms: PaymentTerm[] = ((selectedAssessment.value as any)?.paymentTerms
        ?? props.assessment?.paymentTerms
        ?? [])
        .filter((t: PaymentTerm) => parseFloat(String(t.balance)) > 0)
        .sort((a: PaymentTerm, b: PaymentTerm) => a.term_order - b.term_order);
    return unpaidTerms.map((term: PaymentTerm, index: number) => ({
        ...term,
        isSelectable: index === 0,
        hasCarryover: term.remarks?.toLowerCase().includes('carried') ?? false,
    }));
});

const allTermsSorted = computed((): PaymentTerm[] => {
    const terms: PaymentTerm[] =
        (selectedAssessment.value as any)?.paymentTerms
        ?? props.assessment?.paymentTerms
        ?? [];
    return [...terms].sort((a, b) => a.term_order - b.term_order);
});

const paidTermsCount = computed(() => allTermsSorted.value.filter((t) => t.status === 'paid').length);

// (assessment selector moved above — see top of script)

// ─── Fee line items ────────────────────────────────────────────────────────────

const feeLineItems = computed(() => {
    const labelMap: Record<string, string> = {
        Tuition: 'Tuition Fee', Miscellaneous: 'Miscellaneous Fee',
        Laboratory: 'Laboratory Fee', Library: 'Library Fee',
        Athletic: 'Athletic Fee', Registration: 'Registration Fee',
    };
    const breakdown: Array<{ category: string; total: number; items: number }> = [];
    const selectedAssess = selectedAssessment.value as any;
    if (!selectedAssess) return [];
    if (selectedAssess.tuition_fee > 0) breakdown.push({ category: 'Tuition', total: selectedAssess.tuition_fee, items: 1 });
    const storedBreakdown = selectedAssess.fee_breakdown ?? [];
    if (storedBreakdown.length > 0) {
        const grouped: Record<string, any[]> = {};
        for (const item of storedBreakdown) {
            if (item.category === 'Tuition') continue;
            if (!grouped[item.category]) grouped[item.category] = [];
            grouped[item.category].push(item);
        }
        for (const [category, items] of Object.entries(grouped)) {
            breakdown.push({ category, total: parseFloat(items.reduce((s: number, i: any) => s + parseFloat(String(i.amount)), 0).toFixed(2)), items: items.length });
        }
    } else if (selectedAssess.other_fees > 0) {
        breakdown.push({ category: 'Miscellaneous', total: selectedAssess.other_fees, items: 1 });
    }
    return breakdown.map((item) => ({ ...item, displayLabel: labelMap[item.category] ?? item.category }));
});

// ─── Transaction history ───────────────────────────────────────────────────────

interface TxGroup {
    key: string; transactions: any[];
    totalCharges: number; totalPaid: number; balance: number;
}

const filteredTransactions = computed(() => {
    const paymentsOnly = props.transactions.filter((t: any) => t.kind === 'payment');
    if (!selectedAssessmentId.value || !selectedAssessment.value) return paymentsOnly;
    const assessment = selectedAssessment.value;
    return paymentsOnly.filter((t: any) => {
        const startYear = parseInt(String(assessment.school_year?.split('-')[0] ?? ''), 10);
        return parseInt(String(t.year), 10) === startYear &&
               String(t.semester).trim() === String(assessment.semester).trim();
    });
});

const transactionsByTerm = computed((): TxGroup[] => {
    const groups: Record<string, any[]> = {};
    for (const t of filteredTransactions.value) {
        let key: string;
        if (t.year && t.semester) {
            const startYear = parseInt(String(t.year), 10);
            key = `${isNaN(startYear) ? String(t.year) : `${startYear}-${startYear + 1}`} ${t.semester}`;
        } else key = 'Other';
        if (!groups[key]) groups[key] = [];
        groups[key].push(t);
    }
    const assessmentTotal = parseFloat(String(selectedAssessment.value?.total_assessment ?? props.assessment?.total_assessment ?? 0));
    return Object.entries(groups)
        .map(([key, txns]) => {
            const totalPaidAmt = txns.filter((t) => t.kind === 'payment' && t.status === 'paid').reduce((s, t) => s + parseFloat(t.amount), 0);
            return { key, transactions: txns, totalCharges: assessmentTotal, totalPaid: totalPaidAmt, balance: assessmentTotal - totalPaidAmt };
        })
        .sort((a, b) => parseInt(a.key.split('-')[0] ?? '0', 10) - parseInt(b.key.split('-')[0] ?? '0', 10) > 0 ? -1 : 1);
});

const expandedTerms = ref<Record<string, boolean>>({});
const toggleTerm = (key: string) => { expandedTerms.value[key] = !expandedTerms.value[key]; };

const currentAssessmentTermKey = computed<string | null>(() => {
    if (!selectedAssessment.value?.school_year || !selectedAssessment.value?.semester) return null;
    return `${selectedAssessment.value.school_year} ${selectedAssessment.value.semester}`;
});

// ─── Enrolled Subjects Accordion ──────────────────────────────────────────────
//
// Builds a grouped structure for the accordion from allAssessments[].fee_breakdown.
// Each term panel shows only Tuition + Laboratory line items (subject rows).
// Miscellaneous fixed-fee rows are intentionally excluded — they are not per-subject.
//
// enrolledSubjectIds — flat Set of subject IDs that have a student_enrollments
// record with status='enrolled' for the current assessment's term. Used to render
// the green ✓ Enrolled badge vs a grey ○ Not Confirmed badge.

const enrolledSubjectsOpen = ref(false);
const expandedSubjectTerms = ref<Set<number>>(new Set());

function toggleSubjectTerm(assessmentId: number) {
    if (expandedSubjectTerms.value.has(assessmentId)) {
        expandedSubjectTerms.value.delete(assessmentId);
    } else {
        expandedSubjectTerms.value.add(assessmentId);
    }
}

// Build one panel per assessment that has subject fee_breakdown rows
const enrolledSubjectTerms = computed(() => {
    return props.allAssessments
        .filter((a) => a.fee_breakdown && a.fee_breakdown.length > 0)
        .map((a) => {
            // Subject rows only — Tuition and Laboratory categories have subject_id
            const subjectRows = a.fee_breakdown.filter(
                (item) => item.category === 'Tuition' || item.category === 'Laboratory'
            );

            // Group: one entry per subject (Tuition row + optional Lab row merged)
            const subjectMap: Record<number, {
                subject_id: number;
                code: string;
                name: string;
                units: number;
                tuitionAmount: number;
                labAmount: number;
                hasLab: boolean;
                isEnrolled: boolean;
            }> = {};

            const enrolledIds = new Set(props.enrolledSubjectsByAssessment[a.id] ?? []);

            for (const row of subjectRows) {
                const sid = row.subject_id;
                if (sid === undefined) continue;

                if (!subjectMap[sid]) {
                    subjectMap[sid] = {
                        subject_id:    sid,
                        code:          row.code ?? '—',
                        name:          row.name,
                        units:         row.units ?? 0,
                        tuitionAmount: 0,
                        labAmount:     0,
                        hasLab:        false,
                        isEnrolled:    enrolledIds.has(sid),
                    };
                }

                if (row.category === 'Tuition') {
                    subjectMap[sid].tuitionAmount = parseFloat(String(row.amount));
                    subjectMap[sid].units         = row.units ?? subjectMap[sid].units;
                    // Preserve the clean subject name from the Tuition row
                    if (!subjectMap[sid].name || subjectMap[sid].name.startsWith('Laboratory')) {
                        subjectMap[sid].name = row.name;
                    }
                } else if (row.category === 'Laboratory') {
                    subjectMap[sid].labAmount = parseFloat(String(row.amount));
                    subjectMap[sid].hasLab    = true;
                }
            }

            const subjects = Object.values(subjectMap);
            const totalUnits      = subjects.reduce((s, sub) => s + sub.units, 0);
            const totalTuition    = subjects.reduce((s, sub) => s + sub.tuitionAmount, 0);
            const totalLab        = subjects.reduce((s, sub) => s + sub.labAmount, 0);
            const enrolledCount   = subjects.filter((sub) => sub.isEnrolled).length;

            return {
                assessmentId: a.id,
                label:        `${a.year_level} — ${a.semester}`,
                schoolYear:   a.school_year,
                course:       a.course ?? '—',
                totalUnits,
                totalTuition,
                totalLab,
                subjectCount: subjects.length,
                enrolledCount,
                subjects,
            };
        })
        .filter((panel) => panel.subjects.length > 0);
});

// ─── Payment form ──────────────────────────────────────────────────────────────

const breadcrumbs = [
    { title: 'Dashboard', href: route('admin.dashboard') },
    { title: 'Archives', href: route('students.archive') },
    { title: props.student.name },
];

const PAYMENT_PAGE_SIZE   = 5;
const paymentHistoryLimit = ref(PAYMENT_PAGE_SIZE);

const filteredPayments = computed(() => {
    const selectedId = selectedAssessmentId.value;
    if (!selectedId) return props.payments;
    return props.payments.filter((p: any) => p.assessment_id === selectedId);
});

const visiblePayments = computed(() => filteredPayments.value.slice(0, paymentHistoryLimit.value));
const hasMorePayments  = computed(() => filteredPayments.value.length > paymentHistoryLimit.value);
const loadMorePayments = () => { paymentHistoryLimit.value += PAYMENT_PAGE_SIZE; };

const showPaymentDialog = ref(false);

// ── Payment form — no term_id needed; backend auto-allocates sequentially ─────
const paymentForm = useForm({
    amount:         '',
    payment_method: 'cash',
    assessment_id:  null as number | null,
    payment_date:   new Date().toISOString().split('T')[0],
});

// Simple amount validation — only reject zero/negative. No upper-limit.
const paymentAmountError = computed(() => {
    const amount = parseFloat(paymentForm.amount) || 0;
    if (amount <= 0 && paymentForm.amount) return 'Amount must be greater than zero';
    if (amount > remainingBalance.value) {
        return `Amount cannot exceed the outstanding balance of ${formatCurrency(remainingBalance.value)}`;
    }
    return '';
});

// Projected balance after applying the entered amount (informational display only —
// not used as a submission gate). Floors at 0 so it never shows negative.
const projectedRemainingBalance = computed(() =>
    Math.max(0, remainingBalance.value - (parseFloat(paymentForm.amount) || 0))
);

// ── Allocation preview ────────────────────────────────────────────────────────
// Simulates sequentially applying the entered amount across unpaid terms so
// accounting can see exactly which terms will be touched before submitting.
// Mirrors the server-side allocation logic exactly.
const allocationPreview = computed(() => {
    const entered = parseFloat(paymentForm.amount) || 0;
    if (entered <= 0) return [];

    const unpaid = [...allTermsSorted.value]
        .filter((t) => parseFloat(String(t.balance)) > 0)
        .sort((a, b) => a.term_order - b.term_order);

    let remaining = entered;
    const rows: Array<{ name: string; applied: number; balanceAfter: number; willBePaid: boolean }> = [];

    for (const term of unpaid) {
        if (remaining <= 0) break;
        const bal     = parseFloat(String(term.balance));
        const applied = Math.min(remaining, bal);
        rows.push({
            name:         term.term_name,
            applied,
            balanceAfter: Math.max(0, bal - applied),
            willBePaid:   applied >= bal,
        });
        remaining -= applied;
    }
    return rows;
});

const canSubmitPayment = computed(() =>
    parseFloat(paymentForm.amount) > 0 &&
    !paymentAmountError.value &&
    paymentForm.assessment_id !== null &&
    !paymentForm.processing
);

const getTermStatusConfig = (status: string) => {
    const map: Record<string, { bg: string; text: string; label: string }> = {
        pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Unpaid' },
        partial: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Partial' },
        paid:    { bg: 'bg-green-100',  text: 'text-green-800',  label: 'Paid'   },
        overdue: { bg: 'bg-red-100',    text: 'text-red-800',    label: 'Overdue'},
    };
    return map[status] ?? { bg: 'bg-gray-100', text: 'text-gray-800', label: status };
};

// Seed assessment_id whenever the dialog opens or the selected assessment changes
watch(() => showPaymentDialog.value, (isOpen) => {
    if (isOpen) {
        paymentForm.assessment_id = selectedAssessmentId.value ?? (props.assessment?.id ?? null);
    }
});

watch(() => selectedAssessmentId.value, (newId) => {
    paymentForm.assessment_id = newId ?? (props.assessment?.id ?? null);
    paymentForm.reset();
    paymentForm.clearErrors();
    expandedTerms.value = {};
    if (transactionsByTerm.value.length > 0) {
        const matchKey = currentAssessmentTermKey.value;
        const autoKey  = matchKey && transactionsByTerm.value.some((g) => g.key === matchKey)
            ? matchKey : transactionsByTerm.value[0].key;
        expandedTerms.value[autoKey] = true;
    }
});

const submitPayment = () => {
    if (!canSubmitPayment.value) {
        if (!paymentForm.amount) paymentForm.setError('amount', 'Please enter an amount');
        return;
    }
    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => { showPaymentDialog.value = false; paymentForm.reset(); paymentForm.clearErrors(); },
        onError:   (errors) => console.error('Payment errors:', errors),
    });
};

// ─── Helpers ──────────────────────────────────────────────────────────────────


const formatDate       = (d: string) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
const formatDateShort  = (d: string) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

const toYearRange = (year: string | number | null | undefined): string => {
    if (!year) return '—';
    const y = parseInt(String(year), 10);
    return isNaN(y) ? String(year) : `${y}-${y + 1}`;
};

const getStudentStatusColor = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-green-100 text-green-800', graduated: 'bg-blue-100 text-blue-800',
        dropped: 'bg-red-100 text-red-800',
    };
    return map[status] ?? 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head :title="`Fee Details — ${student.name}`" />

    <AppLayout>
        <div class="w-full space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- ── Header ── -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-4">
                    <Link :href="backUrl">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" /> Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ student.name }}</h1>
                        <p class="mt-0.5 text-sm text-gray-500">
                            {{ student.account_id }} &middot;
                            <span class="font-medium">{{ selectedAssessment?.course || student.course || '—' }}</span>
                            <span v-if="selectedAssessment?.course && selectedAssessment.course !== student.course"
                                  class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                Assessment Course
                            </span>
                            &middot;
                            <span v-if="selectedAssessment?.year_level" class="font-medium text-blue-700">{{ selectedAssessment.year_level }}</span>
                            <span v-else>{{ student.year_level }}</span>
                            &middot;
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold inline-flex ml-2',
                                           student.is_irregular ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700']">
                                {{ student.is_irregular ? 'Irregular' : 'Regular' }}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <select v-if="allAssessments.length > 1"
                            v-model.number="selectedAssessmentId"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            title="Select semester to view">
                        <option v-for="a in allAssessments" :key="a.id" :value="a.id">
                            {{ a.year_level }} — {{ a.semester }} {{ a.school_year }}
                        </option>
                    </select>
                    <a :href="exportUrl" target="_blank">
                        <Button variant="outline" size="sm">
                            <Download class="mr-2 h-4 w-4" /> Export PDF
                        </Button>
                    </a>
                    <Dialog v-model:open="showPaymentDialog">
                        <DialogTrigger as-child>
                            <Button size="sm"><Plus class="mr-2 h-4 w-4" /> Record Payment</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Record Payment</DialogTitle>
                                <DialogDescription>
                                    <div class="space-y-1">
                                        <p>Recording payment for <strong>{{ student.name }}</strong></p>
                                        <p class="text-sm text-gray-500">
                                            {{ selectedAssessment?.year_level }} — {{ selectedAssessment?.semester }}
                                            {{ selectedAssessment?.school_year }}
                                        </p>
                                        <p class="text-base font-semibold text-slate-900">
                                            Outstanding Balance: {{ formatCurrency(remainingBalance) }}
                                        </p>
                                    </div>
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitPayment" class="space-y-4">

                                <!-- Amount — no upper limit; backend allocates automatically -->
                                <div class="space-y-2">
                                    <Label for="amount">Amount *</Label>
                                    <Input
                                        id="amount"
                                        v-model="paymentForm.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        placeholder="0.00"
                                        :class="{ 'border-red-500': paymentAmountError }"
                                    />
                                    <p v-if="paymentAmountError" class="text-sm font-medium text-red-500">{{ paymentAmountError }}</p>
                                    <p v-else class="text-xs text-gray-500">
                                        Enter any amount — payment will be applied sequentially across outstanding terms.
                                    </p>
                                    <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">{{ paymentForm.errors.amount }}</p>
                                </div>

                                <!-- Payment Method -->
                                <div class="space-y-2">
                                    <Label for="payment_method">Payment Method *</Label>
                                    <select
                                        id="payment_method"
                                        v-model="paymentForm.payment_method"
                                        required
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="debit_card">Debit Card</option>
                                    </select>
                                    <p v-if="paymentForm.errors.payment_method" class="text-sm text-red-500">{{ paymentForm.errors.payment_method }}</p>
                                </div>

                                <!-- Payment Date -->
                                <div class="space-y-2">
                                    <Label for="payment_date">Payment Date *</Label>
                                    <Input id="payment_date" v-model="paymentForm.payment_date" type="date" required />
                                    <p v-if="paymentForm.errors.payment_date" class="text-sm text-red-500">{{ paymentForm.errors.payment_date }}</p>
                                </div>

                                <!-- Allocation Preview — shows exactly which terms will be touched -->
                                <div v-if="allocationPreview.length > 0" class="rounded-lg border border-indigo-200 bg-indigo-50 overflow-hidden text-sm">
                                    <div class="flex items-center justify-between border-b border-indigo-200 bg-indigo-100 px-4 py-2">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Allocation Preview</p>
                                        <p class="text-xs text-indigo-600">Applied oldest term first</p>
                                    </div>
                                    <div class="divide-y divide-indigo-100">
                                        <div
                                            v-for="row in allocationPreview"
                                            :key="row.name"
                                            class="flex items-center justify-between px-4 py-2.5"
                                        >
                                            <div class="flex items-center gap-2">
                                                <span
                                                    :class="['inline-flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                                             row.willBePaid ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700']"
                                                >
                                                    {{ row.willBePaid ? '✓' : '~' }}
                                                </span>
                                                <div>
                                                    <p class="font-medium text-gray-900">{{ row.name }}</p>
                                                    <p class="text-xs text-gray-500">
                                                        Balance after: {{ formatCurrency(row.balanceAfter) }}
                                                        <span v-if="row.willBePaid" class="ml-1 font-semibold text-green-600">· Fully paid</span>
                                                        <span v-else class="ml-1 text-amber-600">· Partial</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="font-semibold text-indigo-700">
                                                {{ formatCurrency(row.applied) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Summary row -->
                                    <div class="flex items-center justify-between border-t border-indigo-200 bg-indigo-100 px-4 py-2">
                                        <div>
                                            <p class="text-xs font-semibold text-indigo-800">Total Applied</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-indigo-800">
                                                {{ formatCurrency(parseFloat(paymentForm.amount) || 0) }}
                                            </p>
                                            <p class="text-xs text-indigo-600">
                                                Balance after: {{ formatCurrency(projectedRemainingBalance) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="paymentForm.errors.error" class="text-sm font-medium text-red-600">{{ paymentForm.errors.error }}</p>

                                <DialogFooter>
                                    <Button type="button" variant="outline" @click="showPaymentDialog = false">Cancel</Button>
                                    <Button
                                        type="submit"
                                        :disabled="!canSubmitPayment"
                                        :class="{ 'cursor-not-allowed opacity-50': !canSubmitPayment }"
                                    >
                                        <span v-if="paymentForm.processing">Recording…</span>
                                        <span v-else>Record Payment</span>
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- ── Personal Info ── -->
            <Card>
                <CardHeader><CardTitle>Personal Information</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div><Label class="text-xs text-gray-500">Full Name</Label><p class="mt-0.5 font-medium">{{ student.name }}</p></div>
                        <div><Label class="text-xs text-gray-500">Email</Label><p class="mt-0.5 font-medium">{{ student.email }}</p></div>
                        <div><Label class="text-xs text-gray-500">Birthday</Label><p class="mt-0.5 font-medium">{{ student.birthday ? formatDate(student.birthday) : 'N/A' }}</p></div>
                        <div><Label class="text-xs text-gray-500">Phone</Label><p class="mt-0.5 font-medium">{{ student.phone || 'N/A' }}</p></div>
                        <div><Label class="text-xs text-gray-500">Account ID</Label><p class="mt-0.5 font-medium">{{ student.account_id }}</p></div>
                        <div><Label class="text-xs text-gray-500">Course</Label><p class="mt-0.5 font-medium">{{ student.course }}</p></div>
                        <div><Label class="text-xs text-gray-500">Year Level</Label><p class="mt-0.5 font-medium">{{ assessment?.year_level || student.year_level }}</p></div>
                        <div>
                            <Label class="text-xs text-gray-500">Status</Label>
                            <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-xs font-semibold" :class="getStudentStatusColor(student.status)">{{ student.status }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Fee Breakdown ── -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle>Fee Breakdown</CardTitle>
                            <CardDescription>Assessment for {{ selectedAssessment?.year_level }} — {{ selectedAssessment?.semester }} {{ selectedAssessment?.school_year }}</CardDescription>
                        </div>
                        <div v-if="assessment?.course" class="text-right">
                            <span class="text-xs font-semibold text-gray-600">Course:</span>
                            <span class="ml-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">{{ assessment.course }}</span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <div v-for="item in feeLineItems" :key="item.category"
                             class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <Receipt class="h-4 w-4 text-gray-400" />
                                <span class="text-sm font-medium text-gray-700">{{ item.displayLabel }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(item.total) }}</span>
                        </div>
                        <div v-if="feeLineItems.length === 0" class="py-4 text-center text-sm text-gray-400">No fee items on record</div>
                    </div>

                    <div class="flex items-center justify-between border-t-2 border-gray-200 px-1 pt-2">
                        <span class="font-semibold text-gray-700">Total Assessment</span>
                        <span class="text-lg font-bold text-gray-900">{{ formatCurrency(totalAssessment) }}</span>
                    </div>

                    <div class="space-y-1 px-1">
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>Payment Progress</span>
                            <span>{{ totalAssessment > 0 ? Math.round((totalPaid / totalAssessment) * 100) : 0 }}%</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                            <div class="h-2.5 rounded-full transition-all duration-500"
                                 :class="paymentTimingStatus === 'behind' ? 'bg-red-500' : paymentTimingStatus === 'on_track' ? 'bg-blue-500' : 'bg-green-500'"
                                 :style="{ width: totalAssessment > 0 ? `${Math.min(100, (totalPaid / totalAssessment) * 100)}%` : '0%' }"></div>
                        </div>
                        <div class="flex justify-between pt-0.5 text-xs text-gray-500">
                            <span>Paid: <strong class="text-green-600">{{ formatCurrency(totalPaid) }}</strong></span>
                            <span>Remaining: <strong :class="paymentTimingStatus === 'paid' ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(remainingBalance) }}</strong></span>
                        </div>
                    </div>

                    <div :class="['mt-2 flex items-center gap-4 rounded-xl border-2 p-4', balanceCardConfig.bg]">
                        <div :class="['rounded-xl p-3', balanceCardConfig.iconBg]">
                            <component :is="balanceCardConfig.icon" :class="['h-6 w-6', balanceCardConfig.iconColor]" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm" :class="balanceCardConfig.labelColor">Remaining Balance</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="balanceCardConfig.badge.cls">{{ balanceCardConfig.badge.label }}</span>
                            </div>
                            <p class="mt-0.5 text-3xl font-extrabold" :class="balanceCardConfig.amountColor">{{ formatCurrency(remainingBalance) }}</p>
                            <p v-if="assessment?.paymentTerms?.length" class="mt-1 text-xs" :class="balanceCardConfig.labelColor">
                                {{ paidTermsCount }} of {{ allTermsSorted.length }} terms paid
                            </p>
                        </div>
                    </div>

                    <div v-if="allTermsSorted.length > 0" class="space-y-2 pt-1">
                        <p class="text-xs font-semibold tracking-wider text-gray-500 uppercase">Payment Terms</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-5">
                            <div v-for="term in allTermsSorted" :key="term.id"
                                 :class="['rounded-lg border p-2.5 text-center text-xs transition-all',
                                          term.status === 'paid' ? 'border-green-200 bg-green-50'
                                          : term.status === 'partial' ? 'border-orange-200 bg-orange-50'
                                          : term.status === 'overdue' ? 'border-red-300 bg-red-100'
                                          : 'border-gray-200 bg-gray-50']">
                                <p class="truncate font-semibold text-gray-700">{{ term.term_name }}</p>
                                <p class="mt-0.5 font-bold" :class="term.status === 'paid' ? 'text-green-600' : term.status === 'overdue' ? 'text-red-600' : 'text-gray-800'">
                                    {{ formatCurrency(parseFloat(String(term.balance))) }}
                                </p>
                                <span :class="['mt-1 inline-block rounded-full px-1.5 py-0.5 font-medium', getTermStatusConfig(term.status).bg, getTermStatusConfig(term.status).text]">
                                    {{ getTermStatusConfig(term.status).label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ════════════════════════════════════════════════════════════════
                 ── ENROLLED SUBJECTS ACCORDION ──────────────────────────────
                 Collapsible section showing all subjects per academic term.
                 Hidden by default; expands on user interaction.
                 ════════════════════════════════════════════════════════════════ -->
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

                <!-- Section toggle header -->
                <button type="button"
                        class="flex w-full cursor-pointer items-center justify-between px-6 py-4 transition-colors hover:bg-gray-50 select-none"
                        @click="enrolledSubjectsOpen = !enrolledSubjectsOpen">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-indigo-100 p-2">
                            <BookOpen class="h-4 w-4 text-indigo-600" />
                        </div>
                        <div class="text-left">
                            <p class="font-semibold text-gray-900">Enrolled Subjects</p>
                            <p class="text-xs text-gray-500">
                                Fee derivation by subject per academic term
                                <span v-if="enrolledSubjectTerms.length > 0" class="ml-1 text-indigo-600">
                                    · {{ enrolledSubjectTerms.length }} term{{ enrolledSubjectTerms.length !== 1 ? 's' : '' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span v-if="!enrolledSubjectsOpen && enrolledSubjectTerms.length > 0"
                              class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                            {{ enrolledSubjectTerms.reduce((s, t) => s + t.subjectCount, 0) }} subjects total
                        </span>
                        <ChevronDown class="h-5 w-5 text-gray-400 transition-transform duration-200"
                                     :class="{ 'rotate-180': enrolledSubjectsOpen }" />
                    </div>
                </button>

                <!-- Expanded content -->
                <div v-if="enrolledSubjectsOpen" class="border-t border-gray-100">

                    <!-- Empty state -->
                    <div v-if="enrolledSubjectTerms.length === 0"
                         class="flex flex-col items-center justify-center py-12 text-center text-sm text-gray-400">
                        <BookOpen class="mb-3 h-10 w-10 text-gray-200" />
                        <p class="font-medium">No subject data available</p>
                        <p class="mt-1 text-xs">Subject breakdown appears once an assessment with subjects has been created.</p>
                    </div>

                    <!-- One collapsible panel per assessment term -->
                    <div v-for="(termPanel, idx) in enrolledSubjectTerms" :key="termPanel.assessmentId"
                         :class="['border-gray-100', idx < enrolledSubjectTerms.length - 1 ? 'border-b' : '']">

                        <!-- Term accordion header -->
                        <button type="button"
                                class="flex w-full items-center justify-between px-6 py-3.5 text-left transition-colors hover:bg-gray-50 select-none"
                                @click="toggleSubjectTerm(termPanel.assessmentId)">
                            <div class="flex items-center gap-3">
                                <!-- Expand/collapse chevron -->
                                <ChevronDown class="h-4 w-4 flex-shrink-0 text-gray-400 transition-transform duration-200"
                                             :class="{ 'rotate-180': expandedSubjectTerms.has(termPanel.assessmentId) }" />
                                <div>
                                    <p class="font-semibold text-gray-900">{{ termPanel.label }}</p>
                                    <p class="text-xs text-gray-500">{{ termPanel.schoolYear }} · {{ termPanel.course }}</p>
                                </div>
                            </div>

                            <!-- Term summary chips -->
                            <div class="flex flex-wrap items-center gap-2 text-right">
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    {{ termPanel.subjectCount }} subject{{ termPanel.subjectCount !== 1 ? 's' : '' }}
                                </span>
                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    {{ termPanel.totalUnits }} units
                                </span>
                                <span v-if="termPanel.enrolledCount > 0"
                                      class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">
                                    ✓ {{ termPanel.enrolledCount }} enrolled
                                </span>
                                <span class="min-w-[90px] rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                    {{ formatCurrency(termPanel.totalTuition + termPanel.totalLab) }}
                                </span>
                            </div>
                        </button>

                        <!-- Expanded subject table -->
                        <div v-if="expandedSubjectTerms.has(termPanel.assessmentId)" class="border-t border-gray-100 bg-gray-50">

                            <!-- Legend -->
                            <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 bg-white px-6 py-2.5 text-xs text-gray-500">
                                <span class="flex items-center gap-1.5">
                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">✓</span>
                                    Enrolled (confirmed in student_enrollments)
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-400">○</span>
                                    Assessment only (no enrollment record)
                                </span>
                                <span v-if="termPanel.totalLab > 0" class="flex items-center gap-1.5 text-purple-600">
                                    <FlaskConical class="h-3 w-3" />
                                    Has laboratory component
                                </span>
                            </div>

                            <!-- Subject rows -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 bg-gray-100 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                            <th class="px-5 py-2.5 text-left">Status</th>
                                            <th class="px-5 py-2.5 text-left">Code</th>
                                            <th class="px-5 py-2.5 text-left">Subject Name</th>
                                            <th class="px-5 py-2.5 text-center">Units</th>
                                            <th class="px-5 py-2.5 text-right">Unit Cost</th>
                                            <th class="px-5 py-2.5 text-right">Lab Fee</th>
                                            <th class="px-5 py-2.5 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="subject in termPanel.subjects" :key="subject.subject_id"
                                            :class="['transition-colors', subject.isEnrolled ? 'hover:bg-green-50/50' : 'hover:bg-gray-50']">

                                            <!-- Enrolled indicator -->
                                            <td class="px-5 py-3 text-center">
                                                <span v-if="subject.isEnrolled"
                                                      class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700"
                                                      title="Confirmed enrollment record exists">
                                                    ✓
                                                </span>
                                                <span v-else
                                                      class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-400"
                                                      title="Assessment record only — no enrollment record">
                                                    ○
                                                </span>
                                            </td>

                                            <!-- Subject Code -->
                                            <td class="px-5 py-3">
                                                <span class="rounded bg-indigo-50 px-2 py-0.5 font-mono text-xs font-semibold text-indigo-700">
                                                    {{ subject.code }}
                                                </span>
                                            </td>

                                            <!-- Subject Name -->
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-medium text-gray-900">{{ subject.name }}</span>
                                                    <FlaskConical v-if="subject.hasLab"
                                                                  class="h-3.5 w-3.5 flex-shrink-0 text-purple-500"
                                                                  title="Has laboratory component" />
                                                </div>
                                            </td>

                                            <!-- Units -->
                                            <td class="px-5 py-3 text-center">
                                                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                                    {{ subject.units }} unit{{ subject.units !== 1 ? 's' : '' }}
                                                </span>
                                            </td>

                                            <!-- Unit cost (units × rate) -->
                                            <td class="px-5 py-3 text-right">
                                                <span class="text-xs text-gray-500">
                                                    {{ subject.units }} × {{ formatCurrency(subject.units > 0 ? subject.tuitionAmount / subject.units : 0) }}
                                                </span>
                                                <p class="font-medium text-gray-900">{{ formatCurrency(subject.tuitionAmount) }}</p>
                                            </td>

                                            <!-- Lab fee -->
                                            <td class="px-5 py-3 text-right">
                                                <span v-if="subject.hasLab" class="font-medium text-purple-700">
                                                    {{ formatCurrency(subject.labAmount) }}
                                                </span>
                                                <span v-else class="text-xs text-gray-300">—</span>
                                            </td>

                                            <!-- Total per subject -->
                                            <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                                {{ formatCurrency(subject.tuitionAmount + subject.labAmount) }}
                                            </td>
                                        </tr>
                                    </tbody>

                                    <!-- Footer totals row -->
                                    <tfoot>
                                        <tr class="border-t-2 border-gray-200 bg-gray-50 text-sm font-semibold">
                                            <td colspan="3" class="px-5 py-3 text-gray-700">
                                                Subtotal — {{ termPanel.subjectCount }} subjects · {{ termPanel.totalUnits }} total units
                                            </td>
                                            <td class="px-5 py-3 text-center text-gray-700">—</td>
                                            <td class="px-5 py-3 text-right text-gray-900">{{ formatCurrency(termPanel.totalTuition) }}</td>
                                            <td class="px-5 py-3 text-right text-purple-700">
                                                <span v-if="termPanel.totalLab > 0">{{ formatCurrency(termPanel.totalLab) }}</span>
                                                <span v-else class="text-xs font-normal text-gray-300">—</span>
                                            </td>
                                            <td class="px-5 py-3 text-right text-indigo-700">
                                                {{ formatCurrency(termPanel.totalTuition + termPanel.totalLab) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Misc note footer -->
                            <div class="border-t border-gray-100 bg-white px-5 py-2.5 text-xs text-gray-400">
                                Miscellaneous fees (registration, library, athletics, etc.) are fixed per semester and are not listed per subject above.
                                They are included in the Total Assessment shown in the Fee Breakdown card.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ── END ENROLLED SUBJECTS ACCORDION ── -->

            <!-- ── Payment History ── -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>
                        {{ filteredPayments.length }} payment(s) for {{ selectedAssessment?.year_level }} — {{ selectedAssessment?.semester }} {{ selectedAssessment?.school_year }}
                        <span v-if="props.payments.length > filteredPayments.length" class="mt-1 block text-xs text-gray-500">
                            ({{ props.payments.length }} total across all assessments)
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="border-b border-gray-200 bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase">Year & Sem</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold tracking-wider text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="filteredPayments.length === 0">
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-400">
                                        <CreditCard class="mx-auto mb-2 h-8 w-8 opacity-30" />
                                        <p>No payment history found</p>
                                    </td>
                                </tr>
                                <tr v-for="payment in visiblePayments" :key="payment.id" class="transition-colors hover:bg-gray-50">
                                    <td class="px-6 py-3 text-sm whitespace-nowrap text-gray-600">{{ formatDateShort(payment.paid_at) }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap"><span class="font-mono text-xs text-gray-700">{{ payment.reference_number }}</span></td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 capitalize">{{ payment.payment_method }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ payment.description }}</td>
                                    <td class="px-6 py-3 text-sm whitespace-nowrap">
                                        <div v-if="payment.school_year || payment.semester">
                                            <p class="font-medium text-gray-800">{{ payment.school_year }}</p>
                                            <p class="text-xs text-gray-500">{{ payment.semester }}</p>
                                        </div>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-6 py-3 text-right text-sm font-semibold whitespace-nowrap text-green-600">+ {{ formatCurrency(payment.amount) }}</td>
                                    <td class="px-6 py-3 text-center whitespace-nowrap">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                              :class="payment.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                                            {{ payment.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="hasMorePayments" class="border-t px-6 py-3 text-center">
                        <button type="button" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline transition-colors" @click="loadMorePayments">
                            See More ({{ filteredPayments.length - paymentHistoryLimit }} remaining)
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Transaction History ── -->
            <div>
                <div class="mb-3 flex items-center justify-between px-1">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Transaction Ledger</h2>
                        <p class="text-sm text-gray-500">All payment transactions grouped by term</p>
                    </div>
                </div>

                <div v-if="transactionsByTerm.length === 0" class="rounded-xl border bg-white p-10 text-center text-gray-400">
                    <AlertCircle class="mx-auto mb-2 h-8 w-8 opacity-30" />
                    <p>No payment transactions found</p>
                </div>

                <div v-for="group in transactionsByTerm" :key="group.key" class="mb-4 overflow-hidden rounded-xl border bg-white shadow-sm">
                    <div class="flex cursor-pointer items-center justify-between p-5 transition-colors select-none hover:bg-gray-50" @click="toggleTerm(group.key)">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ group.key }}</h3>
                            <p class="mt-0.5 text-sm text-gray-400">{{ group.transactions.length }} transaction{{ group.transactions.length !== 1 ? 's' : '' }}</p>
                        </div>
                        <div class="flex items-center gap-8 text-right md:gap-12">
                            <div>
                                <p class="text-xs text-gray-400">Total Assessed</p>
                                <p class="text-sm font-bold text-red-600">{{ formatCurrency(group.totalCharges) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Total Paid</p>
                                <p class="text-sm font-bold text-green-600">{{ formatCurrency(group.totalPaid) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Balance</p>
                                <p class="text-sm font-bold" :class="group.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ formatCurrency(Math.abs(group.balance)) }}
                                </p>
                            </div>
                            <ChevronDown class="h-5 w-5 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedTerms[group.key] }" />
                        </div>
                    </div>

                    <div v-if="expandedTerms[group.key]" class="border-t">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase">
                                        <th class="px-4 py-3 font-semibold">Reference</th>
                                        <th class="px-4 py-3 font-semibold">Type</th>
                                        <th class="px-4 py-3 font-semibold">Category</th>
                                        <th class="px-4 py-3 font-semibold">Year & Semester</th>
                                        <th class="px-4 py-3 font-semibold">Amount</th>
                                        <th class="px-4 py-3 font-semibold">Status</th>
                                        <th class="px-4 py-3 font-semibold">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in group.transactions" :key="t.id" class="border-b border-gray-100 transition-colors hover:bg-gray-50">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ t.reference }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">payment</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ t.type }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <div v-if="t.year || t.semester">
                                                <p class="font-medium text-gray-800">{{ toYearRange(t.year) }}</p>
                                                <p class="text-xs text-gray-500">{{ t.semester }}</p>
                                            </div>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-green-600">
                                            +{{ formatCurrency(t.amount) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  :class="{ 'bg-green-100 text-green-800': t.status === 'paid', 'bg-yellow-100 text-yellow-800': t.status === 'pending', 'bg-blue-100 text-blue-800': t.status === 'awaiting_approval', 'bg-red-100 text-red-800': t.status === 'failed', 'bg-gray-100 text-gray-700': t.status === 'cancelled' }">
                                                {{ t.status === 'awaiting_approval' ? 'Awaiting Verification' : t.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDateShort(t.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>