<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, ChevronDown, ChevronRight, PenLine, Search, Trash2, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Student {
    id: number;
    account_id: string;
    name: string;
    email: string;
    course: string | null;
    year_level: string | null;
    status: string;
    is_irregular: boolean;
    suggested_year_level: string | null;
    suggested_semester: string | null;
    latest_assessment: { year_level: string; semester: string; school_year: string } | null;
}

interface SubjectItem {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
    year_level: string;
    semester: string;
}

interface MiscItem {
    name: string;
    category: string;
    amount: number;
}

// subjectMap[course][yearLevel][semester] = SubjectItem[]
type SubjectMap = Record<string, Record<string, Record<string, SubjectItem[]>>>;

interface Props {
    students:          Student[];
    yearLevels:        string[];
    semesters:         string[];
    schoolYears:       string[];
    subjectMap:        SubjectMap;
    courses:           string[];
    // New props from updated controller — safe defaults prevent crash if old controller is active
    tuitionPerUnit?:   number;
    labFeePerSubject?: number;
    miscItems?:        MiscItem[];
    miscTotal?:        number;
}

const props = withDefaults(defineProps<Props>(), {
    tuitionPerUnit:   364.00,
    labFeePerSubject: 1656.00,
    miscItems:        () => [],
    miscTotal:        6956.00,
});

// ─── Breadcrumbs ──────────────────────────────────────────────────────────────

