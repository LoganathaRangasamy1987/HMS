<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PatientDocument>
 */
class PatientDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'hospital_id' => fn (array $attributes): int => Patient::findOrFail($attributes['patient_id'])->hospital_id,
            'branch_id' => fn (array $attributes): int => Patient::findOrFail($attributes['patient_id'])->branch_id,
            'uploaded_by' => fn (array $attributes): int => User::factory()->create(['hospital_id' => $attributes['hospital_id']])->id,
            'category' => 'other',
            'name' => 'fixture.pdf',
            'path' => 'testing/patient-documents/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
