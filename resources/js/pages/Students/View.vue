<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const { student } = defineProps<{ student: any }>();

/**
 * FIX: Balance is now read from student.user.account.balance (accounts table),
 * which is the single source of truth written by AccountService::recalculate().
 * student.total_balance no longer exists — removed in migration
 * 2026_03_17_000001_drop_total_balance_from_students_table.
 */
const remainingBalance = computed<number>(() => {
    return Number(student.user?.account?.balance ?? student.account?.balance ?? 0);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Students', href: route('students.index') },
    { title: student.name },
];
</script>

<template>
    <Head :title="`View Student - ${student.name}`" />

    <AppLayout>
        <div class="mx-auto max-w-4xl p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">{{ student.name }}</h1>
                <p class="text-gray-500">Account ID: {{ student.student_id }}</p>
            </div>

            <!-- Student Info -->
            <div class="mb-6 rounded-xl bg-white p-6 shadow">
                <h2 class="mb-4 text-xl font-medium text-gray-800">Student Information</h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><span class="font-medium">Email:</span> {{ student.user?.email }}</div>
                    <div><span class="font-medium">Course:</span> {{ student.user?.course }}</div>
                    <div><span class="font-medium">Year:</span> {{ student.user?.year_level }}</div>
                    <div><span class="font-medium">Phone:</span> {{ student.user?.phone || 'N/A' }}</div>
                    <div class="md:col-span-2"><span class="font-medium">Address:</span> {{ student.user?.address || 'N/A' }}</div>
                </div>

                <!-- Balance — reads from accounts.balance (single source of truth) -->
                <div class="mt-6 border-t pt-4">
                    <p class="text-lg font-semibold">
                        Outstanding Balance:
                        <span :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                            ₱{{ Math.abs(remainingBalance).toFixed(2) }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Payment History -->
            <div class="rounded-xl bg-white p-6 shadow">
                <h2 class="mb-4 text-xl font-medium text-gray-800">Payment History</h2>

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
                <p v-else class="text-gray-500">No payments recorded yet.</p>
            </div>
        </div>
    </AppLayout>
</template>