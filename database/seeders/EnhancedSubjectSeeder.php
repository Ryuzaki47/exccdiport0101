<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * EnhancedSubjectSeeder
 *
 * Populates the subjects table with the REAL CCDI curriculum for AY 2025-2026.
 * Source: CCDI OBE Curriculum documents (BSEET and BSEECT program structures).
 *
 * BILLING MODEL (from Rate of Conduct of Consultation, April 2025):
 *   - price_per_unit = ₱364.00  (tuition per unit)
 *   - lab_fee        = ₱1,656.00 per lab subject (has_lab = true)
 *   - Total per subject = (units × 364) + (has_lab ? 1656 : 0)
 *
 * NOTE ON NON-TUITION SUBJECTS:
 *   PATHFIT (Movement Competency Training) and NSTP are NOT included here.
 *   They are non-tuition subjects per CHED rules — no tuition or lab fee applies.
 *   They do NOT appear in assessments.
 *
 * COURSES COVERED:
 *   1. BS Electrical Engineering Technology (BSEET)
 *   2. BS Electronics Engineering Technology (BSEECT)
 *
 * To add subjects for a new course, follow the same pattern:
 *   ['code' => 'CODE', 'name' => 'Name', 'units' => N, 'has_lab' => bool,
 *    'course' => 'Full Course Name', 'year_level' => 'Nth Year', 'semester' => 'Nth Sem']
 */
class EnhancedSubjectSeeder extends Seeder
{
    private float $pricePerUnit = 364.00;
    private float $labFee       = 1656.00;

    public function run(): void
    {
        $this->command->info('📚 Seeding Subjects table with CCDI AY 2025-2026 curriculum…');
        Subject::query()->delete();

        foreach ($this->subjects() as $s) {
            Subject::create(array_merge([
                'price_per_unit' => $this->pricePerUnit,
                'has_lab'        => false,
                'lab_fee'        => 0,
                'description'    => null,
                'is_active'      => true,
            ], $s));
        }

        $this->command->info('✓ Subjects seeded: ' . Subject::count() . ' records.');
        $this->command->table(
            ['Course', 'Year', 'Semester', 'Count'],
            Subject::selectRaw('course, year_level, semester, COUNT(*) as count')
                ->groupBy('course', 'year_level', 'semester')
                ->orderBy('course')
                ->orderByRaw("FIELD(year_level,'1st Year','2nd Year','3rd Year','4th Year')")
                ->orderByRaw("FIELD(semester,'1st Sem','2nd Sem')")
                ->get()
                ->map(fn ($r) => [$r->course, $r->year_level, $r->semester, $r->count])
                ->toArray()
        );
    }

