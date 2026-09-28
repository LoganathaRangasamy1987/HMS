<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\Patient;
use App\Services\AuditService;
use App\Services\PatientIdentityService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use LogicException;

class PatientController extends Controller
{
    public function __construct(private TenantContext $tenant, private PatientIdentityService $identity, private AuditService $audit) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($this->tenant->can('PATIENT.VIEW'), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $patients = $this->patients()->with('branch:id,name')
            ->when(isset($filters['q']) && $filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['q'];
                $query->where(function (Builder $matches) use ($search): void {
                    $matches->whereRaw("LOWER(uhid) LIKE ? ESCAPE '!'", [$this->likePattern($search)])
                        ->orWhere(function (Builder $names) use ($search): void {
                            foreach (preg_split('/\s+/u', trim($search)) as $part) {
                                $names->where(function (Builder $name) use ($part): void {
                                    $name->whereRaw("LOWER(first_name) LIKE ? ESCAPE '!'", [$this->likePattern($part)])
                                        ->orWhereRaw("LOWER(last_name) LIKE ? ESCAPE '!'", [$this->likePattern($part)]);
                                });
                            }
                        });
                    $digits = preg_replace('/[^0-9]/', '', $search);
                    if ($digits !== '' && preg_match('/^[+0-9\s().-]+$/D', $search)) {
                        $matches->orWhereRaw($this->phoneExpression('mobile')." LIKE ? ESCAPE '!'", ['%'.$digits.'%'])
                            ->orWhereRaw($this->phoneExpression('emergency_contact_mobile')." LIKE ? ESCAPE '!'", ['%'.$digits.'%']);
                    }
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')->paginate(15)->withQueryString();

        return $request->expectsJson() ? response()->json($patients) : view('patients.index', compact('patients'));
    }

    public function create(): View
    {
        abort_unless($this->tenant->can('PATIENT.MANAGE'), 403);

        return view('patients.form', ['patient' => new Patient(['status' => 'active', 'date_of_birth_unknown' => false])]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PATIENT.MANAGE'), 403);
        $demographics = $this->demographics($request);
        $request->validate(['duplicates_confirmed' => ['sometimes', 'boolean']]);

        return DB::transaction(function () use ($request, $demographics): JsonResponse|RedirectResponse {
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->can('PATIENT.MANAGE'), 403);
            $duplicates = $this->possibleDuplicates($demographics);
            if ($duplicates !== [] && ! $request->boolean('duplicates_confirmed')) {
                $message = 'Review the possible matches and confirm this is a different patient before registering.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message, 'errors' => ['duplicates_confirmed' => [$message]], 'duplicates' => $duplicates], 422)
                    : back()->withInput()->withErrors(['duplicates_confirmed' => $message])->with('duplicates', $duplicates);
            }

            $patient = $this->identity->create($this->tenant->hospital(), $this->tenant->branch(), $demographics, $request->user());
            $this->audit->record('patients', 'created', $patient, null, $patient->toArray());

            return $request->expectsJson()
                ? response()->json(['data' => $patient], 201)
                : redirect()->route('patients.show', $patient)->with('status', 'Patient registered.');
        }, attempts: 5);
    }

    public function show(Request $request, string $patient): View|JsonResponse
    {
        abort_unless($this->tenant->can('PATIENT.VIEW'), 403);
        $patient = $this->patients()->with(['branch:id,name', 'registeredBy:id,name'])->findOrFail($patient);

        return $request->expectsJson() ? response()->json(['data' => $patient]) : view('patients.show', compact('patient'));
    }

    public function edit(string $patient): View
    {
        abort_unless($this->tenant->can('PATIENT.MANAGE'), 403);
        $patient = $this->patients()->with('branch:id,name')->findOrFail($patient);

        return view('patients.form', compact('patient'));
    }

    public function update(Request $request, string $patient): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant->can('PATIENT.MANAGE'), 403);
        $patient = DB::transaction(function () use ($request, $patient): Patient {
            Hospital::whereKey($this->tenant->hospitalId())->lockForUpdate()->firstOrFail();
            abort_unless($this->tenant->can('PATIENT.MANAGE'), 403);
            $patient = $this->patients()->whereKey($patient)->lockForUpdate()->firstOrFail();
            $validated = $this->demographics($request);
            $old = $patient->toArray();
            $patient->fill($validated);
            if ($patient->isDirty()) {
                if (! $patient->saveOrFail()) {
                    throw new LogicException('Patient update was cancelled before it could be saved.');
                }
                $this->audit->record('patients', 'updated', $patient, $old, $patient->fresh()->toArray());
            }

            return $patient->fresh();
        }, attempts: 5);

        return $request->expectsJson()
            ? response()->json(['data' => $patient])
            : redirect()->route('patients.show', $patient)->with('status', 'Patient updated.');
    }

    public function duplicates(Request $request): JsonResponse
    {
        abort_unless($this->tenant->can('PATIENT.VIEW'), 403);
        $criteria = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Kolkata')->toDateString()],
            'mobile' => $this->phoneRules(),
            'emergency_contact_mobile' => $this->phoneRules(),
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        return response()->json(['data' => $this->possibleDuplicates($criteria)]);
    }

    private function patients(): Builder
    {
        return Patient::query()->forHospital($this->tenant->hospitalId());
    }

    /** @return array<string, mixed> */
    private function demographics(Request $request): array
    {
        $request->validate(['mobile' => $this->phoneRules(), 'emergency_contact_mobile' => $this->phoneRules()]);
        $demographics = $request->only((new Patient)->getFillable());
        foreach (['mobile', 'emergency_contact_mobile'] as $field) {
            if (isset($demographics[$field])) {
                $demographics[$field] = preg_replace('/[\s().-]/', '', $demographics[$field]);
            }
        }
        $validated = $this->identity->validateDemographics($demographics);
        if ($validated['date_of_birth_unknown']) {
            $validated['date_of_birth'] = null;
        }

        return $validated;
    }

    /** @return list<string> */
    private function phoneRules(): array
    {
        return ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]+$/D', 'regex:/^(?:[^0-9]*[0-9]){7,15}[^0-9]*$/D'];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array<string, mixed>>
     */
    private function possibleDuplicates(array $criteria): array
    {
        $phones = array_filter(array_unique([
            preg_replace('/[^0-9]/', '', $criteria['mobile'] ?? ''),
            preg_replace('/[^0-9]/', '', $criteria['emergency_contact_mobile'] ?? ''),
        ]));
        $email = mb_strtolower(trim($criteria['email'] ?? ''));
        $firstName = mb_strtolower(trim($criteria['first_name'] ?? ''));
        $lastName = mb_strtolower(trim($criteria['last_name'] ?? ''));
        if ($phones === [] && $email === '' && $firstName === '') {
            return [];
        }

        return $this->patients()->where(function (Builder $query) use ($phones, $email, $firstName, $lastName, $criteria): void {
            foreach ($phones as $phone) {
                $query->orWhereRaw($this->phoneExpression('mobile').' = ?', [$phone])
                    ->orWhereRaw($this->phoneExpression('emergency_contact_mobile').' = ?', [$phone]);
            }
            if ($email !== '') {
                $query->orWhereRaw('LOWER(email) = ?', [$email]);
            }
            if ($firstName !== '') {
                $query->orWhere(function (Builder $name) use ($firstName, $lastName, $criteria): void {
                    $name->whereRaw('LOWER(first_name) = ?', [$firstName])
                        ->whereRaw("LOWER(COALESCE(last_name, '')) = ?", [$lastName]);
                    if (! empty($criteria['date_of_birth'])) {
                        $name->whereDate('date_of_birth', $criteria['date_of_birth']);
                    }
                });
            }
        })->orderBy('id')->limit(10)
            ->get(['id', 'uhid', 'first_name', 'last_name', 'date_of_birth', 'date_of_birth_unknown', 'mobile', 'email', 'emergency_contact_mobile', 'status'])->toArray();
    }

    private function phoneExpression(string $column): string
    {
        $expression = "COALESCE($column, '')";
        foreach ([' ', '+', '-', '(', ')', '.'] as $separator) {
            $expression = "REPLACE($expression, '$separator', '')";
        }

        return $expression;
    }

    private function likePattern(string $search): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
    }
}
