<?php

namespace App\Services;

use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Models\LabTestParameter;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabResultService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit, private LabNotificationService $notifications) {}

    public function start(LabSpecimen $specimen): LabResult
    {
        return DB::transaction(function () use ($specimen): LabResult {
            $specimen = $this->lockedSpecimen($specimen);
            if ($specimen->status !== 'PROCESSING') {
                throw ValidationException::withMessages(['result' => 'Result entry requires a specimen in processing.']);
            }
            if ($specimen->results()->exists()) {
                throw ValidationException::withMessages(['result' => 'A result already exists; use the correction workflow after finalization.']);
            }
            $result = LabResult::create([
                'hospital_id' => $specimen->hospital_id, 'branch_id' => $specimen->branch_id, 'lab_order_id' => $specimen->lab_order_id,
                'lab_order_item_id' => $specimen->lab_order_item_id, 'lab_specimen_id' => $specimen->id, 'revision' => 1,
                'status' => 'DRAFT', 'entered_by' => auth()->id(), 'entered_at' => now(),
            ]);
            $this->audit->record('lab_results', 'draft_created', $result, null, $result->toArray());

            return $result->load($this->relations());
        }, 3);
    }

    /** @param array<int, array<string, mixed>> $entries */
    public function save(LabResult $result, array $entries): LabResult
    {
        return DB::transaction(function () use ($result, $entries): LabResult {
            $result = $this->lockedResult($result);
            $this->ensureDraftOwner($result);
            $parameters = LabTestParameter::where('lab_test_version_id', $result->orderItem->lab_test_version_id)->with('unit')->orderBy('sort_order')->get()->keyBy('id');
            if ($parameters->count() !== count($entries) || collect($entries)->pluck('parameter_id')->unique()->count() !== count($entries)) {
                throw ValidationException::withMessages(['values' => 'Provide one value for every ordered test parameter.']);
            }
            $rows = [];
            foreach ($entries as $index => $entry) {
                $parameter = $parameters->get((int) $entry['parameter_id']);
                if (! $parameter) {
                    throw ValidationException::withMessages(["values.{$index}.parameter_id" => 'This parameter does not belong to the ordered test version.']);
                }
                $rows[] = $this->valueRow($parameter, $entry, $index);
            }
            $result->values()->delete();
            $result->values()->createMany($rows);
            $this->audit->record('lab_results', 'values_saved', $result, null, ['status' => $result->status, 'value_count' => count($rows)]);
            $this->notifications->resultReady($result);

            return $result->load($this->relations());
        }, 3);
    }

    public function finalize(LabResult $result): LabResult
    {
        return DB::transaction(function () use ($result): LabResult {
            $result = $this->lockedResult($result);
            if ($result->status !== 'DRAFT') {
                throw ValidationException::withMessages(['result' => 'Only a draft result can be finalized.']);
            }
            if ($result->entered_by === auth()->id()) {
                throw ValidationException::withMessages(['result' => 'The person who entered results cannot verify the same revision.']);
            }
            $required = LabTestParameter::where('lab_test_version_id', $result->orderItem->lab_test_version_id)->count();
            if ($required === 0 || $result->values()->count() !== $required) {
                throw ValidationException::withMessages(['result' => 'Every parameter requires a valid value before verification.']);
            }
            $result->update(['status' => 'FINAL', 'verified_by' => auth()->id(), 'verified_at' => now()]);
            $result->specimen->update(['status' => 'VERIFIED']);
            $result->orderItem->update(['status' => 'VERIFIED']);
            $this->syncOrderStatus($result->order);
            $this->audit->record('lab_results', 'finalized', $result, null, $result->fresh()->toArray());
            $this->notifications->resultFinalized($result);

            return $result->load($this->relations());
        }, 3);
    }

    public function correct(LabResult $result, string $reason): LabResult
    {
        return DB::transaction(function () use ($result, $reason): LabResult {
            $result = $this->lockedResult($result);
            if ($result->status !== 'FINAL' || $result->specimen->results()->where('revision', '>', $result->revision)->exists()) {
                throw ValidationException::withMessages(['result' => 'Only the latest finalized revision can be corrected.']);
            }
            $correction = LabResult::create([
                'hospital_id' => $result->hospital_id, 'branch_id' => $result->branch_id, 'lab_order_id' => $result->lab_order_id,
                'lab_order_item_id' => $result->lab_order_item_id, 'lab_specimen_id' => $result->lab_specimen_id,
                'revision' => $result->revision + 1, 'status' => 'DRAFT', 'supersedes_lab_result_id' => $result->id,
                'correction_reason' => $reason, 'entered_by' => auth()->id(), 'entered_at' => now(),
            ]);
            foreach ($result->values as $value) {
                $correction->values()->create($value->only(['lab_test_parameter_id', 'parameter_code', 'parameter_name', 'result_type', 'unit_symbol', 'reference_min', 'reference_max', 'reference_text', 'sort_order', 'numeric_value', 'text_value', 'boolean_value', 'flag']));
            }
            $this->audit->record('lab_results', 'correction_started', $correction, null, $correction->toArray());

            return $correction->load($this->relations());
        }, 3);
    }

    /** @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function valueRow(LabTestParameter $parameter, array $entry, int $index): array
    {
        $row = [
            'lab_test_parameter_id' => $parameter->id, 'parameter_code' => $parameter->code, 'parameter_name' => $parameter->name,
            'result_type' => $parameter->result_type, 'unit_symbol' => $parameter->unit?->symbol,
            'reference_min' => $parameter->reference_min, 'reference_max' => $parameter->reference_max, 'reference_text' => $parameter->reference_text,
            'sort_order' => $parameter->sort_order,
            'numeric_value' => null, 'text_value' => null, 'boolean_value' => null,
        ];
        if ($parameter->result_type === 'NUMERIC') {
            if (! is_numeric($entry['value']) || (float) $entry['value'] < -9999999999 || (float) $entry['value'] > 9999999999) {
                throw ValidationException::withMessages(["values.{$index}.value" => 'Enter a valid numeric result.']);
            }
            $row['numeric_value'] = (string) $entry['value'];
            $row['flag'] = $this->numericFlag((float) $entry['value'], $parameter);
        } elseif ($parameter->result_type === 'BOOLEAN') {
            if (! in_array($entry['value'], [true, false, 1, 0, '1', '0'], true)) {
                throw ValidationException::withMessages(["values.{$index}.value" => 'Select a valid boolean result.']);
            }
            $row['boolean_value'] = filter_var($entry['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $row['flag'] = $this->manualFlag($entry, $index);
        } else {
            $value = trim((string) $entry['value']);
            if ($value === '' || mb_strlen($value) > 5000) {
                throw ValidationException::withMessages(["values.{$index}.value" => 'Enter a text result up to 5000 characters.']);
            }
            $row['text_value'] = $value;
            $row['flag'] = $this->manualFlag($entry, $index);
        }

        return $row;
    }

    private function numericFlag(float $value, LabTestParameter $parameter): string
    {
        if ($parameter->reference_min !== null && $value < (float) $parameter->reference_min) {
            return 'LOW';
        }
        if ($parameter->reference_max !== null && $value > (float) $parameter->reference_max) {
            return 'HIGH';
        }

        return 'NORMAL';
    }

    /** @param array<string, mixed> $entry */
    private function manualFlag(array $entry, int $index): string
    {
        if (! in_array($entry['flag'] ?? null, ['NORMAL', 'ABNORMAL'], true)) {
            throw ValidationException::withMessages(["values.{$index}.flag" => 'Select normal or abnormal.']);
        }

        return $entry['flag'];
    }

    private function lockedSpecimen(LabSpecimen $specimen): LabSpecimen
    {
        return LabSpecimen::whereKey($specimen->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
    }

    private function lockedResult(LabResult $result): LabResult
    {
        return LabResult::whereKey($result->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->with(['orderItem', 'specimen', 'order', 'values'])->lockForUpdate()->firstOrFail();
    }

    private function ensureDraftOwner(LabResult $result): void
    {
        if ($result->status !== 'DRAFT' || $result->entered_by !== auth()->id()) {
            throw ValidationException::withMessages(['result' => 'Only the technician who opened this draft can edit it.']);
        }
    }

    private function syncOrderStatus(LabOrder $order): void
    {
        $statuses = $order->items()->pluck('status');
        $order->update(['status' => $statuses->every(fn (string $status) => $status === 'VERIFIED') ? 'VERIFIED' : 'RESULT_PARTIAL']);
    }

    /** @return array<int, string> */
    public function relations(): array
    {
        return ['values', 'order.patient:id,uhid,first_name,last_name,date_of_birth,gender', 'order.doctorProfile.user:id,name', 'orderItem.versionDefinition.parameters.unit', 'specimen.results.enteredBy:id,name', 'specimen.results.verifiedBy:id,name', 'enteredBy:id,name', 'verifiedBy:id,name', 'supersedes'];
    }
}
