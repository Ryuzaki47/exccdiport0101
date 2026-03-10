<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Traits\GetAdminUserTrait;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Fee;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\User;

/**
 * ComprehensiveAssessmentSeeder
 *
 * Each assessment uses a SINGLE flat "Tuition Fee" line equal to the
 * course-specific total for that year level and semester.
 *
 * "Academic" category removed — the single fee uses category "Tuition".
 *
 * Payment terms: 5 per assessment
 *   Upon Registration 42.15% | Prelim 17.86% | Midterm 17.86%
 *   Semi-Final 14.88% | Final 7.25%
 */
class ComprehensiveAssessmentSeeder extends Seeder
{
    use GetAdminUserTrait;

    private string $schoolYear = '2025-2026';

    // ─────────────────────────────────────────────────────────────────────────
    // Course-specific flat tuition totals per year level × semester
    // shape: course → yearLevel → semester → total amount (float)
    //
    // BSEET amounts provided:  1Y1S=18,400 | 1Y2S=16,000 | 2Y1S=17,600
    // Remaining marked TODO — update these with real values.
    // ─────────────────────────────────────────────────────────────────────────
    private array $courseTotals = [

        'BS Electrical Engineering Technology' => [
            '1st Year' => ['1st Sem' => 18400.00, '2nd Sem' => 16000.00],
            '2nd Year' => ['1st Sem' => 17600.00, '2nd Sem' => 16800.00],  // TODO: confirm 2Y2S
            '3rd Year' => ['1st Sem' => 19200.00, '2nd Sem' => 18000.00],  // TODO: confirm 3Y1S, 3Y2S
            '4th Year' => ['1st Sem' => 20000.00, '2nd Sem' => 19200.00],  // TODO: confirm 4Y1S, 4Y2S
        ],

        'BS Electronics Engineering Technology' => [
            '1st Year' => ['1st Sem' => 18600.00, '2nd Sem' => 16200.00],  // TODO: confirm
            '2nd Year' => ['1st Sem' => 17800.00, '2nd Sem' => 17000.00],  // TODO: confirm
            '3rd Year' => ['1st Sem' => 19400.00, '2nd Sem' => 18200.00],  // TODO: confirm
            '4th Year' => ['1st Sem' => 20200.00, '2nd Sem' => 19400.00],  // TODO: confirm
        ],

        // Add more courses here — one entry per course name exactly as stored
        // in users.course:
        //
        // 'BS Information Technology' => [
        //     '1st Year' => ['1st Sem' => 15000.00, '2nd Sem' => 14000.00],
        //     ...
        // ],
    ];

    /**
     * Fallback used when a student's course has no entry in $courseTotals.
     */
    private array $fallbackTotals = [
        '1st Year' => ['1st Sem' => 17000.00, '2nd Sem' => 15500.00],
        '2nd Year' => ['1st Sem' => 17500.00, '2nd Sem' => 16000.00],
        '3rd Year' => ['1st Sem' => 18500.00, '2nd Sem' => 17000.00],
        '4th Year' => ['1st Sem' => 19500.00, '2nd Sem' => 18500.00],
    ];

