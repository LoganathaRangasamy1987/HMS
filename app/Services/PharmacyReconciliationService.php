<?php

namespace App\Services;

use App\Models\MedicineBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PharmacyReconciliationService
{
    /** @return array{batches: Collection<int, MedicineBatch>, batch_count: int, reconciled_count: int, discrepancy_count: int, movement_count: int, movement_totals: array<string, string>} */
    public function report(int $hospitalId, int $branchId, ?string $search = null, ?string $status = null): array
    {
        $batches = MedicineBatch::where('hospital_id', $hospitalId)->where('branch_id', $branchId)
            ->with(['medicine:id,code,name', 'latestMovement' => fn ($query) => $query->select('stock_movements.id', 'stock_movements.medicine_batch_id', 'stock_movements.balance_after', 'stock_movements.recorded_at')])
            ->withSum('movements as ledger_quantity', 'quantity')->withCount('movements')
            ->when($search, function (Builder $query, string $search): void {
                $term = '%'.trim($search).'%';
                $query->where(fn (Builder $query) => $query->where('batch_number', 'like', $term)
                    ->orWhereHas('medicine', fn (Builder $query) => $query->where('code', 'like', $term)->orWhere('name', 'like', $term)));
            })->orderBy('medicine_id')->orderBy('expires_on')->get()
            ->map(function (MedicineBatch $batch): MedicineBatch {
                $onHand = $this->quantity((float) $batch->on_hand_quantity);
                $ledger = $this->quantity((float) ($batch->ledger_quantity ?? 0));
                $latest = $batch->latestMovement ? $this->quantity((float) $batch->latestMovement->balance_after) : '0.000';
                $batch->ledger_balance = $ledger;
                $batch->latest_recorded_balance = $latest;
                $batch->reconciliation_status = $onHand === $ledger && $onHand === $latest ? 'RECONCILED' : 'DISCREPANCY';

                return $batch;
            });
        $filtered = $status ? $batches->where('reconciliation_status', $status)->values() : $batches;
        $movementTotals = StockMovement::where('hospital_id', $hospitalId)->where('branch_id', $branchId)
            ->selectRaw('type, SUM(quantity) AS total')->groupBy('type')->pluck('total', 'type')
            ->map(fn ($value) => $this->quantity((float) $value))->all();

        return [
            'batches' => $filtered,
            'batch_count' => $batches->count(),
            'reconciled_count' => $batches->where('reconciliation_status', 'RECONCILED')->count(),
            'discrepancy_count' => $batches->where('reconciliation_status', 'DISCREPANCY')->count(),
            'movement_count' => StockMovement::where('hospital_id', $hospitalId)->where('branch_id', $branchId)->count(),
            'movement_totals' => $movementTotals,
        ];
    }

    private function quantity(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
