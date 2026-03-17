<?php

/**
 * School Fee Configuration
 * ============================================================================
 * This file is the SINGLE source of truth for course fee presets.
 *
 * School administrators update amounts here instead of editing controller code.
 * After editing, run:   php artisan config:clear
 *
 * STRUCTURE
 * ---------
 * fees.presets   → course → year_level → semester → fee line items[]
 * fees.categories → list of allowed fee category names
 * fees.terms     → payment term definitions (order, name, percentage)
 *
 * ALLOWED CATEGORIES: Tuition | Laboratory | Miscellaneous | Other
 * (Academic was removed per project requirement.)
 *
 * To add a new course: add a top-level key matching users.course exactly.
 * ============================================================================
 */

return [

    // =========================================================================
    // FEE CATEGORIES
    // =========================================================================
    'categories' => [
        'Tuition',
        'Laboratory',
        'Miscellaneous',
        'Other',
    ],

    // =========================================================================
    // PAYMENT TERM DEFINITIONS
    // Term percentages must sum to exactly 100.
    // The last term absorbs any cent-level rounding remainder automatically
    // inside StudentFeeController::createPaymentTerms().
    // =========================================================================
    'terms' => [
        1 => ['name' => 'Upon Registration', 'percentage' => 42.15],
        2 => ['name' => 'Prelim',            'percentage' => 17.86],
        3 => ['name' => 'Midterm',           'percentage' => 17.86],
        4 => ['name' => 'Semi-Final',        'percentage' => 14.88],
        5 => ['name' => 'Final',             'percentage' =>  7.25],
    ],

    // =========================================================================
    // COURSE FEE PRESETS
    // =========================================================================
    //
    // BS Electrical Engineering Technology (BSEET)
    // Totals: 1Y1S=18,400 | 1Y2S=16,000 | 2Y1S=17,600 | 2Y2S=16,800
    //         3Y1S=19,200 | 3Y2S=18,000 | 4Y1S=20,000 | 4Y2S=19,200
    //
    // BS Electronics Engineering Technology (BSEECT)
    // Same structure; slightly higher lab fees due to electronics equipment.
    //
    // =========================================================================
    'presets' => [

        // ─────────────────────────────────────────────────────────────────────
        // BS Electrical Engineering Technology
        // ─────────────────────────────────────────────────────────────────────
        'BS Electrical Engineering Technology' => [
            '1st Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' =>   400.00],
                    // Total = 18,400
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 11600.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 16,000
                ],
            ],
            '2nd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13200.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 17,800
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 12700.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  1700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 17,000
                ],
            ],
            '3rd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 19,400
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 18,200
                ],
            ],
            '4th Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 15400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 20,400
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    // Total = 19,500
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────────
        // BS Electronics Engineering Technology
        // Slightly higher lab fees due to electronics lab equipment
        // ─────────────────────────────────────────────────────────────────────
        'BS Electronics Engineering Technology' => [
            '1st Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2000.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                    ['category' => 'Other',         'name' => 'Medical/Dental Fee',   'amount' =>   400.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 11600.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2000.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
            '2nd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13200.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2100.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 12700.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2000.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   700.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
            '3rd Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2300.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 13400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2300.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   800.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
            '4th Year' => [
                '1st Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 15400.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   500.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
                '2nd Sem' => [
                    ['category' => 'Tuition',       'name' => 'Tuition Fee',          'amount' => 14500.00],
                    ['category' => 'Laboratory',    'name' => 'Laboratory Fee',       'amount' =>  2400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Registration Fee',     'amount' =>   400.00],
                    ['category' => 'Miscellaneous', 'name' => 'Miscellaneous Fee',    'amount' =>   900.00],
                    ['category' => 'Miscellaneous', 'name' => 'Athletics Fee',        'amount' =>   200.00],
                    ['category' => 'Miscellaneous', 'name' => 'Library Fee',          'amount' =>   200.00],
                    ['category' => 'Other',         'name' => 'Student Activity Fee', 'amount' =>   500.00],
                    ['category' => 'Other',         'name' => 'ICT Fee',              'amount' =>   600.00],
                ],
            ],
        ],

    ],

];