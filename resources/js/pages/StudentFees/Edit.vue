<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDataFormatting } from '@/composables/useDataFormatting';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2, UserCog } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Student {
    id: number;
    account_id: string;
    name: string;
    last_name: string;
    first_name: string;
    middle_initial: string | null;
    email: string;
    birthday: string | null;
    phone: string | null;
    address: string | null;
    course: string | null;
    year_level: string | null;
    status: string;
}

interface FeeLineItem {
    category: string;
    name: string;
    amount: number;
}

interface Assessment {
    id: number;
    assessment_number: string;
    course: string | null;
    year_level: string;
    semester: string;
    school_year: string;
    tuition_fee: number;
    other_fees: number;
    total_assessment: number;
    fee_breakdown: FeeLineItem[];
    status: string;
}

interface Preset {
    category: string;
    name: string;
    amount: number;
}

interface Presets {
    [course: string]: {
        [yearLevel: string]: {
            [semester: string]: Preset[];
        };
    };
}

interface Props {
    student: Student;
    assessment: Assessment;
    courses: string[];
    feeCategories: string[];
    presets: Presets;
}

const props = defineProps<Props>();

const { formatCurrency } = useDataFormatting();

// ─── Breadcrumbs ──────────────────────────────────────────────────────────────

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name, href: route('student-fees.show', props.student.id) },
    { title: 'Edit' },
];

// ─── Constants ────────────────────────────────────────────────────────────────

const yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const semesters = ['1st Sem', '2nd Sem', 'Summer'];

// ─── Form ─────────────────────────────────────────────────────────────────────

// Seed fee_items from the stored fee_breakdown JSON.
// Each item has category, name, amount — matching exactly what update() expects.
const seedFeeItems = (): FeeLineItem[] => {
    const bd = props.assessment.fee_breakdown ?? [];
    if (bd.length > 0) {
        return bd.map((item) => ({
            category: item.category ?? 'Tuition',
            name: item.name ?? '',
            amount: Number(item.amount) || 0,
        }));
    }
    // Fallback: single tuition line from totals when fee_breakdown is empty
    return [
        { category: 'Tuition', name: 'Tuition Fee', amount: Number(props.assessment.tuition_fee) || 0 },
        { category: 'Other', name: 'Other Fees', amount: Number(props.assessment.other_fees) || 0 },
    ].filter((r) => r.amount > 0);
};

// ── FIX (Bug #5): Seed course from assessment.course, NOT student.course.
//
// assessment.course is the authoritative course this fee schedule was built for.
// student.course may differ if it was changed after the assessment was created.
// Using student.course would silently overwrite the assessed course on every save,
// defeating the course-tracking feature added to student_assessments.
const resolvedCourse = (): string => {
    const fromAssessment = props.assessment.course?.trim();
    if (fromAssessment && fromAssessment !== 'N/A') return fromAssessment;

    const fromStudent = props.student.course?.trim();
    if (fromStudent && fromStudent !== 'N/A') return fromStudent;

    return '';
};

const form = useForm({
    // ── Student profile ──────────────────────────────────────────────────────
    last_name: props.student.last_name ?? '',
    first_name: props.student.first_name ?? '',
    middle_initial: props.student.middle_initial ?? '',
    email: props.student.email ?? '',
    birthday: props.student.birthday ?? '',
    phone: props.student.phone ?? '',
    address: props.student.address ?? '',
    course: resolvedCourse(),
    // ── Assessment term ──────────────────────────────────────────────────────
    year_level: props.assessment.year_level ?? '',
    semester: props.assessment.semester ?? '',
    school_year: props.assessment.school_year ?? '',
    // ── Fee breakdown (sent as fee_items[]) ──────────────────────────────────
    fee_items: seedFeeItems(),
});

// ─── Update fee items when course/semester changes ───────────────────────────

