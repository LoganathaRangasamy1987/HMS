<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\Medicine;
use Illuminate\Database\Seeder;
use LogicException;

class MedicineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Fictional medicines are only available in local/testing environments.');
        }

        foreach (Hospital::query()->get() as $hospital) {
            foreach ([
                ['PARA500', 'Paracetamol', 'Paracetamol', 'Tablet', '500 mg'],
                ['AMOX500', 'Amoxicillin', 'Amoxicillin', 'Capsule', '500 mg'],
                ['CET10', 'Cetirizine', 'Cetirizine', 'Tablet', '10 mg'],
            ] as [$code, $name, $generic, $form, $strength]) {
                Medicine::firstOrCreate(['hospital_id' => $hospital->id, 'code' => $code], ['name' => $name, 'generic_name' => $generic, 'form' => $form, 'strength' => $strength, 'status' => 'active']);
            }
        }
    }
}
