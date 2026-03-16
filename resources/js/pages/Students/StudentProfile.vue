<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const { student } = defineProps<{
    student: any;
}>();

// Payment form state
const payment = useForm({
    amount: '',
    description: '',
    payment_method: 'gcash',
    reference_number: '',
    status: 'completed',
    paid_at: new Date().toISOString().split('T')[0],
});

function addPayment(studentId: number) {
    payment.post(route('students.payments.store', studentId), {
        onSuccess: () => {
            payment.reset();
            payment.paid_at = new Date().toISOString().split('T')[0];
        },
    });
}

/**
 * FIX: Balance is now read from student.user.account.balance (accounts table),
 * which is the single source of truth written by AccountService::recalculate().
 * student.total_balance no longer exists — removed in migration
 * 2026_03_17_000001_drop_total_balance_from_students_table.
 *
 * The controller eager-loads 'user.account' so student.user.account.balance
 * is always available. The fallback to student.account?.balance handles the
 * case where the student model is serialised directly without the user relation.
 */
const remainingBalance = computed<number>(() => {
    return Number(student.user?.account?.balance ?? student.account?.balance ?? 0);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Students', href: route('students.index') },
    { title: `${student.user?.last_name}, ${student.user?.first_name}` },
];
</script>

<template>
    <Head :title="`My Profile - ${student.user?.last_name}, ${student.user?.first_name}`" />

    <AppLayout>
        <div class="w-full p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">My Student Profile</h1>
                <p class="text-gray-500">Account ID: {{ student.student_id }}</p>
            </div>

            <!-- Student Info Card -->
            <div class="mb-6 rounded-xl bg-white p-6 shadow">
                <h2 class="mb-4 text-xl font-medium text-gray-800">Personal Information</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <span class="font-medium">Full Name:</span>
                        {{ student.user?.last_name }}, {{ student.user?.first_name }}{{ student.user?.middle_initial ? ' ' + student.user.middle_initial + '.' : '' }}
                    </div>
                    <div><span class="font-medium">Account ID:</span> {{ student.student_id }}</div>
                    <div><span class="font-medium">Email:</span> {{ student.user?.email }}</div>
                    <div><span class="font-medium">Course:</span> {{ student.user?.course }}</div>
                    <div><span class="font-medium">Year:</span> {{ student.user?.year_level }}</div>
                    <div v-if="student.user?.phone"><span class="font-medium">Phone:</span> {{ student.user?.phone }}</div>
                    <div v-if="student.user?.birthday">
                        <span class="font-medium">Birthday:</span>
                        {{ new Date(student.user?.birthday).toLocaleDateString() }}
                    </div>
                    <div class="md:col-span-2" v-if="student.user?.address">
                        <span class="font-medium">Address:</span> {{ student.user?.address }}
                    </div>
                </div>

                <!-- Balance — reads from accounts.balance (single source of truth) -->
                <div class="mt-6 border-t pt-4">
                    <p class="text-lg font-semibold">
                        Outstanding Balance:
                        <span :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                            ₱{{ Math.abs(Number(remainingBalance)).toFixed(2) }}
                        </span>
                    </p>
                    <p v-if="remainingBalance <= 0" class="mt-1 text-sm text-green-600">
                        Your account is fully settled.
                    </p>
                </div>
            </div>

            <!-- Payment History -->
            <div class="rounded-xl bg-white p-6 shadow">
                <h2 class="mb-4 text-xl font-medium text-gray-800">My Payment History</h2>

                <div v-if="student.payments?.length" class="divide-y">
                    <div v-for="p in student.payments" :key="p.id" class="flex items-center justify-between py-3">
                        <div>
                            <p class="font-medium">₱{{ p.amount }}</p>
                            <p class="text-sm text-gray-600">{{ p.description }}</p>
                            <p class="text-xs text-gray-500">
                                {{ p.payment_method }}
                                <span v-if="p.reference_number">• Ref: {{ p.reference_number }}</span>
                            </p>
                        </div>
                        <span class="text-sm text-gray-500">{{ new Date(p.created_at).toLocaleDateString() }}</span>
                    </div>
                </div>
                <p v-else class="text-gray-500">No payment history found.</p>

                <!-- Add Payment Form -->
                <form @submit.prevent="addPayment(student.id)" class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <h3 class="mb-2 text-lg font-medium text-gray-800">Add New Payment</h3>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                        <input v-model="payment.amount" type="number" step="0.01" min="0" placeholder="0.00" required
                            class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Payment Method</label>
                        <select v-model="payment.payment_method"
                            class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Reference Number</label>
                        <input v-model="payment.reference_number" placeholder="Optional reference number"
                            class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Payment Date</label>
                        <input v-model="payment.paid_at" type="date" required
                            class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                        <input v-model="payment.description" placeholder="Payment description" required
                            class="w-full rounded-lg border px-4 py-2 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" :disabled="payment.processing"
                            class="w-full rounded-lg bg-indigo-600 px-5 py-2 text-white shadow transition-colors hover:bg-indigo-700 disabled:opacity-50">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>