function updateFeeItemsFromPreset() {
    const course = form.course.trim();
    const yearLevel = form.year_level.trim();
    const semester = form.semester.trim();

    if (!course || !yearLevel || !semester) return;

    const coursePresets = props.presets[course]?.[yearLevel]?.[semester];
    if (coursePresets && Array.isArray(coursePresets)) {
        form.fee_items = coursePresets.map((item) => ({
            category: item.category ?? 'Tuition',
            name: item.name ?? '',
            amount: Number(item.amount) || 0,
        }));
    }
}

// Watch for changes to course, year_level, or semester and update fee items
watch(
    () => [form.course, form.year_level, form.semester],
    () => {
        updateFeeItemsFromPreset();
    },
);

// Event handler for @change on dropdowns (faster feedback)
const handleCourseChange = () => {
    updateFeeItemsFromPreset();
};

// ─── Fee row management ───────────────────────────────────────────────────────

function addFeeRow() {
    form.fee_items.push({ category: 'Miscellaneous', name: '', amount: 0 });
}

function removeFeeRow(index: number) {
    form.fee_items.splice(index, 1);
}

// ─── Totals ───────────────────────────────────────────────────────────────────

const tuitionTotal = computed(() => form.fee_items.filter((r) => r.category === 'Tuition').reduce((s, r) => s + (Number(r.amount) || 0), 0));

const otherTotal = computed(() => form.fee_items.filter((r) => r.category !== 'Tuition').reduce((s, r) => s + (Number(r.amount) || 0), 0));

const grandTotal = computed(() => tuitionTotal.value + otherTotal.value);

const totalChanged = computed(() => Math.abs(grandTotal.value - Number(props.assessment.total_assessment)) > 0.01);

// ─── Validation ───────────────────────────────────────────────────────────────

const localErrors = ref<Record<string, string>>({});

