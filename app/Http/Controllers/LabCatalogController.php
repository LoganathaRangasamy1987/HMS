<?php

namespace App\Http\Controllers;

use App\Models\LabCategory;
use App\Models\LabSampleType;
use App\Models\LabTest;
use App\Models\LabTestVersion;
use App\Models\LabUnit;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LabCatalogController extends Controller
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->view();
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $tests = $this->tests()
            ->when(! $this->tenant->can('LAB_CATALOG.MANAGE'), fn (Builder $query) => $query->where('status', 'active')->whereNotNull('active_version_id'))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $search) => $search->where('code', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['activeVersion.category', 'activeVersion.sampleType', 'activeVersion.parameters.unit'])
            ->orderBy('name')->paginate(15)->withQueryString();
        $data = ['tests' => $tests, ...$this->lookups()];

        return $request->expectsJson() ? response()->json([...$tests->toArray(), 'lookups' => $this->lookups()]) : view('laboratory.catalog', $data);
    }

    public function show(string $test): View|JsonResponse
    {
        $this->view();
        $test = $this->tests()->with(['versions' => fn ($query) => $query->with(['category', 'sampleType', 'parameters.unit'])->orderByDesc('version')])->findOrFail($test);

        return request()->expectsJson() ? response()->json(['data' => $test]) : view('laboratory.test', ['test' => $test, ...$this->lookups()]);
    }

    public function storeCategory(Request $request): JsonResponse|RedirectResponse
    {
        return $this->storeLookup($request, LabCategory::class, 'Laboratory category');
    }

    public function storeSampleType(Request $request): JsonResponse|RedirectResponse
    {
        return $this->storeLookup($request, LabSampleType::class, 'Sample type');
    }

    public function storeUnit(Request $request): JsonResponse|RedirectResponse
    {
        return $this->storeLookup($request, LabUnit::class, 'Laboratory unit', true);
    }

    public function storeTest(Request $request): JsonResponse|RedirectResponse
    {
        $this->manage();
        $identity = $this->testIdentity($request);
        $versionData = $this->versionData($request);
        $test = DB::transaction(function () use ($identity, $versionData, $request): LabTest {
            $test = LabTest::create([...$identity, 'hospital_id' => $this->tenant->hospitalId(), 'status' => 'active']);
            $version = $this->createVersion($test, $versionData, $request, 1, 'active');
            $test->update(['active_version_id' => $version->id]);
            $this->audit->record('lab_tests', 'created', $test, null, $test->toArray());

            return $test->load(['activeVersion.category', 'activeVersion.sampleType', 'activeVersion.parameters.unit']);
        });

        return $request->expectsJson() ? response()->json(['data' => $test], 201) : redirect()->route('laboratory.tests.show', $test)->with('status', 'Laboratory test created with active version 1.');
    }

    public function updateTest(Request $request, string $test): JsonResponse|RedirectResponse
    {
        $this->manage();
        $test = $this->tests()->findOrFail($test);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'status' => ['required', Rule::in(['active', 'inactive'])]]);
        $old = $test->toArray();
        $test->updateOrFail($data);
        $this->audit->record('lab_tests', 'updated', $test, $old, $test->fresh()->toArray());

        return $request->expectsJson() ? response()->json(['data' => $test->fresh()]) : back()->with('status', 'Laboratory test updated.');
    }

    public function storeVersion(Request $request, string $test): JsonResponse|RedirectResponse
    {
        $this->manage();
        $test = $this->tests()->findOrFail($test);
        $data = $this->versionData($request);
        $version = DB::transaction(function () use ($test, $data, $request): LabTestVersion {
            $next = ((int) $test->versions()->lockForUpdate()->max('version')) + 1;

            return $this->createVersion($test, $data, $request, $next, 'draft');
        });

        return $request->expectsJson() ? response()->json(['data' => $version->load(['category', 'sampleType', 'parameters.unit'])], 201) : back()->with('status', "Draft version {$version->version} created.");
    }

    public function activateVersion(Request $request, string $test, string $version): JsonResponse|RedirectResponse
    {
        $this->manage();
        $test = $this->tests()->findOrFail($test);
        $version = $test->versions()->where('status', 'draft')->findOrFail($version);
        DB::transaction(function () use ($test, $version): void {
            $lockedTest = $this->tests()->lockForUpdate()->findOrFail($test->id);
            $lockedTest->versions()->where('status', 'active')->update(['status' => 'archived']);
            $version->update(['status' => 'active', 'activated_at' => now()]);
            $lockedTest->update(['active_version_id' => $version->id]);
            $this->audit->record('lab_test_versions', 'activated', $version, null, $version->fresh()->toArray());
        });

        return $request->expectsJson() ? response()->json(['data' => $version->fresh(['parameters.unit'])]) : back()->with('status', "Version {$version->version} activated.");
    }

    /** @param class-string<LabCategory|LabSampleType|LabUnit> $model */
    private function storeLookup(Request $request, string $model, string $label, bool $unit = false): JsonResponse|RedirectResponse
    {
        $this->manage();
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $table = (new $model)->getTable();
        $rules = ['code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9_-]+\z/D', Rule::unique($table)->where('hospital_id', $this->tenant->hospitalId())], 'name' => ['required', 'string', 'max:150']];
        if ($unit) {
            $rules['symbol'] = ['required', 'string', 'max:40'];
        }
        $record = $model::create([...$request->validate($rules), 'hospital_id' => $this->tenant->hospitalId(), 'status' => 'active']);
        $this->audit->record($table, 'created', $record, null, $record->toArray());

        return $request->expectsJson() ? response()->json(['data' => $record], 201) : back()->with('status', "{$label} created.");
    }

    /** @return array<string, mixed> */
    private function testIdentity(Request $request): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate(['code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9_-]+\z/D', Rule::unique('lab_tests')->where('hospital_id', $this->tenant->hospitalId())], 'name' => ['required', 'string', 'max:150']]);
    }

    /** @return array<string, mixed> */
    private function versionData(Request $request): array
    {
        $hospitalId = $this->tenant->hospitalId();
        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('lab_categories', 'id')->where('hospital_id', $hospitalId)->where('status', 'active')],
            'sample_type_id' => ['required', 'integer', Rule::exists('lab_sample_types', 'id')->where('hospital_id', $hospitalId)->where('status', 'active')],
            'sample_volume' => ['nullable', 'string', 'max:100'], 'instructions' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'decimal:0,2', 'between:0,9999999999.99'], 'parameters' => ['required', 'array', 'min:1', 'max:100'],
            'parameters.*.code' => ['required', 'string', 'max:40', 'regex:/\A[A-Za-z0-9_-]+\z/D', 'distinct:ignore_case'],
            'parameters.*.name' => ['required', 'string', 'max:150'], 'parameters.*.result_type' => ['required', Rule::in(['NUMERIC', 'TEXT', 'BOOLEAN'])],
            'parameters.*.unit_id' => ['nullable', 'integer', Rule::exists('lab_units', 'id')->where('hospital_id', $hospitalId)->where('status', 'active')],
            'parameters.*.reference_min' => ['nullable', 'numeric', 'between:-9999999999,9999999999'], 'parameters.*.reference_max' => ['nullable', 'numeric', 'between:-9999999999,9999999999'],
            'parameters.*.reference_text' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach ($data['parameters'] as $index => &$parameter) {
            $parameter['code'] = strtoupper(trim($parameter['code']));
            if ($parameter['result_type'] !== 'NUMERIC' && (! empty($parameter['unit_id']) || isset($parameter['reference_min']) || isset($parameter['reference_max']))) {
                throw ValidationException::withMessages(["parameters.{$index}.result_type" => 'Only numeric parameters can use a unit or numeric reference range.']);
            }
            if (isset($parameter['reference_min'], $parameter['reference_max']) && (float) $parameter['reference_min'] > (float) $parameter['reference_max']) {
                throw ValidationException::withMessages(["parameters.{$index}.reference_max" => 'Reference maximum must be greater than or equal to the minimum.']);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function createVersion(LabTest $test, array $data, Request $request, int $number, string $status): LabTestVersion
    {
        $parameters = $data['parameters'];
        unset($data['parameters']);
        $version = $test->versions()->create([...$data, 'version' => $number, 'currency' => 'INR', 'status' => $status, 'created_by' => $request->user()->id, 'activated_at' => $status === 'active' ? now() : null]);
        foreach ($parameters as $index => $parameter) {
            $version->parameters()->create([...$parameter, 'sort_order' => $index + 1]);
        }
        $this->audit->record('lab_test_versions', 'created', $version, null, $version->toArray());

        return $version;
    }

    /** @return array<string, mixed> */
    private function lookups(): array
    {
        $hospitalId = $this->tenant->hospitalId();

        return ['categories' => LabCategory::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(), 'sampleTypes' => LabSampleType::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(), 'units' => LabUnit::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get()];
    }

    private function tests(): Builder
    {
        return LabTest::where('hospital_id', $this->tenant->hospitalId());
    }

    private function view(): void
    {
        abort_unless($this->tenant->can('LAB_CATALOG.VIEW'), 403);
    }

    private function manage(): void
    {
        abort_unless($this->tenant->can('LAB_CATALOG.MANAGE'), 403);
    }
}
