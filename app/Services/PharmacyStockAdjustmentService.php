<?php

namespace App\Services;

use App\Models\MedicineBatch;
use App\Models\PharmacyPurchaseItem;
use App\Models\PharmacySaleItem;
use App\Models\PharmacyStockAdjustment;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyStockAdjustmentService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function request(array $data): PharmacyStockAdjustment
    {
        return DB::transaction(function () use ($data): PharmacyStockAdjustment {
            $payloadHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = PharmacyStockAdjustment::where('hospital_id', $this->tenant->hospitalId())->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages(['request_key' => 'This request key belongs to a different stock adjustment.']);
                }

                return $existing->load($this->relations());
            }

            $batch = MedicineBatch::where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->findOrFail($data['medicine_batch_id']);
            $saleItem = isset($data['pharmacy_sale_item_id']) ? PharmacySaleItem::where('medicine_batch_id', $batch->id)->whereHas('sale', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId()))->findOrFail($data['pharmacy_sale_item_id']) : null;
            $purchaseItem = isset($data['pharmacy_purchase_item_id']) ? PharmacyPurchaseItem::where('medicine_batch_id', $batch->id)->whereHas('purchase', fn ($query) => $query->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId()))->findOrFail($data['pharmacy_purchase_item_id']) : null;
            if (($data['type'] === 'SALE_RETURN' && (! $saleItem || $purchaseItem)) || ($data['type'] === 'PURCHASE_RETURN' && (! $purchaseItem || $saleItem)) || (in_array($data['type'], ['DAMAGED', 'EXPIRED'], true) && ($saleItem || $purchaseItem))) {
                throw ValidationException::withMessages(['type' => 'The selected adjustment type requires the matching source line.']);
            }
            $adjustment = PharmacyStockAdjustment::create([
                'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'medicine_id' => $batch->medicine_id,
                'medicine_batch_id' => $batch->id, 'pharmacy_sale_item_id' => $saleItem?->id, 'pharmacy_purchase_item_id' => $purchaseItem?->id,
                'type' => $data['type'], 'quantity' => $this->quantity((float) $data['quantity']), 'reason' => trim($data['reason']), 'status' => 'PENDING',
                'request_key' => $data['request_key'], 'payload_hash' => $payloadHash, 'requested_by' => auth()->id(), 'requested_at' => now(),
            ]);
            $this->audit->record('pharmacy_stock_adjustments', 'requested', $adjustment, null, $adjustment->toArray());

            return $adjustment->load($this->relations());
        }, 3);
    }

    public function decide(PharmacyStockAdjustment $adjustment, string $decision, ?string $reason): PharmacyStockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $decision, $reason): PharmacyStockAdjustment {
            $adjustment = PharmacyStockAdjustment::whereKey($adjustment->id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            if ($adjustment->status !== 'PENDING') {
                throw ValidationException::withMessages(['adjustment' => 'This adjustment has already been decided.']);
            }
            if ($adjustment->requested_by === auth()->id()) {
                throw ValidationException::withMessages(['adjustment' => 'A different administrator must decide this adjustment.']);
            }
            if ($decision === 'REJECTED') {
                $adjustment->update(['status' => 'REJECTED', 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_reason' => trim((string) $reason)]);
                $this->audit->record('pharmacy_stock_adjustments', 'rejected', $adjustment, null, $adjustment->toArray());

                return $adjustment->load($this->relations());
            }

            $batch = MedicineBatch::whereKey($adjustment->medicine_batch_id)->where('hospital_id', $this->tenant->hospitalId())->where('branch_id', $this->tenant->branchId())->lockForUpdate()->firstOrFail();
            $quantity = (float) $adjustment->quantity;
            $approvedQuery = PharmacyStockAdjustment::where('status', 'APPROVED')->where('type', $adjustment->type);
            if ($adjustment->type === 'SALE_RETURN') {
                $source = PharmacySaleItem::findOrFail($adjustment->pharmacy_sale_item_id);
                $already = (float) (clone $approvedQuery)->where('pharmacy_sale_item_id', $source->id)->sum('quantity');
                if ($quantity > (float) $source->quantity - $already) {
                    throw ValidationException::withMessages(['quantity' => 'Approved sale returns cannot exceed the dispensed quantity.']);
                }
            } elseif ($adjustment->type === 'PURCHASE_RETURN') {
                $source = PharmacyPurchaseItem::findOrFail($adjustment->pharmacy_purchase_item_id);
                $already = (float) (clone $approvedQuery)->where('pharmacy_purchase_item_id', $source->id)->sum('quantity');
                if ($quantity > ((float) $source->quantity + (float) $source->free_quantity) - $already) {
                    throw ValidationException::withMessages(['quantity' => 'Approved purchase returns cannot exceed the received quantity.']);
                }
            }
            if ($adjustment->type === 'EXPIRED' && $batch->expires_on->format('Y-m-d') > now($this->tenant->branch()->timezone)->toDateString()) {
                throw ValidationException::withMessages(['medicine_batch_id' => 'Only an expired batch can use an expiry write-off.']);
            }
            $direction = $adjustment->type === 'SALE_RETURN' ? 1 : -1;
            if ($direction < 0 && (float) $batch->on_hand_quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'The batch does not have enough on-hand stock for this adjustment.']);
            }
            $balance = (float) $batch->on_hand_quantity + ($direction * $quantity);
            $batch->update(['on_hand_quantity' => $this->quantity($balance)]);
            $movement = $batch->movements()->create([
                'hospital_id' => $this->tenant->hospitalId(), 'branch_id' => $this->tenant->branchId(), 'medicine_id' => $batch->medicine_id,
                'type' => $adjustment->type, 'quantity' => $this->quantity($direction * $quantity), 'balance_after' => $this->quantity($balance),
                'reference_type' => PharmacyStockAdjustment::class, 'reference_id' => $adjustment->id, 'recorded_by' => auth()->id(), 'recorded_at' => now(),
            ]);
            $adjustment->update(['status' => 'APPROVED', 'stock_movement_id' => $movement->id, 'decided_by' => auth()->id(), 'decided_at' => now(), 'decision_reason' => $reason ? trim($reason) : null]);
            $this->audit->record('pharmacy_stock_adjustments', 'approved', $adjustment, null, $adjustment->toArray());

            return $adjustment->load($this->relations());
        }, 3);
    }

    /** @return array<int, string> */
    public function relations(): array
    {
        return ['batch.medicine:id,code,name', 'saleItem.sale.invoice:id,number,status,total', 'purchaseItem.purchase:id,number,supplier_invoice_number', 'movement', 'requestedBy:id,name', 'decidedBy:id,name'];
    }

    private function quantity(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}
