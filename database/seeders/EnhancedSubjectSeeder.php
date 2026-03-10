<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * EnhancedSubjectSeeder
 *
 * Populates the subjects table for Irregular student assessments.
 * Each subject has: code, name, units, price_per_unit, has_lab, lab_fee
 *
 * The Create Assessment form uses this data to let admin pick individual
 * subjects for Irregular students (units × price_per_unit = line total).
 *
 * Courses covered:
 *   - BS Electrical Engineering Technology (BSEET)
 *   - BS Electronics Engineering Technology (BSEECT)
 *
 * To add subjects for another course: follow the same pattern below and
 * add a new entry to $subjects with the matching course name.
 */
class EnhancedSubjectSeeder extends Seeder
{
    // Shared price per unit across both engineering technology courses
    // Adjust per course below if needed.
    private float $pricePerUnit = 600.00;
    private float $labFee       = 1500.00;

    public function run(): void
    {
        $this->command->info('📚 Seeding Subjects table…');
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
            ['Course', 'Count'],
            Subject::selectRaw('course, COUNT(*) as count')
                ->groupBy('course')
                ->get()
                ->map(fn($r) => [$r->course, $r->count])
                ->toArray()
        );
    }

    private function subjects(): array
    {
        return [
            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 1st Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-101', 'name' => 'Engineering Mathematics 1',        'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-102', 'name' => 'Engineering Physics 1',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-103', 'name' => 'Introduction to Computing',        'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-104', 'name' => 'Technical Drawing',                'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'GE-101',    'name' => 'Purposive Communication',          'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'GE-102',    'name' => 'Understanding the Self',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'PE-101',    'name' => 'Physical Education 1',             'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'NSTP-101',  'name' => 'NSTP 1',                          'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 1st Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-111', 'name' => 'Engineering Mathematics 2',        'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-112', 'name' => 'Engineering Physics 2',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-113', 'name' => 'Basic Circuit Theory',             'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-114', 'name' => 'Engineering Materials Science',    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-111',    'name' => 'Readings in Philippine History',   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-112',    'name' => 'Mathematics in the Modern World',  'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'PE-102',    'name' => 'Physical Education 2',             'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'NSTP-102',  'name' => 'NSTP 2',                          'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 2nd Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-201', 'name' => 'Differential Equations',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-202', 'name' => 'AC Circuits & Analysis',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-203', 'name' => 'Electronics 1',                    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-204', 'name' => 'Electrical Wiring & Installation', 'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-205', 'name' => 'Logic Circuits & Design',          'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'GE-201',    'name' => 'The Contemporary World',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'PE-201',    'name' => 'Physical Education 3',             'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 2nd Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-211', 'name' => 'Electrical Machines 1',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-212', 'name' => 'Electronics 2',                    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-213', 'name' => 'Instrumentation & Measurement',    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-214', 'name' => 'Engineering Economy',              'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-211',    'name' => 'Ethics',                           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'PE-202',    'name' => 'Physical Education 4',             'units' => 2, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 3rd Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-301', 'name' => 'Electrical Machines 2',            'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-302', 'name' => 'Power Systems Analysis',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-303', 'name' => 'Industrial Electronics',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-304', 'name' => 'Control Systems',                  'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-305', 'name' => 'Electrical Safety & Standards',    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'GE-301',    'name' => 'Science, Technology & Society',    'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 3rd Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-311', 'name' => 'Electrical Design & Drafting',     'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-312', 'name' => 'PLC & Automation',                 'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-313', 'name' => 'Building Electrical Systems',      'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-314', 'name' => 'Renewable Energy Systems',         'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-315', 'name' => 'Technical Report Writing',         'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 4th Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-401', 'name' => 'Capstone Project 1',              'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-402', 'name' => 'High Voltage Engineering',         'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-403', 'name' => 'Power Electronics',                'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEET-404', 'name' => 'Electrical Project Management',   'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEET-405', 'name' => 'Special Topics in EET',           'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electrical Engineering Technology — 4th Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEET-411', 'name' => 'Capstone Project 2',              'units' => 6, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-412', 'name' => 'On-the-Job Training',             'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEET-413', 'name' => 'Professional Practice & Ethics',  'units' => 3, 'course' => 'BS Electrical Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 1st Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-101', 'name' => 'Engineering Mathematics 1',       'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-102', 'name' => 'Engineering Physics 1',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-103', 'name' => 'Introduction to Electronics',     'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-104', 'name' => 'Technical Drawing',               'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'GE-101B',   'name' => 'Purposive Communication',          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'GE-102B',   'name' => 'Understanding the Self',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'PE-101B',   'name' => 'Physical Education 1',             'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],
            ['code' => 'NSTP-101B', 'name' => 'NSTP 1',                          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 1st Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-111', 'name' => 'Engineering Mathematics 2',       'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-112', 'name' => 'Engineering Physics 2',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-113', 'name' => 'DC & AC Circuit Analysis',        'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-114', 'name' => 'Electronic Components',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-111B',   'name' => 'Readings in Philippine History',   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-112B',   'name' => 'Mathematics in the Modern World',  'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'PE-102B',   'name' => 'Physical Education 2',             'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],
            ['code' => 'NSTP-102B', 'name' => 'NSTP 2',                          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '1st Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 2nd Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-201', 'name' => 'Differential Equations',          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-202', 'name' => 'Electronic Circuits 1',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-203', 'name' => 'Digital Electronics',             'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-204', 'name' => 'Signals & Systems',               'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-205', 'name' => 'Electronic Measurements',         'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'GE-201B',   'name' => 'The Contemporary World',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],
            ['code' => 'PE-201B',   'name' => 'Physical Education 3',             'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 2nd Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-211', 'name' => 'Electronic Circuits 2',           'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-212', 'name' => 'Microprocessors & Microcontrollers', 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-213', 'name' => 'Communications Electronics',      'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-214', 'name' => 'Engineering Economy',             'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'GE-211B',   'name' => 'Ethics',                          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],
            ['code' => 'PE-202B',   'name' => 'Physical Education 4',             'units' => 2, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '2nd Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 3rd Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-301', 'name' => 'RF & Microwave Engineering',      'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-302', 'name' => 'Embedded Systems',                'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-303', 'name' => 'Digital Signal Processing',       'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-304', 'name' => 'Industrial Electronics',          'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'GE-301B',   'name' => 'Science, Technology & Society',   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 3rd Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-311', 'name' => 'Fiber Optics & Photonics',        'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-312', 'name' => 'Wireless Communications',         'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-313', 'name' => 'IoT & Smart Systems',             'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-314', 'name' => 'Technical Report Writing',        'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '3rd Year', 'semester' => '2nd Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 4th Year 1st Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-401', 'name' => 'Capstone Project 1',             'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-402', 'name' => 'Advanced Communications Systems', 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem', 'has_lab' => true, 'lab_fee' => 1500.00],
            ['code' => 'BSEECT-403', 'name' => 'Electronic Project Management',   'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],
            ['code' => 'BSEECT-404', 'name' => 'Special Topics in EECT',         'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '1st Sem'],

            // ═══════════════════════════════════════════════════════════════
            // BS Electronics Engineering Technology — 4th Year 2nd Semester
            // ═══════════════════════════════════════════════════════════════
            ['code' => 'BSEECT-411', 'name' => 'Capstone Project 2',             'units' => 6, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-412', 'name' => 'On-the-Job Training',            'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
            ['code' => 'BSEECT-413', 'name' => 'Professional Practice & Ethics', 'units' => 3, 'course' => 'BS Electronics Engineering Technology', 'year_level' => '4th Year', 'semester' => '2nd Sem'],
        ];
    }
}