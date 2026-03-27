<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const { student } = defineProps<{
    student: any;
}>();

const studentName = `${student.user?.last_name}, ${student.user?.first_name}${student.user?.middle_initial ? ' ' + student.user.middle_initial + '.' : ''}`;

/**
 * FIX: total_balance removed from the form.
 * Balance is computed exclusively by AccountService::recalculate() and stored
 * in accounts.balance. It is never manually editable via a form — it is derived
 * from transactions. If a balance correction is needed, record a payment or
 * charge transaction through the proper payment flow instead.
 */
const form = useForm({
    student_id: student.student_id,
    first_name: student.user?.first_name || '',
    last_name: student.user?.last_name || '',
    middle_initial: student.user?.middle_initial || '',
    email: student.user?.email || '',
    course: student.user?.course || '',
    year_level: student.user?.year_level || '',
    birthday: student.user?.birthday ? student.user.birthday.split('T')[0] : '',
    phone: student.user?.phone || '',
    address: student.user?.address || '',
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Students', href: route('students.index') },
    { title: `Edit ${studentName}`, href: '#' },
];

function submit() {
    form.put(route('students.update', student.id));
}
</script>

<template>
    <Head :title="`Edit ${studentName}`" />

    <AppLayout>
        <div class="mx-auto max-w-3xl p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <h1 class="mb-6 text-2xl font-semibold text-gray-800">Edit Student: {{ studentName }}</h1>

            <form @submit.prevent="submit" class="space-y-4 rounded-xl bg-white p-6 shadow-md">
                <!-- Student ID -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Student ID *</label>
                    <input v-model="form.student_id" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                    <p v-if="form.errors.student_id" class="text-sm text-red-500">{{ form.errors.student_id }}</p>
                </div>

                <!-- Last Name -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Last Name *</label>
                    <input v-model="form.last_name" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                    <p v-if="form.errors.last_name" class="text-sm text-red-500">{{ form.errors.last_name }}</p>
                </div>

                <!-- First Name -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">First Name *</label>
                    <input v-model="form.first_name" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                    <p v-if="form.errors.first_name" class="text-sm text-red-500">{{ form.errors.first_name }}</p>
                </div>

                <!-- Middle Initial -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Middle Initial</label>
                    <input v-model="form.middle_initial" maxlength="10" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                </div>

                <!-- Email -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                    <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
                </div>

                <!-- Course & Year Level -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Course *</label>
                        <input v-model="form.course" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                        <p v-if="form.errors.course" class="text-sm text-red-500">{{ form.errors.course }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Year Level *</label>
                        <input v-model="form.year_level" required class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                        <p v-if="form.errors.year_level" class="text-sm text-red-500">{{ form.errors.year_level }}</p>
                    </div>
                </div>

                <!-- Birthday -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Birthday</label>
                    <input v-model="form.birthday" type="date" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                </div>

                <!-- Phone -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Phone Number</label>
                    <input v-model="form.phone" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                </div>

                <!-- Address -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                    <textarea v-model="form.address" rows="3" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="$router.back()" class="rounded-lg border px-4 py-2 text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        Update Student
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