    private array $termDefinitions = [
        1 => ['name' => 'Upon Registration', 'percentage' => 42.15],
        2 => ['name' => 'Prelim',            'percentage' => 17.86],
        3 => ['name' => 'Midterm',           'percentage' => 17.86],
        4 => ['name' => 'Semi-Final',        'percentage' => 14.88],
        5 => ['name' => 'Final',             'percentage' =>  7.25],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $adminId = $this->getOrFindAdminUserId();

        $this->command->info('🗑️  Clearing existing assessments, payment terms, charge transactions…');
        $studentIds = User::where('role', 'student')->pluck('id');
        StudentPaymentTerm::whereIn('user_id', $studentIds)->delete();
        StudentAssessment::whereIn('user_id', $studentIds)->delete();
        Transaction::whereIn('user_id', $studentIds)->where('kind', 'charge')->delete();
        $this->command->info('✓ Cleared.');
        $this->command->newLine();

        // Seed fees table with one row per course × year × semester
        $this->command->info('💰 Seeding Fees table (single flat Tuition Fee per course/year/sem)…');
        Fee::query()->delete();
        $this->seedFeesTable();
        $this->command->info('✓ Fees seeded: ' . Fee::count() . ' records.');
        $this->command->newLine();

        $students  = User::where('role', 'student')->whereNotNull('year_level')->get();
        $semesters = ['1st Sem', '2nd Sem'];

        $this->command->info("📋 Creating assessments for {$students->count()} students…");
        $created = 0;
        $skipped = 0;

        foreach ($students as $student) {
            if (empty($student->year_level)) { $skipped++; continue; }

            $totals = $this->courseTotals[$student->course ?? ''] ?? $this->fallbackTotals;

            foreach ($semesters as $semester) {
                $amount = $totals[$student->year_level][$semester] ?? null;
                if ($amount === null) { $skipped++; continue; }

                $this->createStudentAssessment($student, $semester, $adminId, (float) $amount);
                $created++;
            }
        }

        $this->command->info("✓ Created {$created} assessments. Skipped {$skipped}.");
        $this->command->newLine();
        $this->command->info('✅ ComprehensiveAssessmentSeeder complete.');
        $this->command->table(
            ['Item', 'Count'],
            [
                ['Fee Records',         Fee::count()],
                ['Assessments',         StudentAssessment::count()],
                ['Payment Terms',       StudentPaymentTerm::count()],
                ['Charge Transactions', Transaction::whereIn('user_id', $studentIds)
                                            ->where('kind', 'charge')->count()],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seed fees table — one flat "Tuition Fee" row per course × year × semester
    // ─────────────────────────────────────────────────────────────────────────

    private function seedFeesTable(): void
    {
        $allCourses = array_merge(
            $this->courseTotals,
            ['_fallback' => $this->fallbackTotals]
        );

        foreach ($this->courseTotals as $course => $yearLevels) {
            foreach ($yearLevels as $yearLevel => $semesters) {
                foreach ($semesters as $semester => $amount) {
                    $courseSlug = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $course), 0, 4));
                    $yrNum      = preg_replace('/[^0-9]/', '', $yearLevel);
                    $semNum     = preg_replace('/[^0-9]/', '', $semester);
                    $code       = "TUI-{$courseSlug}-Y{$yrNum}S{$semNum}";

                    Fee::firstOrCreate(['code' => $code], [
                        'name'        => 'Tuition Fee',
                        'category'    => 'Tuition',
                        'amount'      => $amount,
                        'year_level'  => $yearLevel,
                        'semester'    => $semester,
                        'school_year' => $this->schoolYear,
                        'description' => "Tuition Fee — {$yearLevel} {$semester} ({$course})",
                        'is_active'   => true,
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create one full assessment for a student + semester
    // ─────────────────────────────────────────────────────────────────────────

    private function createStudentAssessment(
        User   $student,
        string $semester,
        int    $adminId,
        float  $tuitionTotal
    ): void {
        $yearLevel    = $student->year_level;
        $grandTotal   = round($tuitionTotal, 2);

        $feeBreakdown = [[
            'category'    => 'Tuition',
            'name'        => 'Tuition Fee',
            'amount'      => $grandTotal,
            'description' => "Tuition Fee — {$yearLevel} {$semester} {$this->schoolYear}",
        ]];

        $assessment = StudentAssessment::create([
            'user_id'           => $student->id,
            'assessment_number' => StudentAssessment::generateAssessmentNumber(),
            'year_level'        => $yearLevel,
            'semester'          => $semester,
            'school_year'       => $this->schoolYear,
            'tuition_fee'       => $grandTotal,
            'other_fees'        => 0,
            'total_assessment'  => $grandTotal,
            'subjects'          => [],
            'fee_breakdown'     => $feeBreakdown,
            'status'            => 'active',
            'created_by'        => $adminId,
        ]);

        // Single charge transaction per assessment
        $yearNum = (int) explode('-', $this->schoolYear)[0];
        Transaction::create([
            'user_id'   => $student->id,
            'reference' => 'ASMT-' . strtoupper(Str::random(8)),
            'kind'      => 'charge',
            'type'      => 'Tuition',
            'year'      => $yearNum,
            'semester'  => $semester,
            'amount'    => $grandTotal,
            'status'    => 'pending',
            'meta'      => [
                'assessment_id'   => $assessment->id,
                'assessment_type' => 'regular',
                'description'     => "Tuition Fee — {$yearLevel} {$semester} {$this->schoolYear}",
            ],
        ]);

        // 5 payment terms
        $semStart = ($semester === '1st Sem')
            ? Carbon::create(2025, 8, 1)
            : Carbon::create(2026, 1, 5);

        $dueDates = [
            1 => $semStart->copy(),
            2 => $semStart->copy()->addWeeks(6),
            3 => $semStart->copy()->addWeeks(12),
            4 => $semStart->copy()->addWeeks(16),
            5 => $semStart->copy()->addWeeks(19),
        ];

        $allocated = 0.00;
        foreach ($this->termDefinitions as $order => $term) {
            $isLast = ($order === 5);
            $amount = $isLast
                ? round($grandTotal - $allocated, 2)
                : round(($term['percentage'] / 100) * $grandTotal, 2);

            if (!$isLast) $allocated += $amount;

            StudentPaymentTerm::create([
                'student_assessment_id'  => $assessment->id,
                'user_id'                => $student->id,
                'term_name'              => $term['name'],
                'term_order'             => $order,
                'percentage'             => $term['percentage'],
                'amount'                 => $amount,
                'balance'                => $amount,
                'due_date'               => $dueDates[$order]->toDateString(),
                'status'                 => StudentPaymentTerm::STATUS_PENDING,
                'remarks'                => null,
                'paid_date'              => null,
                'carryover_from_term_id' => null,
                'carryover_amount'       => 0.00,
            ]);
        }
    }
}