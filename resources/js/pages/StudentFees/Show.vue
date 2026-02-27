<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ArrowLeft, Plus, Download, Wallet } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';

interface Props {
    student: any;
    assessment: any;
    transactions: any[];
    payments: any[];
    feeBreakdown: Array<{
        category: string;
        total: number;
        items: number;
    }>;
}

const props = defineProps<Props>();

// Calculate remaining balance
const remainingBalance = computed(() => {
    const balance = props.student.account?.balance || 0
    return Math.max(0, parseFloat(String(balance)))
});

// Get available payment terms for payment (only unpaid terms)
const availableTermsForPayment = computed(() => {
    const unpaidTerms = props.assessment?.paymentTerms
        ?.filter((term: any) => term.balance > 0)
        .sort((a: any, b: any) => a.term_order - b.term_order) || []
    
    // Only the first unpaid term is selectable
    const firstUnpaidIndex = unpaidTerms.length > 0 ? 0 : -1
    
    return unpaidTerms.map((term: any, index: number) => ({
        id: term.id,
        label: term.term_name,
        term_name: term.term_name,
        value: term.id,
        balance: term.balance,
        amount: term.amount,
        due_date: term.due_date,
        status: term.status,
        isSelectable: index === firstUnpaidIndex,
        hasCarryover: term.remarks?.toLowerCase().includes('carried') || false,
    }))
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name },
];

const showPaymentDialog = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    term_id: null as string | number | null,
    payment_date: new Date().toISOString().split('T')[0],
});

// Get first unpaid term (for auto-selection)
const firstUnpaidTerm = computed(() => {
    return availableTermsForPayment.value.find((term: any) => term.isSelectable) || null
});

// Get selected term details
const selectedTerm = computed(() => {
    if (!paymentForm.term_id) return null
    return availableTermsForPayment.value.find((term: any) => term.id === paymentForm.term_id) || null
});

// Calculate remaining balance after payment
const projectedRemainingBalance = computed(() => {
    const paymentAmount = parseFloat(paymentForm.amount) || 0
    const projected = remainingBalance.value - paymentAmount
    return Math.max(0, projected)
});

// Validate payment amount
const paymentAmountError = computed(() => {
    const amount = parseFloat(paymentForm.amount) || 0
    
    if (amount <= 0 && paymentForm.amount) {
        return 'Amount must be greater than zero'
    }
    
    if (amount > remainingBalance.value) {
        return `Amount cannot exceed remaining balance of ${formatCurrency(remainingBalance.value)}`
    }
    
    if (selectedTerm.value && amount > selectedTerm.value.balance) {
        return `Amount cannot exceed selected term balance of ${formatCurrency(selectedTerm.value.balance)}`
    }
    
    return ''
});

// Check if payment form is valid for submission
const canSubmitPayment = computed(() => {
    const amount = parseFloat(paymentForm.amount) || 0
    return (
        amount > 0 &&
        amount <= remainingBalance.value &&
        paymentForm.term_id !== null &&
        !paymentForm.processing &&
        availableTermsForPayment.value.length > 0
    )
});

