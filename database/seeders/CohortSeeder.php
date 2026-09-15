<?php

namespace Database\Seeders;

use App\Models\CohortIntake;
use App\Models\CohortProgram;
use Illuminate\Database\Seeder;

// Idempotent — safe to re-run, same pattern as WorkspaceSeeder.
class CohortSeeder extends Seeder
{
    public function run(): void
    {
        $program = CohortProgram::firstOrCreate(
            ['name' => 'Digital Academy Cohort Programme'],
            ['description' => '12-week intensive cohort-based tech training with global certifications.', 'is_active' => true]
        );

        $batches = [
            ['name' => 'Batch A', 'start_date' => '2026-07-02', 'end_date' => '2026-07-31'],
            ['name' => 'Batch B', 'start_date' => '2026-08-06', 'end_date' => '2026-08-30'],
            ['name' => 'Batch C', 'start_date' => '2026-09-03', 'end_date' => '2026-09-27'],
            ['name' => 'Batch D', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
            ['name' => 'Batch E', 'start_date' => '2026-11-06', 'end_date' => '2026-11-29'],
        ];

        foreach ($batches as $batch) {
            CohortIntake::updateOrCreate(
                ['cohort_program_id' => $program->id, 'name' => $batch['name']],
                ['start_date' => $batch['start_date'], 'end_date' => $batch['end_date'], 'capacity' => 30]
            );
        }
    }
}
