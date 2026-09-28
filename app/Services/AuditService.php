<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditService
{
    public function __construct(private TenantContext $tenant) {}

    public function record(string $module, string $action, Model $record, ?array $old = null, ?array $new = null): void
    {
        $safeFields = ['name', 'code', 'registration_number', 'email', 'phone', 'mobile', 'address', 'city', 'state', 'country', 'status', 'branch_id', 'role_id', 'memberships', 'description', 'password_changed'];
        if ($module === 'patients') {
            $safeFields = ['uhid', 'first_name', 'last_name', 'date_of_birth', 'date_of_birth_unknown', 'gender', 'mobile', 'email', 'blood_group', 'address', 'city', 'state', 'pincode', 'emergency_contact_name', 'emergency_contact_mobile', 'status'];
        }
        if (in_array($module, ['patient_allergies', 'patient_medical_history'], true)) {
            $safeFields = ['allergen', 'reaction', 'severity', 'condition', 'onset_date', 'onset_date_unknown', 'status', 'notes'];
        }
        if ($module === 'patient_documents') {
            $safeFields = ['category', 'name', 'mime_type', 'size'];
        }
        if ($module === 'doctor_profiles') {
            $safeFields = ['branch_id', 'department_id', 'user_id', 'registration_number', 'qualification', 'specialization', 'consultation_fee', 'status'];
        }
        if (in_array($module, ['doctor_schedules', 'doctor_unavailability', 'doctor_schedule_exceptions'], true)) {
            $safeFields = ['day_of_week', 'starts_at', 'ends_at', 'slot_duration_minutes', 'capacity_per_slot', 'status', 'type', 'starts_on', 'ends_on', 'reason', 'date', 'availability'];
        }
        if ($module === 'appointments') {
            $safeFields = ['branch_id', 'patient_id', 'doctor_profile_id', 'appointment_date', 'starts_at', 'ends_at', 'token_number', 'type', 'status', 'reason', 'cancellation_reason'];
        }
        if ($module === 'encounters') {
            $safeFields = ['branch_id', 'patient_id', 'appointment_id', 'doctor_profile_id', 'department_id', 'encounter_type', 'status', 'opened_at', 'closed_at'];
        }
        if ($module === 'consultations') {
            $safeFields = ['encounter_id', 'status', 'follow_up_date', 'finalized_at', 'finalized_by'];
        }
        if ($module === 'consultation_amendments') {
            $safeFields = ['consultation_id', 'follow_up_date', 'reason', 'amended_by', 'amended_at'];
        }
        if ($module === 'vital_observations') {
            $safeFields = ['encounter_id', 'measured_at', 'recorded_by'];
        }
        if ($module === 'diagnoses') {
            $safeFields = ['encounter_id', 'type', 'code_system', 'code', 'diagnosed_at', 'authored_by'];
        }
        if ($module === 'diagnosis_corrections') {
            $safeFields = ['diagnosis_id', 'type', 'code_system', 'code', 'reason', 'corrected_by', 'corrected_at'];
        }
        if ($module === 'medicines') {
            $safeFields = ['code', 'name', 'generic_name', 'form', 'strength', 'status'];
        }
        if (in_array($module, ['lab_categories', 'lab_sample_types', 'lab_units'], true)) {
            $safeFields = ['code', 'name', 'symbol', 'status'];
        }
        if ($module === 'lab_tests') {
            $safeFields = ['code', 'name', 'status', 'active_version_id'];
        }
        if ($module === 'lab_test_versions') {
            $safeFields = ['lab_test_id', 'version', 'category_id', 'sample_type_id', 'sample_volume', 'currency', 'price', 'status', 'created_by', 'activated_at'];
        }
        if ($module === 'lab_orders') {
            $safeFields = ['number', 'status', 'patient_id', 'encounter_id', 'doctor_profile_id', 'invoice_id', 'ordered_by', 'ordered_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];
        }
        if ($module === 'lab_specimens') {
            $safeFields = ['identifier', 'attempt', 'status', 'lab_order_id', 'lab_order_item_id', 'collected_by', 'collected_at', 'received_by', 'received_at', 'processing_by', 'processing_at', 'rejected_by', 'rejected_at', 'rejection_reason'];
        }
        if ($module === 'lab_results') {
            $safeFields = ['lab_order_id', 'lab_order_item_id', 'lab_specimen_id', 'revision', 'status', 'supersedes_lab_result_id', 'correction_reason', 'entered_by', 'entered_at', 'verified_by', 'verified_at', 'value_count'];
        }
        if ($module === 'prescriptions') {
            $safeFields = ['encounter_id', 'patient_id', 'prescribed_at', 'prescribed_by', 'item_count'];
        }
        if ($module === 'pharmacy_sales') {
            $safeFields = ['number', 'status', 'patient_id', 'prescription_id', 'invoice_id', 'currency', 'subtotal', 'tax_amount', 'total', 'dispensed_by', 'dispensed_at'];
        }
        if ($module === 'pharmacy_stock_adjustments') {
            $safeFields = ['medicine_id', 'medicine_batch_id', 'pharmacy_sale_item_id', 'pharmacy_purchase_item_id', 'stock_movement_id', 'type', 'quantity', 'reason', 'status', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_reason'];
        }
        if ($module === 'pharmacy_settings') {
            $safeFields = ['pharmacy_expiry_warning_days'];
        }
        if ($module === 'service_items') {
            $safeFields = ['code', 'name', 'type', 'description', 'currency', 'base_price', 'tax_rate_percent', 'discount_type', 'discount_value', 'status'];
        }
        if ($module === 'service_branch_prices') {
            $safeFields = ['branch_id', 'base_price', 'tax_rate_percent', 'discount_type', 'discount_value', 'is_available'];
        }
        if ($module === 'invoices') {
            $safeFields = ['number', 'status', 'currency', 'subtotal', 'discount_total', 'tax_total', 'total', 'patient_id', 'appointment_id', 'issued_at'];
        }
        if ($module === 'payments') {
            $safeFields = ['invoice_id', 'amount', 'mode', 'received_at'];
        }
        if ($module === 'financial_adjustments') {
            $safeFields = ['invoice_id', 'payment_id', 'type', 'amount', 'reason', 'recorded_at'];
        }
        $filter = function (?array $values) use ($safeFields): ?array {
            if ($values === null) {
                return null;
            }
            $safe = Arr::only($values, $safeFields);
            if (isset($safe['memberships'])) {
                $safe['memberships'] = array_map(fn ($row) => Arr::only((array) $row, ['id', 'branch_id', 'role_id', 'status']), $safe['memberships']);
            }

            return $safe;
        };
        AuditLog::create([
            'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(),
            'user_id' => auth()->id(), 'module' => $module, 'action' => $action,
            'record_type' => $record->getMorphClass(), 'record_id' => $record->getKey(),
            'old_values' => $filter($old), 'new_values' => $filter($new),
            'ip_address' => request()->ip(), 'created_at' => now(),
        ]);
    }
}