function validate(): boolean {
    localErrors.value = {};

    if (!form.last_name.trim()) localErrors.value.last_name = 'Last name is required.';
    if (!form.first_name.trim()) localErrors.value.first_name = 'First name is required.';
    if (!form.email.trim()) localErrors.value.email = 'Email is required.';
    if (!form.course.trim()) localErrors.value.course = 'Course is required.';
    if (!form.year_level) localErrors.value.year_level = 'Year level is required.';
    if (!form.semester) localErrors.value.semester = 'Semester is required.';
    if (!form.school_year.match(/^\d{4}-\d{4}$/)) localErrors.value.school_year = 'School year must be in YYYY-YYYY format.';
    if (form.fee_items.length === 0) localErrors.value.fee_items = 'At least one fee line is required.';
    if (grandTotal.value <= 0) localErrors.value.fee_items = 'Total assessment must be greater than zero.';
    const emptyName = form.fee_items.some((r) => !r.name.trim());
    if (emptyName) localErrors.value.fee_items = 'All fee line items must have a name.';

    return Object.keys(localErrors.value).length === 0;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

function submit() {
    if (!validate()) return;

    form.put(route('student-fees.update', props.student.id), {
        preserveScroll: true,
    });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const statusColor = (s: string) =>
    ({
        active: 'bg-green-100 text-green-800',
        completed: 'bg-blue-100 text-blue-800',
        cancelled: 'bg-red-100 text-red-800',
    })[s] ?? 'bg-gray-100 text-gray-800';

// Helper: show either server error or local validation error
const err = (field: string): string => (form.errors as Record<string, string>)[field] ?? localErrors.value[field] ?? '';
</script>

<template>
    <Head :title="`Edit — ${student.name}`" />

    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Page header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.show', student.id)">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Edit Student &amp; Assessment</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Assessment&nbsp;
                        <span class="font-semibold text-blue-600">{{ assessment.assessment_number }}</span>
                        &nbsp;&middot;&nbsp;
                        <span :class="['inline-block rounded-full px-2 py-0.5 text-xs font-semibold', statusColor(assessment.status)]">
                            {{ assessment.status }}
                        </span>
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- SECTION 1 — Student Information (editable)                -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div class="rounded-lg border bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b px-6 py-4">
                        <UserCog class="h-5 w-5 text-blue-600" />
                        <h2 class="font-semibold text-gray-900">Student Information</h2>
                        <span class="ml-auto rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"> Editable </span>
                    </div>

                    <div class="space-y-5 p-6">
                        <!-- Read-only Account ID -->
                        <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm">
                            <span class="text-gray-500">Account ID:</span>
                            <span class="ml-2 font-semibold text-gray-800">{{ student.account_id }}</span>
                        </div>

                        <!-- Name row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label for="last_name">Last Name <span class="text-red-500">*</span></Label>
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    placeholder="Dela Cruz"
                                    :class="err('last_name') ? 'border-red-400' : ''"
                                />
                                <p v-if="err('last_name')" class="text-xs text-red-600">{{ err('last_name') }}</p>
                            </div>

                            <div class="space-y-1">
                                <Label for="first_name">First Name <span class="text-red-500">*</span></Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    placeholder="Juan"
                                    :class="err('first_name') ? 'border-red-400' : ''"
                                />
                                <p v-if="err('first_name')" class="text-xs text-red-600">{{ err('first_name') }}</p>
                            </div>

                            <div class="space-y-1">
                                <Label for="middle_initial">Middle Initial</Label>
                                <Input id="middle_initial" v-model="form.middle_initial" maxlength="10" placeholder="A" />
                            </div>
                        </div>

                        <!-- Contact row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label for="email">Email <span class="text-red-500">*</span></Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="student@ccdi.edu.ph"
                                    :class="err('email') ? 'border-red-400' : ''"
                                />
                                <p v-if="err('email')" class="text-xs text-red-600">{{ err('email') }}</p>
                            </div>

                            <div class="space-y-1">
                                <Label for="birthday">Birthday</Label>
                                <Input id="birthday" v-model="form.birthday" type="date" />
                            </div>
                        </div>

                        <!-- Contact row 2 -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label for="phone">Phone</Label>
                                <Input id="phone" v-model="form.phone" placeholder="09171234567" />
                            </div>

                            <div class="space-y-1">
                                <Label for="address">Address</Label>
                                <Input id="address" v-model="form.address" placeholder="Sorsogon City" />
                            </div>
                        </div>

                        <!-- Academic row -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label for="course">Course <span class="text-red-500">*</span></Label>
                                <select
                                    id="course"
                                    v-model="form.course"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    :class="err('course') ? 'border-red-400' : ''"
                                    @change="handleCourseChange"
                                >
                                    <option value="">— Select Course —</option>
                                    <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                                </select>
                                <p v-if="err('course')" class="text-xs text-red-600">{{ err('course') }}</p>
                            </div>

                            <!-- year_level on the student profile is updated separately from
                                 the assessment year_level below. Both are saved on submit. -->
                            <div class="space-y-1">
                                <Label for="profile_year_level">Year Level (Profile)</Label>
                                <select
                                    id="profile_year_level"
                                    v-model="form.year_level"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                    @change="handleCourseChange"
                                >
                                    <option value="">— Select Year Level —</option>
                                    <option v-for="y in yearLevels" :key="y" :value="y">{{ y }}</option>
                                </select>
                                <p class="text-xs text-gray-400">Also used as the assessment year level below.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- SECTION 2 — Assessment Term                               -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <h2 class="mb-4 font-semibold text-gray-900">Assessment Term</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="space-y-1">
                            <Label for="semester">Semester <span class="text-red-500">*</span></Label>
                            <select
                                id="semester"
                                v-model="form.semester"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                :class="err('semester') ? 'border-red-400' : ''"
                                @change="handleCourseChange"
                            >
                                <option value="">Select semester</option>
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <p v-if="err('semester')" class="text-xs text-red-600">{{ err('semester') }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label for="school_year">School Year <span class="text-red-500">*</span></Label>
                            <Input
                                id="school_year"
                                v-model="form.school_year"
                                placeholder="2025-2026"
                                :class="err('school_year') ? 'border-red-400' : ''"
                            />
                            <p v-if="err('school_year')" class="text-xs text-red-600">{{ err('school_year') }}</p>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- SECTION 3 — Fee Breakdown                                 -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div class="rounded-lg border bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h2 class="font-semibold text-gray-900">Fee Breakdown</h2>
                        <Button type="button" variant="outline" size="sm" class="flex items-center gap-1" @click="addFeeRow">
                            <Plus class="h-3.5 w-3.5" />
                            Add Line
                        </Button>
                    </div>

                    <div class="space-y-3 p-6">
                        <!-- Column headers -->
                        <div class="grid grid-cols-12 gap-2 px-1 text-xs font-medium tracking-wide text-gray-500 uppercase">
                            <div class="col-span-3">Category</div>
                            <div class="col-span-6">Name / Description</div>
                            <div class="col-span-2 text-right">Amount (₱)</div>
                            <div class="col-span-1"></div>
                        </div>

                        <!-- Fee rows -->
                        <div v-for="(row, idx) in form.fee_items" :key="idx" class="grid grid-cols-12 items-center gap-2">
                            <!-- Category -->
                            <div class="col-span-3">
                                <select
                                    v-model="row.category"
                                    class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200"
                                >
                                    <option v-for="cat in feeCategories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>

                            <!-- Name -->
                            <div class="col-span-6">
                                <Input v-model="row.name" placeholder="e.g. Tuition Fee" class="text-sm" />
                            </div>

                            <!-- Amount -->
                            <div class="col-span-2">
                                <Input v-model.number="row.amount" type="number" min="0" step="0.01" class="text-right text-sm" />
                            </div>

                            <!-- Remove -->
                            <div class="col-span-1 flex justify-center">
                                <button
                                    type="button"
                                    class="rounded p-1 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600"
                                    :disabled="form.fee_items.length === 1"
                                    @click="removeFeeRow(idx)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="form.fee_items.length === 0" class="rounded-lg border border-dashed py-8 text-center text-sm text-gray-400">
                            No fee lines. Click "Add Line" to start.
                        </div>

                        <p v-if="err('fee_items')" class="text-xs text-red-600">{{ err('fee_items') }}</p>

                        <!-- Sub-totals -->
                        <div class="mt-4 space-y-1 border-t pt-4 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <span>Tuition</span>
                                <span>{{ formatCurrency(tuitionTotal) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Other Fees</span>
                                <span>{{ formatCurrency(otherTotal) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════════ -->
                <!-- Grand Total banner                                        -->
                <!-- ══════════════════════════════════════════════════════════ -->
                <div class="rounded-lg bg-gradient-to-r from-blue-600 to-blue-700 p-5 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium tracking-widest text-blue-200 uppercase">Total Assessment</p>
                            <p class="text-4xl font-bold">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                        <div class="text-right text-sm text-blue-200">
                            <p>Tuition: {{ formatCurrency(tuitionTotal) }}</p>
                            <p>Other: {{ formatCurrency(otherTotal) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Change warning -->
                <div v-if="totalChanged" class="rounded-lg border-2 border-amber-200 bg-amber-50 px-5 py-4">
                    <p class="mb-1 font-semibold text-amber-800">⚠ Assessment Total Changed</p>
                    <div class="space-y-0.5 text-sm text-amber-700">
                        <p>Previous: {{ formatCurrency(Number(assessment.total_assessment)) }}</p>
                        <p>New: {{ formatCurrency(grandTotal) }}</p>
                        <p class="font-semibold">
                            Difference:
                            <span :class="grandTotal > assessment.total_assessment ? 'text-red-600' : 'text-green-700'">
                                {{ grandTotal > assessment.total_assessment ? '+' : ''
                                }}{{ formatCurrency(grandTotal - assessment.total_assessment) }}
                            </span>
                        </p>
                    </div>
                    <p class="mt-2 text-xs text-amber-600">Payment terms will be recalculated proportionally on save.</p>
                </div>

                <!-- ── Actions ──────────────────────────────────────────────── -->
                <div class="flex items-center justify-between gap-4 border-t pt-4">
                    <Link :href="route('student-fees.show', student.id)">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing" class="flex min-w-[180px] items-center justify-center gap-2">
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Saving…' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