const breadcrumbs = [
    { title: 'Dashboard',              href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Create Assessment' },
];

// ─── Step & Student ───────────────────────────────────────────────────────────

const currentStep     = ref<1 | 2>(1);
const selectedStudent = ref<Student | null>(null);
const studentSearch   = ref('');

// ─── Inline course editing ────────────────────────────────────────────────────

const editableCourse = ref<string>('');

const needsCourse = computed<boolean>(() => {
    const c = selectedStudent.value?.course;
    return !c || c.trim() === '' || c === 'N/A';
});

// The effective course for subject browsing
const activeCourse = computed(() =>
    needsCourse.value ? editableCourse.value : (selectedStudent.value?.course ?? '')
);

// ─── Assessment type ──────────────────────────────────────────────────────────

const assessmentType = ref<'regular' | 'irregular'>('regular');

// ─── Term fields ──────────────────────────────────────────────────────────────

const yearLevel        = ref('');
const semester         = ref('');
const schoolYear       = ref(props.schoolYears[2] || '');
const customSchoolYear = ref('');
const useCustomYear    = ref(false);

const effectiveSchoolYear = computed(() =>
    useCustomYear.value ? customSchoolYear.value.trim() : schoolYear.value,
);

const customYearValid = computed(() => {
    if (!useCustomYear.value) return true;
    return /^\d{4}-\d{4}$/.test(customSchoolYear.value.trim());
});

// ─── Subject selection ────────────────────────────────────────────────────────

const selectedSubjectIds = ref<number[]>([]);
const subjectSearch      = ref('');
const expandedGroups     = ref<Set<string>>(new Set());

// For regular: subjects from student's own course × year × semester
// For irregular: subjects from any course the picker browses

// ── Irregular picker state ────────────────────────────────────────────────────
const pickerCourse    = ref('');
const pickerYearLevel = ref('');
const pickerSemester  = ref('');
const pickerExpanded  = ref(false);

// Subjects available in the irregular picker combination
const pickerSubjects = computed<SubjectItem[]>(() => {
    if (!pickerCourse.value || !pickerYearLevel.value || !pickerSemester.value) return [];
    return props.subjectMap?.[pickerCourse.value]?.[pickerYearLevel.value]?.[pickerSemester.value] ?? [];
});

// ── Regular: subjects for current student/term ────────────────────────────────
// Grouped accordion for the regular subject browser
const regularGroups = computed(() => {
    const course = activeCourse.value;
    if (!course || !props.subjectMap[course]) return {};
    const search = subjectSearch.value.toLowerCase();
    const result: Record<string, Record<string, SubjectItem[]>> = {};
    for (const yl of Object.keys(props.subjectMap[course])) {
        for (const sem of Object.keys(props.subjectMap[course][yl])) {
            const items = props.subjectMap[course][yl][sem].filter(
                (s) => !search || s.code.toLowerCase().includes(search) || s.name.toLowerCase().includes(search),
            );
            if (!items.length) continue;
            if (!result[yl]) result[yl] = {};
            result[yl][sem] = items;
        }
    }
    return result;
});

// All selected SubjectItem objects (for display in summary)
const selectedSubjects = computed<SubjectItem[]>(() => {
    const all: SubjectItem[] = [];
    for (const course of Object.keys(props.subjectMap)) {
        for (const yl of Object.keys(props.subjectMap[course])) {
            for (const sem of Object.keys(props.subjectMap[course][yl])) {
                for (const s of props.subjectMap[course][yl][sem]) {
                    if (selectedSubjectIds.value.includes(s.id)) all.push(s);
                }
            }
        }
    }
    return all;
});

function isSelected(id: number) {
    return selectedSubjectIds.value.includes(id);
}

function toggleSubject(id: number) {
    if (isSelected(id)) {
        selectedSubjectIds.value = selectedSubjectIds.value.filter((x) => x !== id);
    } else {
        selectedSubjectIds.value.push(id);
    }
}

function removeSubject(id: number) {
    selectedSubjectIds.value = selectedSubjectIds.value.filter((x) => x !== id);
}

function addAllPickerSubjects() {
    for (const s of pickerSubjects.value) {
        if (!isSelected(s.id)) selectedSubjectIds.value.push(s.id);
    }
}

function toggleGroup(key: string) {
    if (expandedGroups.value.has(key)) expandedGroups.value.delete(key);
    else expandedGroups.value.add(key);
}

// ── Pre-load regular subjects for current term ────────────────────────────────

function preloadRegularSubjects() {
    if (!activeCourse.value || !yearLevel.value || !semester.value) return;
    const subjectsForTerm =
        props.subjectMap?.[activeCourse.value]?.[yearLevel.value]?.[semester.value] ?? [];
    selectedSubjectIds.value = subjectsForTerm.map((s) => s.id);
    // Auto-expand current term group
    const key = `${yearLevel.value}||${semester.value}`;
    expandedGroups.value = new Set([key]);
}

// Watch year/semester changes for regular mode
watch([yearLevel, semester], () => {
    if (assessmentType.value === 'regular') {
        selectedSubjectIds.value = [];
        expandedGroups.value     = new Set();
        preloadRegularSubjects();
    }
});

// When switching to irregular, clear preloaded selection
watch(assessmentType, (newType) => {
    selectedSubjectIds.value = [];
    expandedGroups.value     = new Set();
    subjectSearch.value      = '';
    if (newType === 'irregular') {
        // Pre-populate picker with student's course
        pickerCourse.value    = activeCourse.value;
        pickerYearLevel.value = yearLevel.value;
        pickerSemester.value  = semester.value;
        pickerExpanded.value  = true;
    } else {
        preloadRegularSubjects();
    }
});

// ─── Fee calculation ──────────────────────────────────────────────────────────

const tuitionTotal = computed(() =>
    selectedSubjects.value.reduce((sum, s) => sum + s.units * props.tuitionPerUnit, 0)
);

const labTotal = computed(() =>
    selectedSubjects.value.filter((s) => s.has_lab).length * props.labFeePerSubject
);

const grandTotal = computed(() =>
    tuitionTotal.value + labTotal.value + props.miscTotal
);

const totalUnits = computed(() =>
    selectedSubjects.value.reduce((sum, s) => sum + s.units, 0)
);

const labSubjectCount = computed(() =>
    selectedSubjects.value.filter((s) => s.has_lab).length
);

// ─── Student list filtering ───────────────────────────────────────────────────

const filteredStudents = computed(() => {
    if (!studentSearch.value) return props.students;
    const s = studentSearch.value.toLowerCase();
    return props.students.filter(
        (st) =>
            st.account_id.toLowerCase().includes(s) ||
            st.name.toLowerCase().includes(s) ||
            st.email.toLowerCase().includes(s) ||
            (st.course ?? '').toLowerCase().includes(s),
    );
});

// ─── Student selection ────────────────────────────────────────────────────────

function selectStudent(student: Student) {
    selectedStudent.value    = student;
    const existingCourse     = student.course;
    editableCourse.value     = (existingCourse && existingCourse !== 'N/A') ? existingCourse : '';
    yearLevel.value          = student.suggested_year_level || student.year_level || '';
    if (student.suggested_semester) semester.value = student.suggested_semester;
    assessmentType.value     = student.is_irregular ? 'irregular' : 'regular';
    selectedSubjectIds.value = [];
    expandedGroups.value     = new Set();
    subjectSearch.value      = '';
    pickerCourse.value       = needsCourse.value ? '' : (student.course ?? '');
    pickerYearLevel.value    = '';
    pickerSemester.value     = '';
    pickerExpanded.value     = false;
    currentStep.value        = 2;
}

function backToStudentSelection() {
    currentStep.value        = 1;
    selectedStudent.value    = null;
    editableCourse.value     = '';
    selectedSubjectIds.value = [];
    yearLevel.value          = '';
    semester.value           = '';
    expandedGroups.value     = new Set();
    pickerCourse.value       = '';
    pickerYearLevel.value    = '';
    pickerSemester.value     = '';
    pickerExpanded.value     = false;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

const form: any  = useForm({});
const formErrors = ref<Record<string, string>>({});

function submit() {
    formErrors.value = {};

    if (!yearLevel.value || !semester.value) {
        formErrors.value.term = 'Please select a year level and semester.';
        return;
    }

    if (useCustomYear.value && !customYearValid.value) {
        formErrors.value.school_year = 'School year must be in YYYY-YYYY format (e.g. 2025-2026).';
        return;
    }

    if (needsCourse.value && !editableCourse.value.trim()) {
        formErrors.value.course = 'Please assign a course to this student before creating an assessment.';
        return;
    }

    if (selectedSubjectIds.value.length === 0) {
        formErrors.value.selected_subjects = 'Please select at least one subject.';
        return;
    }

    const payload = {
        user_id:             selectedStudent.value!.id,
        year_level:          yearLevel.value,
        semester:            semester.value,
        school_year:         effectiveSchoolYear.value,
        assessment_type:     assessmentType.value,
        course:              needsCourse.value ? editableCourse.value.trim() : (selectedStudent.value!.course ?? ''),
        selected_subjects:   selectedSubjectIds.value,
    };

    form.transform(() => payload).post(route('student-fees.store'), {
        preserveScroll: true,
        onError: (errors: any) => { formErrors.value = errors; },
    });
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmt = (n: number) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(n);

function statusColor(status: string) {
    return status === 'active' ? 'bg-green-100 text-green-800'
         : status === 'graduated' ? 'bg-blue-100 text-blue-800'
         : 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <Head title="Create Student Assessment" />

    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.index')">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" /> Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold">Create Student Assessment</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ currentStep === 1 ? 'Step 1 of 2 — Select Student' : 'Step 2 of 2 — Configure Assessment' }}
                    </p>
                </div>
            </div>

            <!-- Step indicator -->
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold',
                                  currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500']">1</div>
                    <span class="text-sm font-medium">Select Student</span>
                </div>
                <div class="h-px w-20 bg-gray-200">
                    <div :class="['h-full transition-all duration-300', currentStep >= 2 ? 'w-full bg-blue-600' : 'w-0']" />
                </div>
                <div class="flex items-center gap-2">
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold',
                                  currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500']">2</div>
                    <span class="text-sm font-medium">Select Subjects</span>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════
                 STEP 1 — Student Selection
                 ════════════════════════════════════════════════ -->
            <div v-if="currentStep === 1" class="space-y-4">
                <div class="rounded-lg border bg-white p-4 shadow-sm">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <Input v-model="studentSearch" placeholder="Search by ID, name, email, or course…" class="pl-9" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-6 py-4">
                        <h2 class="font-semibold">Active Students</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Click a row to select</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                                <tr>
                                    <th class="px-5 py-3 text-left">Account ID</th>
                                    <th class="px-5 py-3 text-left">Name</th>
                                    <th class="px-5 py-3 text-left">Course</th>
                                    <th class="px-5 py-3 text-left">Year</th>
                                    <th class="px-5 py-3 text-left">Type</th>
                                    <th class="px-5 py-3 text-left">Status</th>
                                    <th class="px-5 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-if="filteredStudents.length === 0">
                                    <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                                        <User class="mx-auto mb-2 h-10 w-10 text-gray-300" />
                                        <p>No students found</p>
                                    </td>
                                </tr>
                                <tr v-for="st in filteredStudents" :key="st.id"
                                    class="cursor-pointer transition-colors hover:bg-blue-50"
                                    @click="selectStudent(st)">
                                    <td class="px-5 py-3 font-mono text-xs font-medium text-gray-900">{{ st.account_id }}</td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900">{{ st.name }}</div>
                                        <div class="text-xs text-gray-400">{{ st.email }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-gray-600">{{ st.course || '—' }}</td>
                                    <td class="px-5 py-3 text-xs">
                                        <span v-if="st.suggested_year_level && st.suggested_year_level !== st.year_level"
                                              class="font-semibold text-blue-700">{{ st.suggested_year_level }}</span>
                                        <span v-else class="text-gray-600">{{ st.year_level || '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold',
                                                       st.is_irregular ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700']">
                                            {{ st.is_irregular ? 'Irregular' : 'Regular' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusColor(st.status)">
                                            {{ st.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <Button size="sm" @click.stop="selectStudent(st)">Select</Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════
                 STEP 2 — Assessment Configuration
                 ════════════════════════════════════════════════ -->
            <div v-if="currentStep === 2" class="space-y-5">

                <!-- Student banner -->
                <div class="rounded-lg border-2 border-blue-200 bg-blue-50 px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-gray-500">Account ID</p>
                                <p class="font-semibold text-gray-900">{{ selectedStudent?.account_id }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Name</p>
                                <p class="font-semibold text-gray-900">{{ selectedStudent?.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">
                                    Course
                                    <span v-if="needsCourse" class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-amber-700 font-semibold text-xs">Required</span>
                                </p>
                                <p v-if="!needsCourse" class="font-semibold text-gray-900">{{ selectedStudent?.course }}</p>
                                <div v-else class="mt-1">
                                    <select v-model="editableCourse"
                                            class="w-full rounded-md border border-amber-400 bg-white px-2 py-1.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-400">
                                        <option value="">— Select Course —</option>
                                        <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                                    </select>
                                    <p v-if="formErrors.course" class="mt-1 text-xs text-red-600">{{ formErrors.course }}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Current Year</p>
                                <p class="font-semibold text-gray-900">
                                    {{ selectedStudent?.latest_assessment?.year_level || selectedStudent?.year_level || '—' }}
                                </p>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="backToStudentSelection" class="flex-shrink-0">Change</Button>
                    </div>
                    <div class="mt-3 border-t border-blue-200 pt-3 flex flex-wrap items-center gap-4 text-xs">
                        <div v-if="selectedStudent?.latest_assessment">
                            <span class="text-gray-500">Last Assessment: </span>
                            <span class="font-semibold text-gray-700">
                                {{ selectedStudent.latest_assessment.year_level }}
                                {{ selectedStudent.latest_assessment.semester }}
                                {{ selectedStudent.latest_assessment.school_year }}
                            </span>
                        </div>
                        <div v-else class="text-gray-400">No previous assessment on record.</div>
                        <div v-if="selectedStudent?.suggested_year_level"
                             class="rounded-full bg-green-100 px-3 py-1 text-green-700 font-medium">
                            ✓ Auto-filled: {{ selectedStudent.suggested_year_level }}
                            <span v-if="selectedStudent.suggested_semester"> · {{ selectedStudent.suggested_semester }}</span>
                        </div>
                    </div>
                </div>

                <!-- Rate info banner -->
                <div class="rounded-lg border border-blue-100 bg-blue-50 px-5 py-3 text-xs text-blue-800 flex flex-wrap gap-6">
                    <span>Tuition rate: <strong>₱{{ (tuitionPerUnit ?? 364).toLocaleString('en-PH') }}/unit</strong></span>
                    <span>Lab fee: <strong>₱{{ (labFeePerSubject ?? 1656).toLocaleString('en-PH') }}/lab subject</strong></span>
                    <span>Fixed misc: <strong>₱{{ (miscTotal ?? 6956).toLocaleString('en-PH', {minimumFractionDigits:2}) }}/semester</strong></span>
                    <span class="text-blue-500">Rate of Conduct of Consultation, April 2025 (AY 2025-2026)</span>
                </div>

                <!-- Term Information -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <h2 class="mb-4 font-semibold text-gray-900">Term Information</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="space-y-1">
                            <Label>Year Level</Label>
                            <select v-model="yearLevel" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Select year level</option>
                                <option v-for="y in yearLevels" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label>Semester</Label>
                            <select v-model="semester" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Select semester</option>
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label class="flex items-center gap-2">
                                School Year
                                <button type="button"
                                        :class="['rounded px-1.5 py-0.5 text-xs font-medium transition-colors',
                                                 useCustomYear ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']"
                                        @click="useCustomYear = !useCustomYear; customSchoolYear = ''">
                                    <PenLine class="inline h-3 w-3 mr-0.5" />
                                    {{ useCustomYear ? 'Custom ✓' : 'Custom' }}
                                </button>
                            </Label>
                            <select v-if="!useCustomYear" v-model="schoolYear"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option v-for="sy in schoolYears" :key="sy" :value="sy">{{ sy }}</option>
                            </select>
                            <div v-else>
                                <input v-model="customSchoolYear" type="text" placeholder="e.g. 2026-2027"
                                       :class="['w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2',
                                                customYearValid ? 'border-blue-400 focus:border-blue-500 focus:ring-blue-200' : 'border-red-400 focus:border-red-500 focus:ring-red-200']" />
                                <p v-if="!customYearValid && customSchoolYear" class="mt-1 text-xs text-red-500">Format: YYYY-YYYY</p>
                            </div>
                            <p v-if="formErrors.school_year" class="mt-1 text-xs text-red-500">{{ formErrors.school_year }}</p>
                        </div>
                    </div>
                    <p v-if="formErrors.term" class="mt-2 text-xs text-red-500">{{ formErrors.term }}</p>
                </div>

                <!-- Assessment Type Toggle -->
                <div class="rounded-lg border bg-white p-5 shadow-sm">
                    <h2 class="mb-1 font-semibold text-gray-900">Assessment Type</h2>
                    <p class="mb-4 text-xs text-gray-500">Regular = standard full-term subject load. Irregular = custom mix of subjects across courses.</p>
                    <div class="flex gap-3">
                        <button type="button"
                                :class="['flex-1 rounded-lg border-2 px-5 py-4 text-left transition-all',
                                         assessmentType === 'regular' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300']"
                                @click="assessmentType = 'regular'">
                            <p :class="['font-bold', assessmentType === 'regular' ? 'text-blue-700' : 'text-gray-700']">Regular</p>
                            <p class="mt-0.5 text-xs text-gray-500">All subjects for the student's course, year, and semester are pre-selected. Remove any they are not taking.</p>
                        </button>
                        <button type="button"
                                :class="['flex-1 rounded-lg border-2 px-5 py-4 text-left transition-all',
                                         assessmentType === 'irregular' ? 'border-amber-500 bg-amber-50' : 'border-gray-200 hover:border-gray-300']"
                                @click="assessmentType = 'irregular'">
                            <p :class="['font-bold', assessmentType === 'irregular' ? 'text-amber-700' : 'text-gray-700']">Irregular</p>
                            <p class="mt-0.5 text-xs text-gray-500">Manually pick subjects from any course. Use this when a student takes subjects from multiple programs.</p>
                        </button>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     REGULAR — subject browser from student's course
                     ════════════════════════════════════════ -->
                <div v-if="assessmentType === 'regular'" class="rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-4 w-4 text-blue-500" />
                            <h2 class="font-semibold text-gray-900">Subject Selection</h2>
                        </div>
                        <p v-if="activeCourse && yearLevel && semester" class="text-xs text-green-600">
                            ✓ Pre-selected — uncheck any subject the student is NOT taking
                        </p>
                        <p v-else class="text-xs text-gray-400">Select year level and semester to load subjects</p>
                    </div>

                    <div class="border-b px-5 py-3">
                        <div class="relative">
                            <Search class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <input v-model="subjectSearch" type="text" placeholder="Search subjects…"
                                   class="w-full rounded-lg border border-gray-200 py-1.5 pr-3 pl-8 text-sm outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200" />
                        </div>
                    </div>

                    <div v-if="!activeCourse || !yearLevel || !semester" class="px-5 py-10 text-center text-sm text-gray-400">
                        Select course, year level, and semester above to load subjects.
                    </div>
                    <div v-else-if="Object.keys(regularGroups).length === 0" class="px-5 py-10 text-center text-sm text-gray-400">
                        No subjects found for <strong>{{ activeCourse }}</strong>. Run the subject seeder.
                    </div>
                    <div v-else class="divide-y divide-gray-100">
                        <template v-for="(bySem, yl) in regularGroups" :key="yl">
                            <template v-for="(subjects, sem) in bySem" :key="sem">
                                <button type="button"
                                        :class="['flex w-full items-center justify-between px-5 py-2.5 text-left transition-colors hover:bg-gray-100',
                                                 yl === yearLevel && sem === semester ? 'bg-blue-50' : 'bg-gray-50']"
                                        @click="toggleGroup(`${yl}||${sem}`)">
                                    <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        {{ yl }} — {{ sem }}
                                        <span v-if="yl === yearLevel && sem === semester"
                                              class="rounded-full bg-blue-200 px-1.5 py-0.5 text-blue-700 normal-case font-normal">current term</span>
                                        <span class="font-normal text-gray-400">({{ subjects.length }})</span>
                                    </span>
                                    <ChevronDown v-if="expandedGroups.has(`${yl}||${sem}`)" class="h-4 w-4 text-gray-400" />
                                    <ChevronRight v-else class="h-4 w-4 text-gray-400" />
                                </button>
                                <div v-if="expandedGroups.has(`${yl}||${sem}`)" class="divide-y divide-gray-50">
                                    <div v-for="s in subjects" :key="s.id"
                                         :class="['flex cursor-pointer items-center justify-between px-5 py-3 transition-colors',
                                                  isSelected(s.id) ? 'bg-blue-50' : 'hover:bg-gray-50']"
                                         @click="toggleSubject(s.id)">
                                        <div class="flex items-start gap-3">
                                            <div :class="['mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border text-xs font-bold',
                                                          isSelected(s.id) ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 bg-white']">
                                                <span v-if="isSelected(s.id)">✓</span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ s.code }} — {{ s.name }}</p>
                                                <p class="text-xs text-gray-400">
                                                    {{ s.units }} units × {{ fmt(s.price_per_unit) }}
                                                    = {{ fmt(s.units * s.price_per_unit) }}
                                                    <span v-if="s.has_lab" class="ml-1 text-purple-600">+ Lab {{ fmt(s.lab_fee) }}</span>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="ml-4 flex-shrink-0 text-sm font-semibold text-blue-700">{{ fmt(s.total_cost) }}</span>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     IRREGULAR — multi-course subject picker
                     ════════════════════════════════════════ -->
                <div v-if="assessmentType === 'irregular'" class="rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-5 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-4 w-4 text-amber-500" />
                            <h2 class="font-semibold text-gray-900">Subject Picker — Irregular</h2>
                        </div>
                        <span class="text-xs text-amber-700">Select subjects from any course, year, and semester</span>
                    </div>

                    <!-- Picker selectors -->
                    <div class="border-b bg-amber-50 px-5 py-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-amber-800">Browse Subjects by Course</p>
                            <button type="button" class="text-xs text-amber-700 underline hover:no-underline"
                                    @click="pickerExpanded = !pickerExpanded">
                                {{ pickerExpanded ? 'Collapse' : 'Expand' }}
                            </button>
                        </div>

                        <div v-if="pickerExpanded" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Course</label>
                                <select v-model="pickerCourse"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200">
                                    <option value="">— Select Course —</option>
                                    <option v-for="c in courses" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Year Level</label>
                                <select v-model="pickerYearLevel"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200">
                                    <option value="">— Select Year —</option>
                                    <option v-for="y in yearLevels" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Semester</label>
                                <select v-model="pickerSemester"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-200">
                                    <option value="">— Select Semester —</option>
                                    <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Subjects from selected picker combination -->
                        <div v-if="pickerExpanded && pickerSubjects.length > 0" class="rounded-lg border border-amber-200 overflow-hidden">
                            <div class="flex items-center justify-between bg-amber-100 px-4 py-2">
                                <span class="text-xs font-semibold text-amber-800">
                                    {{ pickerCourse }} · {{ pickerYearLevel }} · {{ pickerSemester }}
                                    ({{ pickerSubjects.length }} subjects)
                                </span>
                                <button type="button" class="text-xs font-medium text-amber-700 underline hover:no-underline"
                                        @click="addAllPickerSubjects">Add All</button>
                            </div>
                            <div class="divide-y divide-amber-100">
                                <div v-for="s in pickerSubjects" :key="s.id"
                                     :class="['flex cursor-pointer items-center justify-between px-4 py-2.5 transition-colors',
                                              isSelected(s.id) ? 'bg-amber-50' : 'bg-white hover:bg-amber-50']"
                                     @click="toggleSubject(s.id)">
                                    <div class="flex items-center gap-3">
                                        <div :class="['flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border text-xs font-bold',
                                                      isSelected(s.id) ? 'border-amber-500 bg-amber-500 text-white' : 'border-gray-300 bg-white']">
                                            <span v-if="isSelected(s.id)">✓</span>
                                        </div>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">{{ s.code }} — {{ s.name }}</span>
                                            <p class="text-xs text-gray-400">
                                                {{ s.units }} units × {{ fmt(s.price_per_unit) }}
                                                <span v-if="s.has_lab" class="text-purple-600"> + Lab {{ fmt(s.lab_fee) }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-amber-700">{{ fmt(s.total_cost) }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else-if="pickerExpanded && pickerCourse && pickerYearLevel && pickerSemester"
                           class="text-xs text-gray-400 italic">No subjects found for this combination.</p>
                        <p v-else-if="!pickerExpanded" class="text-xs text-amber-700">
                            Click "Expand" to browse and select subjects from any course.
                        </p>
                    </div>
                </div>

                <!-- ════════════════════════════════════════
                     SELECTED SUBJECTS SUMMARY (both types)
                     ════════════════════════════════════════ -->
                <div class="rounded-lg border bg-white shadow-sm">
                    <div class="border-b px-5 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="font-semibold text-gray-900">Selected Subjects</h2>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ selectedSubjects.length }} subject{{ selectedSubjects.length !== 1 ? 's' : '' }} ·
                                {{ totalUnits }} units ·
                                {{ labSubjectCount }} lab subject{{ labSubjectCount !== 1 ? 's' : '' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="selectedSubjects.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                        No subjects selected. Use the browser above.
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div v-for="s in selectedSubjects" :key="s.id"
                             class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ s.code }} — {{ s.name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ s.course }} · {{ s.year_level }} · {{ s.semester }} ·
                                    {{ s.units }} units × {{ fmt(s.price_per_unit) }}
                                    <span v-if="s.has_lab" class="text-purple-600"> + Lab {{ fmt(s.lab_fee) }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="font-semibold text-gray-900">{{ fmt(s.total_cost) }}</span>
                                <button type="button" class="text-gray-300 hover:text-red-500 transition-colors"
                                        @click="removeSubject(s.id)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="formErrors.selected_subjects" class="px-5 pb-3 text-xs text-red-500">
                        {{ formErrors.selected_subjects }}
                    </p>
                </div>

                <!-- Fee breakdown summary -->
                <div v-if="selectedSubjects.length > 0" class="rounded-lg border bg-white shadow-sm overflow-hidden">
                    <div class="border-b bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Assessment Breakdown</h2>
                    </div>
                    <div class="px-5 py-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tuition ({{ totalUnits }} units × ₱{{ (tuitionPerUnit ?? 364).toLocaleString('en-PH') }})</span>
                            <span class="font-medium">{{ fmt(tuitionTotal) }}</span>
                        </div>
                        <div v-if="labSubjectCount > 0" class="flex justify-between">
                            <span class="text-gray-600">Laboratory ({{ labSubjectCount }} lab subject{{ labSubjectCount !== 1 ? 's' : '' }} × ₱{{ (labFeePerSubject ?? 1656).toLocaleString('en-PH') }})</span>
                            <span class="font-medium text-purple-700">{{ fmt(labTotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Miscellaneous fees (fixed)</span>
                            <span class="font-medium">{{ fmt(miscTotal) }}</span>
                        </div>
                        <div class="border-t pt-2 flex justify-between font-bold text-base">
                            <span>Total Assessment</span>
                            <span>{{ fmt(grandTotal) }}</span>
                        </div>
                        <!-- Misc breakdown -->
                        <details class="text-xs text-gray-400 mt-1">
                            <summary class="cursor-pointer hover:text-gray-600">View misc breakdown ({{ miscItems.length }} items)</summary>
                            <div class="mt-2 space-y-1 pl-3">
                                <div v-for="item in miscItems" :key="item.name" class="flex justify-between">
                                    <span>{{ item.name }}</span>
                                    <span>{{ fmt(item.amount) }}</span>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Grand Total Banner -->
                <div :class="['rounded-xl px-6 py-5 text-white shadow-lg',
                              assessmentType === 'irregular'
                                ? 'bg-gradient-to-r from-amber-500 to-amber-600'
                                : 'bg-gradient-to-r from-blue-600 to-blue-700']">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="mb-1 text-xs font-medium uppercase tracking-widest opacity-80">Total Assessment Amount</p>
                            <p class="text-4xl font-bold tabular-nums">{{ fmt(grandTotal) }}</p>
                        </div>
                        <div class="space-y-0.5 text-right text-xs opacity-75">
                            <p>{{ assessmentType === 'irregular' ? 'Irregular' : 'Regular' }} Assessment</p>
                            <p>{{ totalUnits }} units · {{ labSubjectCount }} lab subjects</p>
                            <p>School Year: {{ effectiveSchoolYear || '—' }}</p>
                            <p>5 payment terms will be generated</p>
                        </div>
                    </div>
                </div>

                <p v-if="formErrors.error" class="text-sm font-medium text-red-600">{{ formErrors.error }}</p>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-2">
                    <Button type="button" variant="outline" @click="backToStudentSelection">
                        ← Back to Student Selection
                    </Button>
                    <div class="flex gap-3">
                        <Link :href="route('student-fees.index')">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                        <Button
                            type="button"
                            :disabled="form.processing
                                || !yearLevel
                                || !semester
                                || !effectiveSchoolYear
                                || !customYearValid
                                || selectedSubjectIds.length === 0"
                            :class="[assessmentType === 'irregular'
                                        ? 'bg-amber-500 hover:bg-amber-600 text-white border-0'
                                        : '',
                                     'min-w-[180px]']"
                            @click="submit">
                            {{ form.processing ? 'Creating…' : 'Create Assessment' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>