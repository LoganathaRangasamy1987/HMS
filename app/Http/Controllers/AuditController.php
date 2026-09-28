<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function index(Request $request)
    {
        abort_unless($this->tenant->isAdmin(), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:50'],
        ]);
        $logs = AuditLog::where('hospital_id', $this->tenant->hospitalId())->with(['user', 'branch'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($search) => $search->where('module', 'like', '%'.$q.'%')->orWhere('action', 'like', '%'.$q.'%')))
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->latest('created_at')->latest('id')->paginate(25)->withQueryString();

        return $request->expectsJson() ? response()->json($logs) : view('audit.index', compact('logs'));
    }
}