// Get status badge config
const getTermStatusConfig = (status: string) => {
    const configs: Record<string, { bg: string; text: string; label: string }> = {
        'pending': { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Unpaid' },
        'partial': { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Partially Paid' },
        'paid': { bg: 'bg-green-100', text: 'text-green-800', label: 'Paid' },
    }
    return configs[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
};

// Auto-select first unpaid term when dialog opens
watch(() => showPaymentDialog.value, (isOpen) => {
    if (isOpen && firstUnpaidTerm.value && !paymentForm.term_id) {
        paymentForm.term_id = firstUnpaidTerm.value.id
    }
});

const submitPayment = () => {
    // Frontend validation
    if (!canSubmitPayment.value) {
        if (!paymentForm.term_id) {
            paymentForm.setError('term_id', 'Please select a payment term')
        }
        if (!paymentForm.amount) {
            paymentForm.setError('amount', 'Please enter an amount')
        }
        return
    }

    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentDialog.value = false;
            paymentForm.reset();
            paymentForm.clearErrors();
        },
        onError: (errors) => {
            // Errors will be displayed in the form
            console.error('Payment errors:', errors);
        }
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Fee Details - ${student.name}`" />

    <AppLayout>
        <div class="space-y-6 max-w-6xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold">Student Fee Details</h1>
                        <p class="text-gray-600 mt-2">
                            {{ student.name }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('student-fees.export-pdf', student.id)" target="_blank">
                        <Button variant="outline">
                            <Download class="w-4 h-4 mr-2" />
                            Export PDF
                        </Button>
                    </Link>
                    <Dialog v-model:open="showPaymentDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                Record Payment
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Record New Payment</DialogTitle>
                                <DialogDescription>
                                    <div class="space-y-1">
                                        <p>Add a payment for {{ student.name }}</p>
                                        <p class="text-base font-semibold text-slate-900">Current Balance: {{ formatCurrency(remainingBalance) }}</p>
                                    </div>
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitPayment" class="space-y-4">
                                <!-- Amount -->
                                <div class="space-y-2">
                                    <Label for="amount">Amount *</Label>
                                    <Input
                                        id="amount"
                                        v-model="paymentForm.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        :max="remainingBalance"
                                        required
                                        placeholder="0.00"
                                        :class="{
                                            'border-red-500 focus:ring-red-500': paymentAmountError
                                        }"
                                    />
                                    <p v-if="paymentAmountError" class="text-sm text-red-500 font-medium">
                                        {{ paymentAmountError }}
                                    </p>
                                    <p v-else class="text-xs text-gray-500">
                                        Maximum: {{ formatCurrency(remainingBalance) }}
                                    </p>
                                    <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">
                                        {{ paymentForm.errors.amount }}
                                    </p>
                                </div>

                                <!-- Payment Method -->
                                <div class="space-y-2">
                                    <Label>Payment Method</Label>
                                    <div class="px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                                        <p class="text-gray-700 font-medium">Cash</p>
                                        <p class="text-xs text-gray-500">On-campus, in-person payment</p>
                                    </div>
                                </div>

                                <!-- Payment Date -->
                                <div class="space-y-2">
                                    <Label for="payment_date">Payment Date *</Label>
                                    <Input
                                        id="payment_date"
                                        v-model="paymentForm.payment_date"
                                        type="date"
                                        required
                                    />
                                    <p v-if="paymentForm.errors.payment_date" class="text-sm text-red-500">
                                        {{ paymentForm.errors.payment_date }}
                                    </p>
                                </div>

                                <!-- Select Term (Required) -->
                                <div class="space-y-2">
                                    <Label for="term_id" class="text-sm font-medium text-gray-700">
                                        Select Term
                                        <span class="text-xs text-red-500">*</span>
                                    </Label>
                                    <select
                                        id="term_id"
                                        v-model.number="paymentForm.term_id"
                                        required
                                        :disabled="remainingBalance <= 0 || availableTermsForPayment.length === 0"
                                        class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option :value="null">-- Choose a payment term --</option>
                                        <option
                                            v-for="term in availableTermsForPayment"
                                            :key="term.id"
                                            :value="term.id"
                                            :disabled="!term.isSelectable"
                                        >
                                            {{ term.label }} - {{ formatCurrency(term.balance) }} {{ !term.isSelectable ? '(Not yet available)' : '' }}
                                        </option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Only the first unpaid term can be selected. Overpayments will carry over to the next term.
                                    </p>
                                    <p v-if="paymentForm.errors.term_id" class="text-red-600 text-sm mt-1">
                                        {{ paymentForm.errors.term_id }}
                                    </p>
                                </div>

                                <!-- Selected Term Details -->
                                <div v-if="selectedTerm" class="space-y-2 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-xs text-gray-600 font-medium">SELECTED TERM</p>
                                            <p class="text-sm font-semibold text-gray-900 mt-1">{{ selectedTerm.label }}</p>
                                        </div>
                                        <span :class="[
                                            'text-xs px-2 py-1 rounded font-medium',
                                            getTermStatusConfig(selectedTerm.status).bg,
                                            getTermStatusConfig(selectedTerm.status).text
                                        ]">
                                            {{ getTermStatusConfig(selectedTerm.status).label }}
                                        </span>
                                    </div>
                                    <div class="pt-2 border-t border-blue-200 grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <p class="text-xs text-gray-600">Current Balance</p>
                                            <p class="font-semibold text-blue-700">{{ formatCurrency(selectedTerm.balance) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-600">Original Amount</p>
                                            <p class="font-semibold text-gray-700">{{ formatCurrency(selectedTerm.amount) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Preview -->
                                <div v-if="parseFloat(paymentForm.amount) > 0" class="space-y-2 p-3 bg-green-50 rounded-lg border border-green-200">
                                    <p class="text-xs text-gray-600 font-medium">PAYMENT PREVIEW</p>
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-gray-600">Current Balance</p>
                                            <p class="font-semibold text-red-600">{{ formatCurrency(remainingBalance) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-600">Payment Amount</p>
                                            <p class="font-semibold text-blue-600">- {{ formatCurrency(parseFloat(paymentForm.amount)) }}</p>
                                        </div>
                                        <div class="col-span-2 pt-2 border-t border-green-200 flex justify-between">
                                            <span class="text-xs text-gray-600 font-medium">Balance After Payment</span>
                                            <span :class="[
                                                'font-bold',
                                                projectedRemainingBalance > 0 ? 'text-red-600' : 'text-green-600'
                                            ]">
                                                {{ formatCurrency(projectedRemainingBalance) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="showPaymentDialog = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Button 
                                        type="submit" 
                                        :disabled="!canSubmitPayment"
                                        :class="{
                                            'opacity-50 cursor-not-allowed': !canSubmitPayment
                                        }"
                                    >
                                        <span v-if="paymentForm.processing">Recording...</span>
                                        <span v-else-if="!canSubmitPayment && remainingBalance <= 0">No Balance to Pay</span>
                                        <span v-else-if="!canSubmitPayment && availableTermsForPayment.length === 0">No Unpaid Terms</span>
                                        <span v-else>Record Payment</span>
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Student Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <Label class="text-sm text-gray-600">Full Name</Label>
                            <p class="font-medium">{{ student.name }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Email</Label>
                            <p class="font-medium">{{ student.email }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Birthday</Label>
                            <p class="font-medium">{{ student.birthday ? formatDate(student.birthday) : 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Phone</Label>
                            <p class="font-medium">{{ student.phone || 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Student ID</Label>
                            <p class="font-medium">{{ student.student_id }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Course</Label>
                            <p class="font-medium">{{ student.course }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Year Level</Label>
                            <p class="font-medium">{{ student.year_level }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Status</Label>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                {{ student.status }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Fee Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Fee Breakdown</CardTitle>
                    <CardDescription>Current assessment details</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <div
                            v-for="breakdown in feeBreakdown"
                            :key="breakdown.category"
                            class="flex justify-between items-center p-3 border rounded-lg"
                        >
                            <div>
                                <p class="font-medium">{{ breakdown.category }}</p>
                                <p class="text-sm text-gray-600">{{ breakdown.items }} items</p>
                            </div>
                            <span class="font-bold">{{ formatCurrency(breakdown.total) }}</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t space-y-2">
                        <div class="flex justify-between items-center text-lg">
                            <span class="font-medium">Total Assessment</span>
                            <span class="font-bold">
                                {{ formatCurrency(assessment?.total_assessment || 0) }}
                            </span>
                        </div>
                        <!-- Current Balance Card (Dashboard style) -->
                        <div :class="[
                            'mt-4 -mx-6 -mb-6 px-6 py-4 rounded-b-lg flex items-center gap-4 border-t-2',
                            remainingBalance > 0 ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'
                        ]">
                            <div :class="[
                                'p-3 rounded-lg',
                                remainingBalance > 0 ? 'bg-red-100' : 'bg-green-100'
                            ]">
                                <Wallet :size="24" :class="[
                                    remainingBalance > 0 ? 'text-red-600' : 'text-green-600'
                                ]" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-600">Current Balance</p>
                                <p :class="[
                                    'text-2xl font-bold',
                                    remainingBalance > 0 ? 'text-red-600' : 'text-green-600'
                                ]">
                                    {{ formatCurrency(remainingBalance) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment History -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All recorded payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="payments.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No payment history found
                                    </td>
                                </tr>
                                <tr v-for="payment in payments" :key="payment.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(payment.paid_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ payment.reference_number }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                            {{ payment.payment_method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">{{ payment.description }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                        {{ formatCurrency(payment.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction History -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction History</CardTitle>
                    <CardDescription>All charges and payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="transactions.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        No transactions found
                                    </td>
                                </tr>
                                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(transaction.created_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ transaction.reference }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span 
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="transaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                        >
                                            {{ transaction.kind }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ transaction.type }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                            {{ transaction.status }}
                                        </span>
                                    </td>
                                    <td 
                                        class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                        :class="transaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                                    >
                                        {{ transaction.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>