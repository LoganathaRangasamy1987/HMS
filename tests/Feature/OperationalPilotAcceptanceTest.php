<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\Medicine;
use App\Models\ServiceItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalPilotAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_complete_pilot_journey_preserves_amendments_and_books_return_visit(): void
    {
        $profile = $this->profileWithMondaySchedule();
        $this->signIn('reception@lotus.test');
        $patientId = $this->postJson('/api/v1/patients', [
            'first_name' => 'Pilot', 'last_name' => 'Acceptance', 'date_of_birth_unknown' => true,
            'gender' => 'unknown', 'mobile' => '9876504070',
        ])->assertCreated()->json('data.id');
        $appointmentId = $this->book($patientId, $profile->id, '2026-10-05', 'NEW');
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CHECKED_IN'])->assertOk();
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'WAITING'])->assertOk();

        $this->signIn('doctor@lotus.test');
        $encounterId = $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CONSULTING'])->assertOk()->json('encounter.id');
        $clinicalTime = now('Asia/Kolkata')->subMinute()->format('Y-m-d\\TH:i');
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", ['temperature' => '37.2', 'pulse' => 78, 'measured_at' => $clinicalTime])->assertCreated();
        $diagnosisId = $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", [
            'type' => 'PROVISIONAL', 'description' => 'Suspected viral fever', 'diagnosed_at' => $clinicalTime,
        ])->assertCreated()->json('data.id');
        $medicine = Medicine::where('hospital_id', $profile->hospital_id)->where('code', 'PARA500')->firstOrFail();
        $this->postJson("/api/v1/encounters/{$encounterId}/prescription", [
            'notes' => 'Complete the advised course',
            'items' => [['medicine_id' => $medicine->id, 'dose' => '1 tablet', 'frequency' => 'Twice daily', 'duration' => '3 days', 'route' => 'Oral', 'timing' => 'After food']],
        ])->assertCreated();
        $consultation = ['chief_complaint' => 'Fever', 'history' => 'Two days', 'examination' => 'Stable', 'clinical_notes' => 'Hydration advised', 'follow_up_date' => '2026-10-12'];
        $this->putJson("/api/v1/encounters/{$encounterId}/consultation/finalize", $consultation)->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $consultationId = Consultation::where('encounter_id', $encounterId)->value('id');
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses/{$diagnosisId}/corrections", [
            'type' => 'FINAL', 'description' => 'Viral fever', 'reason' => 'Confirmed after examination',
        ])->assertCreated();
        $this->postJson("/api/v1/encounters/{$encounterId}/consultation/amendments", [
            ...$consultation, 'clinical_notes' => 'Hydration and rest advised', 'reason' => 'Clarified discharge advice',
        ])->assertCreated();

        $this->signIn('reception@lotus.test');
        $service = ServiceItem::factory()->create(['hospital_id' => $profile->hospital_id, 'base_price' => '500.00', 'discount_type' => 'none', 'discount_value' => '0.00', 'tax_rate_percent' => '0.00']);
        $invoiceId = $this->postJson('/api/v1/invoices', [
            'patient_id' => $patientId, 'appointment_id' => $appointmentId,
            'lines' => [['service_item_id' => $service->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();
        $paymentId = $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => '500.00', 'mode' => 'CASH', 'request_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('invoice.status', 'PAID')->json('data.id');
        $this->get("/invoices/{$invoiceId}/payments/{$paymentId}/receipt")->assertOk();
        $returnAppointmentId = $this->book($patientId, $profile->id, '2026-10-12', 'FOLLOWUP');

        $timeline = $this->getJson("/api/v1/patients/{$patientId}/timeline")->assertOk()
            ->assertJsonPath('permissions.canClinical', false)->assertJsonPath('permissions.canBilling', true);
        $types = collect($timeline->json('timeline.data'))->pluck('event_type');
        $this->assertSame(2, $types->filter(fn (string $type): bool => $type === 'appointment')->count());
        $this->assertTrue($types->contains('invoice'));
        $this->assertTrue($types->contains('payment'));
        $this->assertFalse($types->contains('diagnosis'));
        $this->assertDatabaseHas('appointments', ['id' => $appointmentId, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('appointments', ['id' => $returnAppointmentId, 'type' => 'FOLLOWUP', 'status' => 'BOOKED']);
        $this->assertDatabaseHas('consultations', ['id' => $consultationId, 'clinical_notes' => 'Hydration advised']);
        $this->assertDatabaseHas('consultation_amendments', ['consultation_id' => $consultationId, 'clinical_notes' => 'Hydration and rest advised']);
        $this->assertDatabaseHas('diagnoses', ['id' => $diagnosisId, 'description' => 'Suspected viral fever']);
        $this->assertDatabaseHas('diagnosis_corrections', ['diagnosis_id' => $diagnosisId, 'description' => 'Viral fever']);
    }

    public function test_care_and_administration_permissions_remain_separated(): void
    {
        $profile = $this->profileWithMondaySchedule();
        $this->signIn('reception@lotus.test');
        $patientId = $this->postJson('/api/v1/patients', [
            'first_name' => 'Boundary', 'last_name' => 'Patient', 'date_of_birth_unknown' => true,
            'gender' => 'unknown', 'mobile' => '9876504071',
        ])->assertCreated()->json('data.id');
        $appointmentId = $this->book($patientId, $profile->id, '2026-10-05', 'NEW');
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CHECKED_IN'])->assertOk();
        $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'WAITING'])->assertOk();
        $this->signIn('doctor@lotus.test');
        $encounterId = $this->putJson("/api/v1/appointments/{$appointmentId}/status", ['status' => 'CONSULTING'])->assertOk()->json('encounter.id');
        $this->postJson('/api/v1/invoices', ['patient_id' => $patientId, 'lines' => []])->assertForbidden();
        $this->getJson("/api/v1/patients/{$patientId}/timeline?section=billing")->assertForbidden();

        $this->signIn('reception@lotus.test');
        $this->getJson("/api/v1/encounters/{$encounterId}/consultation")->assertForbidden();
        $this->postJson("/api/v1/encounters/{$encounterId}/vitals", ['pulse' => 70, 'measured_at' => now('Asia/Kolkata')->subMinute()->format('Y-m-d\\TH:i')])->assertForbidden();
        $this->getJson("/api/v1/patients/{$patientId}/timeline?section=clinical")->assertForbidden();

        $this->signIn('admin@lotus.test');
        $this->postJson("/api/v1/encounters/{$encounterId}/diagnoses", [
            'type' => 'FINAL', 'description' => 'Unauthorized diagnosis',
            'diagnosed_at' => now('Asia/Kolkata')->subMinute()->format('Y-m-d\\TH:i'),
        ])->assertForbidden();
        $this->assertDatabaseCount('diagnoses', 0);
        $this->assertDatabaseCount('vital_observations', 0);
    }

    private function profileWithMondaySchedule(): DoctorProfile
    {
        $hospital = Hospital::where('code', 'LOTUS')->firstOrFail();
        $branch = Branch::where('hospital_id', $hospital->id)->where('code', 'CBE')->firstOrFail();
        $doctor = User::where('email', 'doctor@lotus.test')->firstOrFail();
        $profile = DoctorProfile::firstOrCreate(
            ['hospital_id' => $hospital->id, 'branch_id' => $branch->id, 'user_id' => $doctor->id],
            ['department_id' => Department::where('branch_id', $branch->id)->value('id'), 'registration_number' => 'PILOT-DOCTOR', 'qualification' => 'MBBS', 'specialization' => 'General Medicine', 'consultation_fee' => 500, 'status' => 'active'],
        );
        $profile->schedules()->firstOrCreate(
            ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
            ['slot_duration_minutes' => 15, 'capacity_per_slot' => 2, 'status' => 'active'],
        );

        return $profile;
    }

    private function book(int $patientId, int $profileId, string $date, string $type): int
    {
        return $this->postJson('/api/v1/appointments', [
            'patient_id' => $patientId, 'doctor_profile_id' => $profileId,
            'appointment_date' => $date, 'starts_at' => '09:00', 'type' => $type,
        ])->assertCreated()->json('data.id');
    }

    private function signIn(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();
        $membership = $user->memberships()->whereHas('branch', fn ($query) => $query->where('code', 'CBE'))->firstOrFail();
        $this->actingAs($user)->withSession(['membership_id' => $membership->id, 'password_hash_web' => $user->getAuthPassword()]);
    }
}
