<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ServiceItem;
use Illuminate\Validation\ValidationException;

class ServicePricing
{
    /** @return array<string, int|string> */
    public function quote(ServiceItem $service, Branch $branch): array
    {
        if ($service->hospital_id !== $branch->hospital_id || $service->status !== 'active' || $branch->status !== 'active') {
            throw ValidationException::withMessages(['service_item_id' => 'This service is not available at the selected branch.']);
        }

        $override = $service->branchPrices()->where('branch_id', $branch->id)->first();
        if ($override && ! $override->is_available) {
            throw ValidationException::withMessages(['service_item_id' => 'This service is not available at the selected branch.']);
        }

        $price = $this->cents($override?->base_price ?? $service->base_price);
        $taxBasisPoints = $this->basisPoints($override?->tax_rate_percent ?? $service->tax_rate_percent);
        $discountType = $override?->discount_type ?? $service->discount_type;
        $discountValue = $override?->discount_value ?? $service->discount_value;
        $discount = match ($discountType) {
            'none' => 0,
            'fixed' => $this->cents($discountValue),
            'percentage' => intdiv($price * $this->basisPoints($discountValue) + 5000, 10000),
            default => throw ValidationException::withMessages(['discount_type' => 'Invalid discount rule.']),
        };
        if ($discount > $price) {
            throw ValidationException::withMessages(['discount_value' => 'Discount cannot exceed the service price.']);
        }
        $net = $price - $discount;
        $tax = intdiv($net * $taxBasisPoints + 5000, 10000);

        return [
            'service_item_id' => $service->id,
            'branch_id' => $branch->id,
            'currency' => $service->currency,
            'base_price' => $this->money($price),
            'discount_type' => $discountType,
            'discount_value' => $this->money($this->cents($discountValue)),
            'discount_amount' => $this->money($discount),
            'tax_rate_percent' => $this->money($taxBasisPoints),
            'tax_amount' => $this->money($tax),
            'total' => $this->money($net + $tax),
        ];
    }

    private function cents(string|int|float $value): int
    {
        $text = (string) $value;
        if (! preg_match('/\A\d+(?:\.\d{1,2})?\z/D', $text)) {
            throw ValidationException::withMessages(['price' => 'Money amounts must have at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function basisPoints(string|int|float $value): int
    {
        $basisPoints = $this->cents($value);
        if ($basisPoints > 10000) {
            throw ValidationException::withMessages(['tax_rate_percent' => 'Percentage cannot exceed 100.']);
        }

        return $basisPoints;
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
