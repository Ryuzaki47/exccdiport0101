<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

/**
 * FIX: total_balance removed from the form.
 * Balance is computed exclusively by AccountService::recalculate() and stored
 * in accounts.balance. New students start with a balance of 0, set automatically
 * when their Account record is created during registration or student creation.
 */
const form = useForm({
    student_id: '',
    first_name: '',
    last_name: '',
    middle_initial: '',
    email: '',
    course: '',
    year_level: '',
    birthday: '',
    phone: '',
    address: '',
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Students', href: route('students.index') },
    { title: 'Create', href: '#' },
];

function submit() {
    form.post(route('students.store'));
}
</script>

<template>
    <Head title="Add New Student" />

    <AppLayout>
        <div class="mx-auto max-w-2xl p-6">
            <Breadcrumbs :items="breadcrumbs" />
            <h1 class="mb-6 text-2xl font-semibold text-gray-800">Add New Student</h1>

            <form @submit.prevent="submit" class="space-y-4 rounded-xl bg-white p-6 shadow-md">
                <!-- Account ID -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Account ID *</label>
                    <input
                        v-model="form.student_id"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="2024-0001"
                    />
                    <p v-if="form.errors.student_id" class="text-sm text-red-500">{{ form.errors.student_id }}</p>
                </div>

                <!-- Last Name -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Last Name *</label>
                    <input
                        v-model="form.last_name"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="Dela Cruz"
                    />
                    <p v-if="form.errors.last_name" class="text-sm text-red-500">{{ form.errors.last_name }}</p>
                </div>

                <!-- First Name -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">First Name *</label>
                    <input
                        v-model="form.first_name"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="Juan"
                    />
                    <p v-if="form.errors.first_name" class="text-sm text-red-500">{{ form.errors.first_name }}</p>
                </div>

                <!-- Middle Initial -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Middle Initial</label>
                    <input
                        v-model="form.middle_initial"
                        maxlength="10"
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="P"
                    />
                    <p v-if="form.errors.middle_initial" class="text-sm text-red-500">{{ form.errors.middle_initial }}</p>
                </div>

                <!-- Email -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Email *</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="student@ccdi.edu.ph"
                    />
                    <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
                </div>

                <!-- Course & Year Level -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Course *</label>
                        <input
                            v-model="form.course"
                            required
                            class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="BS Information Technology"
                        />
                        <p v-if="form.errors.course" class="text-sm text-red-500">{{ form.errors.course }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Year Level *</label>
                        <input
                            v-model="form.year_level"
                            required
                            class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="1st Year"
                        />
                        <p v-if="form.errors.year_level" class="text-sm text-red-500">{{ form.errors.year_level }}</p>
                    </div>
                </div>

                <!-- Birthday -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Birthday</label>
                    <input v-model="form.birthday" type="date" class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500" />
                    <p v-if="form.errors.birthday" class="text-sm text-red-500">{{ form.errors.birthday }}</p>
                </div>

                <!-- Phone -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Phone Number</label>
                    <input
                        v-model="form.phone"
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="+63 912 345 6789"
                    />
                    <p v-if="form.errors.phone" class="text-sm text-red-500">{{ form.errors.phone }}</p>
                </div>

                <!-- Address -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="Complete address"
                    ></textarea>
                    <p v-if="form.errors.address" class="text-sm text-red-500">{{ form.errors.address }}</p>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="$router.back()" class="rounded-lg border px-4 py-2 text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        Create Student
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
