<?php

namespace App\Models;

use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'code', 'name', 'generic_name', 'pharmacy_manufacturer_id', 'medicine_type_id', 'form', 'strength', 'purchase_unit_id', 'sale_unit_id', 'reorder_level', 'tax_rate_percent', 'prescription_required', 'status'];

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:3', 'tax_rate_percent' => 'decimal:2', 'prescription_required' => 'boolean'];
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(PharmacyManufacturer::class, 'pharmacy_manufacturer_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(MedicineType::class, 'medicine_type_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(MedicineUnit::class, 'purchase_unit_id');
    }

    public function saleUnit(): BelongsTo
    {
        return $this->belongsTo(MedicineUnit::class, 'sale_unit_id');
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }
}