    private function subjects(): array
    {
        return [

            // ═══════════════════════════════════════════════════════════════════
            // BS ELECTRICAL ENGINEERING TECHNOLOGY (BSEET)
            // Major: Electrical Engineering Technology
            // Source: CCDI OBE Curriculum Document, AY 2025-2026
            // ═══════════════════════════════════════════════════════════════════

            // ─── BSEET — 1st Year, 1st Semester ────────────────────────────────
            // From Image 1: GE1, GE Elect1, GE2, GE3, Math101, PHYS101, Comp101
            // PATHFIT1 and NSTP1 are excluded (non-tuition per CHED rules)
            ['code' => 'GE-1',       'name' => 'Purposive Communication',                  'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'GE-ELEC1',   'name' => 'Living in the IT Era',                     'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'GE-2',       'name' => 'Mathematics in the Modern World',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'GE-3',       'name' => 'Science, Technology & Society',             'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'MATH-101',   'name' => 'Calculus 1 — Differential Calculus',       'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'PHYS-101',   'name' => 'Physics for Engineering Technologists',     'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'COMP-101',   'name' => 'Integrated Software Applications 1',        'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],

            // ─── BSEET — 1st Year, 2nd Semester ────────────────────────────────
            // From Image 1: GE Elect2, GE4, Math102, Chem101, FDB, Comp102, CAD1
            // PATHFIT2 and NSTP2 excluded (non-tuition)
            ['code' => 'GE-ELEC2',   'name' => 'Peace Studies and Education',               'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-4',       'name' => 'The Contemporary World',                    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'MATH-102',   'name' => 'Calculus 2 — Integral Calculus',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'CHEM-101',   'name' => 'Chemistry for Engineering Technologists',   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'FDB',        'name' => 'Fundamentals of Deformable Bodies',         'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'COMP-102',   'name' => 'Integrated Software Applications 2',        'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'CAD-1',      'name' => 'Computer-Aided Drafting',                   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],

            // ─── BSEET — 2nd Year, 1st Semester ────────────────────────────────
            ['code' => 'BSEET-201',  'name' => 'Differential Equations',                   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-202',  'name' => 'Basic Electrical Engineering',              'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-203',  'name' => 'AC Circuits Analysis',                     'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-204',  'name' => 'Electrical Wiring & Installation',         'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-205',  'name' => 'Engineering Materials',                    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'GE-5',       'name' => 'Readings in Philippine History',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],

            // ─── BSEET — 2nd Year, 2nd Semester ────────────────────────────────
            ['code' => 'BSEET-211',  'name' => 'Electrical Machines 1',                    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-212',  'name' => 'Electronics 1',                            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-213',  'name' => 'Instrumentation & Measurement',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-214',  'name' => 'Engineering Economy',                      'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-6',       'name' => 'Ethics',                                   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-7',       'name' => 'Art Appreciation',                         'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],

            // ─── BSEET — 3rd Year, 1st Semester ────────────────────────────────
            ['code' => 'BSEET-301',  'name' => 'Electrical Machines 2',                    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-302',  'name' => 'Power Systems Analysis',                   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-303',  'name' => 'Industrial Electronics',                   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-304',  'name' => 'Control Systems',                          'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-305',  'name' => 'Electrical Safety & Standards',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-306',  'name' => 'Technical Report Writing',                 'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],

            // ─── BSEET — 3rd Year, 2nd Semester ────────────────────────────────
            ['code' => 'BSEET-311',  'name' => 'Electrical Design & Drafting',             'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-312',  'name' => 'PLC & Automation',                         'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-313',  'name' => 'Building Electrical Systems',              'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-314',  'name' => 'Renewable Energy Systems',                 'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-315',  'name' => 'Special Topics in EET',                   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],

            // ─── BSEET — 4th Year, 1st Semester ────────────────────────────────
            ['code' => 'BSEET-401',  'name' => 'Capstone Project 1',                       'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-402',  'name' => 'High Voltage Engineering',                 'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-403',  'name' => 'Power Electronics',                        'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEET-404',  'name' => 'Electrical Project Management',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-405',  'name' => 'On-the-Job Training Preparation',          'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],

            // ─── BSEET — 4th Year, 2nd Semester ────────────────────────────────
            ['code' => 'BSEET-411',  'name' => 'Capstone Project 2',                       'units' => 6, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-412',  'name' => 'On-the-Job Training',                      'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-413',  'name' => 'Professional Practice & Ethics',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],


            // ═══════════════════════════════════════════════════════════════════
            // BS ELECTRONICS ENGINEERING TECHNOLOGY (BSEECT)
            // Major: Electronics Engineering Technology
            // Source: CCDI OBE Curriculum Document, AY 2025-2026
            // ═══════════════════════════════════════════════════════════════════

            // ─── BSEECT — 1st Year, 1st Semester ───────────────────────────────
            // From Image 2: GE1, GE Elect1, GE2, GE3, ELXT110, Math101, PHYS101, Comp101
            // PATHFIT1 and NSTP1 excluded (non-tuition)
            ['code' => 'BSEECT-GE1',   'name' => 'Purposive Communication',                'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-GEE1',  'name' => 'Living in the IT Era',                   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-GE2',   'name' => 'Mathematics in the Modern World',         'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-GE3',   'name' => 'Science, Technology & Society',           'units' => 4, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'ELXT-110',     'name' => 'Basic Electricity and Electronics',       'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-M101',  'name' => 'Calculus 1 — Differential Calculus',     'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-P101',  'name' => 'Physics for Engineering Technologists',   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-C101',  'name' => 'Integrated Software Applications',        'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],

            // ─── BSEECT — 1st Year, 2nd Semester ───────────────────────────────
            // From Image 2: GE Elect2, ECE111, EE110, Chem101, Math102, Comp102, CAD1
            ['code' => 'BSEECT-GEE2',  'name' => 'Peace Studies and Education',             'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'ECE-111',      'name' => 'Electronics 1: Electronics Devices and Circuits', 'units' => 4, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'EE-110',       'name' => 'DC and AC Circuits',                      'units' => 4, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-C101A', 'name' => 'Chemistry for Engineering Technologist',  'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-M102',  'name' => 'Calculus 2 — Integral Calculus',          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-C102',  'name' => 'Integrated Software Applications 2',      'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-CAD',   'name' => 'Computer-Aided Drafting',                 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],

            // ─── BSEECT — 2nd Year, 1st Semester ───────────────────────────────
            ['code' => 'BSEECT-201',   'name' => 'Differential Equations',                  'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-202',   'name' => 'Electronic Circuits 1',                   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-203',   'name' => 'Digital Electronics',                     'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-204',   'name' => 'Signals & Systems',                       'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-205',   'name' => 'Electronic Measurements',                 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-GE5',   'name' => 'Readings in Philippine History',          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],

            // ─── BSEECT — 2nd Year, 2nd Semester ───────────────────────────────
            ['code' => 'BSEECT-211',   'name' => 'Electronic Circuits 2',                   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-212',   'name' => 'Microprocessors & Microcontrollers',      'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-213',   'name' => 'Communications Electronics',              'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-214',   'name' => 'Engineering Economy',                     'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-GE6',   'name' => 'Ethics',                                  'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-GE7',   'name' => 'Art Appreciation',                        'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],

            // ─── BSEECT — 3rd Year, 1st Semester ───────────────────────────────
            ['code' => 'BSEECT-301',   'name' => 'RF & Microwave Engineering',              'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-302',   'name' => 'Embedded Systems',                        'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-303',   'name' => 'Digital Signal Processing',               'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-304',   'name' => 'Industrial Electronics',                  'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-GE8',   'name' => 'Science, Technology & Society',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-305',   'name' => 'Technical Report Writing',                'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],

            // ─── BSEECT — 3rd Year, 2nd Semester ───────────────────────────────
            ['code' => 'BSEECT-311',   'name' => 'Fiber Optics & Photonics',                'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-312',   'name' => 'Wireless Communications',                 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-313',   'name' => 'IoT & Smart Systems',                    'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-314',   'name' => 'Special Topics in EECT',                 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],

            // ─── BSEECT — 4th Year, 1st Semester ───────────────────────────────
            ['code' => 'BSEECT-401',   'name' => 'Capstone Project 1',                      'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-402',   'name' => 'Advanced Communications Systems',         'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1656.00],
            ['code' => 'BSEECT-403',   'name' => 'Electronic Project Management',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-404',   'name' => 'On-the-Job Training Preparation',         'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],

            // ─── BSEECT — 4th Year, 2nd Semester ───────────────────────────────
            ['code' => 'BSEECT-411',   'name' => 'Capstone Project 2',                      'units' => 6, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-412',   'name' => 'On-the-Job Training',                     'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-413',   'name' => 'Professional Practice & Ethics',          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],

        ];
    }
}