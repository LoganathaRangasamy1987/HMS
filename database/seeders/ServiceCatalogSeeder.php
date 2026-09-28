<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\ServiceItem;
use Illuminate\Database\Seeder;
use LogicException;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional service prices are only available in local/testing environments.');
        }
        $hospital = Hospital::where('code', 'LOTUS')->first();
        if (! $hospital) {
            return;
        }
        ServiceItem::firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CONSULT-GEN'], [
            'name' => 'General consultation', 'type' => 'CONSULTATION',
            'description' => 'Fictional demonstration service and price.', 'currency' => 'INR',
            'base_price' => '500.00', 'tax_rate_percent' => '0.00',
            'discount_type' => 'none', 'discount_value' => '0.00', 'status' => 'active',
        ]);
    }
}
