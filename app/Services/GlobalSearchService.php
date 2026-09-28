<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;

class GlobalSearchService
{
    /** @return array{query: string, patients: array<int, array<string, mixed>>, invoices: array<int, array<string, mixed>>, counts: array{patients: int, invoices: int}} */
    public function search(int $hospitalId, int $branchId, string $query, bool $includePatients, bool $includeInvoices, int $limit): array
    {
        $patients = $includePatients ? $this->patients($hospitalId, $branchId, $query)->limit($limit)->get()->map(fn (Patient $patient): array => [
            'id' => $patient->id,
            'uhid' => $patient->uhid,
            'name' => trim($patient->first_name.' '.$patient->last_name),
            'mobile' => $patient->mobile,
            'status' => $patient->status,
            'url' => route('patients.show', $patient),
        ])->all() : [];
        $invoices = $includeInvoices ? $this->invoices($hospitalId, $branchId, $query)->with('patient:id,uhid,first_name,last_name')->limit($limit)->get()->map(fn (Invoice $invoice): array => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'patient_uhid' => $invoice->patient->uhid,
            'patient_name' => trim($invoice->patient->first_name.' '.$invoice->patient->last_name),
            'status' => $invoice->status,
            'total' => $invoice->total,
            'url' => route('invoices.show', $invoice),
        ])->all() : [];

        return ['query' => $query, 'patients' => $patients, 'invoices' => $invoices, 'counts' => ['patients' => count($patients), 'invoices' => count($invoices)]];
    }

    private function patients(int $hospitalId, int $branchId, string $query): Builder
    {
        $term = $this->like($query);
        $digits = preg_replace('/[^0-9]/', '', $query);

        return Patient::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)
            ->where(function (Builder $matches) use ($term, $digits): void {
                $matches->whereRaw("LOWER(uhid) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(first_name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(last_name) LIKE ? ESCAPE '!'", [$term]);
                if ($digits !== '') {
                    $matches->orWhere('mobile', 'like', '%'.$digits.'%');
                }
            })->orderByDesc('id');
    }

    private function invoices(int $hospitalId, int $branchId, string $query): Builder
    {
        $term = $this->like($query);
        $digits = preg_replace('/[^0-9]/', '', $query);

        return Invoice::query()->where('hospital_id', $hospitalId)->where('branch_id', $branchId)
            ->where(function (Builder $matches) use ($term, $digits): void {
                $matches->whereRaw("LOWER(number) LIKE ? ESCAPE '!'", [$term])->orWhereHas('patient', function (Builder $patients) use ($term, $digits): void {
                    $patients->whereRaw("LOWER(uhid) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("LOWER(first_name) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("LOWER(last_name) LIKE ? ESCAPE '!'", [$term]);
                    if ($digits !== '') {
                        $patients->orWhere('mobile', 'like', '%'.$digits.'%');
                    }
                });
            })->orderByDesc('id');
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($value)));

        return '%'.$escaped.'%';
    }
}
