<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];
        foreach (['DASHBOARD.VIEW', 'ORGANIZATION.MANAGE', 'BRANCH.MANAGE', 'DEPARTMENT.MANAGE', 'STAFF.MANAGE', 'AUDIT.VIEW', 'DOCUMENT.MANAGE', 'PATIENT.VIEW', 'PATIENT.MANAGE', 'PATIENT_HISTORY.VIEW', 'PATIENT_HISTORY.MANAGE', 'PATIENT_DOCUMENT.VIEW', 'PATIENT_DOCUMENT.MANAGE', 'DOCTOR.VIEW', 'DOCTOR.MANAGE', 'DOCTOR_AVAILABILITY.VIEW', 'DOCTOR_AVAILABILITY.MANAGE', 'APPOINTMENT.VIEW', 'APPOINTMENT.MANAGE', 'ENCOUNTER.VIEW', 'ENCOUNTER.MANAGE', 'MEDICINE.VIEW', 'MEDICINE.MANAGE', 'PHARMACY_CATALOG.VIEW', 'PHARMACY_CATALOG.MANAGE', 'PHARMACY_PURCHASE.VIEW', 'PHARMACY_PURCHASE.MANAGE', 'PHARMACY_STOCK.VIEW', 'PHARMACY_SALE.VIEW', 'PHARMACY_SALE.MANAGE', 'PHARMACY_ADJUSTMENT.VIEW', 'PHARMACY_ADJUSTMENT.MANAGE', 'PHARMACY_ADJUSTMENT.APPROVE', 'PHARMACY_WORKSPACE.VIEW', 'PHARMACY_SETTINGS.MANAGE', 'PHARMACY_RECONCILIATION.VIEW', 'LAB_CATALOG.VIEW', 'LAB_CATALOG.MANAGE', 'LAB_ORDER.VIEW', 'LAB_ORDER.MANAGE', 'LAB_SPECIMEN.VIEW', 'LAB_SPECIMEN.MANAGE', 'LAB_RESULT.VIEW', 'LAB_RESULT.ENTER', 'LAB_RESULT.VERIFY', 'LAB_WORKLIST.VIEW', 'SERVICE.VIEW', 'SERVICE.MANAGE', 'INVOICE.MANAGE', 'PAYMENT.MANAGE', 'FINANCIAL_ADJUSTMENT.MANAGE'] as $name) {
            [$module, $action] = explode('.', $name);
            $permissions[$name] = Permission::firstOrCreate(['name' => $name], compact('module', 'action'))->id;
        }
        foreach (['HOSPITAL_ADMIN' => 'Hospital administrator', 'RECEPTIONIST' => 'Receptionist', 'DOCTOR' => 'Doctor', 'LAB_TECHNICIAN' => 'Laboratory technician', 'PHARMACIST' => 'Pharmacist'] as $name => $label) {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $label]);
            $assigned = match ($name) {
                'HOSPITAL_ADMIN' => array_values(array_diff_key($permissions, array_flip(['PATIENT_HISTORY.VIEW', 'PATIENT_HISTORY.MANAGE', 'PATIENT_DOCUMENT.VIEW', 'PATIENT_DOCUMENT.MANAGE', 'ENCOUNTER.VIEW', 'ENCOUNTER.MANAGE']))),
                'RECEPTIONIST' => [$permissions['DASHBOARD.VIEW'], $permissions['PATIENT.VIEW'], $permissions['PATIENT.MANAGE'], $permissions['DOCTOR.VIEW'], $permissions['DOCTOR_AVAILABILITY.VIEW'], $permissions['APPOINTMENT.VIEW'], $permissions['APPOINTMENT.MANAGE'], $permissions['LAB_CATALOG.VIEW'], $permissions['LAB_ORDER.VIEW'], $permissions['LAB_ORDER.MANAGE'], $permissions['LAB_SPECIMEN.VIEW'], $permissions['LAB_SPECIMEN.MANAGE'], $permissions['LAB_WORKLIST.VIEW'], $permissions['SERVICE.VIEW'], $permissions['INVOICE.MANAGE'], $permissions['PAYMENT.MANAGE']],
                'DOCTOR' => [$permissions['DASHBOARD.VIEW'], $permissions['PATIENT.VIEW'], $permissions['PATIENT_HISTORY.VIEW'], $permissions['PATIENT_HISTORY.MANAGE'], $permissions['PATIENT_DOCUMENT.VIEW'], $permissions['PATIENT_DOCUMENT.MANAGE'], $permissions['DOCTOR.VIEW'], $permissions['DOCTOR_AVAILABILITY.VIEW'], $permissions['DOCTOR_AVAILABILITY.MANAGE'], $permissions['APPOINTMENT.VIEW'], $permissions['ENCOUNTER.VIEW'], $permissions['ENCOUNTER.MANAGE'], $permissions['MEDICINE.VIEW'], $permissions['LAB_CATALOG.VIEW'], $permissions['LAB_ORDER.VIEW'], $permissions['LAB_ORDER.MANAGE'], $permissions['LAB_SPECIMEN.VIEW'], $permissions['LAB_RESULT.VIEW'], $permissions['LAB_RESULT.VERIFY'], $permissions['LAB_WORKLIST.VIEW']],
                'LAB_TECHNICIAN' => [$permissions['DASHBOARD.VIEW'], $permissions['PATIENT.VIEW'], $permissions['LAB_CATALOG.VIEW'], $permissions['LAB_ORDER.VIEW'], $permissions['LAB_SPECIMEN.VIEW'], $permissions['LAB_SPECIMEN.MANAGE'], $permissions['LAB_RESULT.VIEW'], $permissions['LAB_RESULT.ENTER'], $permissions['LAB_WORKLIST.VIEW']],
                default => [$permissions['DASHBOARD.VIEW'], $permissions['MEDICINE.VIEW'], $permissions['MEDICINE.MANAGE'], $permissions['PHARMACY_CATALOG.VIEW'], $permissions['PHARMACY_CATALOG.MANAGE'], $permissions['PHARMACY_PURCHASE.VIEW'], $permissions['PHARMACY_PURCHASE.MANAGE'], $permissions['PHARMACY_STOCK.VIEW'], $permissions['PHARMACY_SALE.VIEW'], $permissions['PHARMACY_SALE.MANAGE'], $permissions['PHARMACY_ADJUSTMENT.VIEW'], $permissions['PHARMACY_ADJUSTMENT.MANAGE'], $permissions['PHARMACY_WORKSPACE.VIEW']],
            };
            $role->permissions()->sync($assigned);
        }
    }
}
