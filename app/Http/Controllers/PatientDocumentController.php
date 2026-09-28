<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientDocument;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PatientDocumentController extends Controller
{
    public function store(Request $request, string $patient): JsonResponse|RedirectResponse
    {
        abort_unless($this->tenant()->can('PATIENT_DOCUMENT.MANAGE'), 403);
        $patient = $this->patient($patient);
        $validated = $request->validate(['category' => ['required', Rule::in(['referral', 'report', 'prescription', 'consent', 'other'])], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $file = $request->file('file');
        $path = $file->store('hospitals/'.$this->tenant()->hospitalId().'/patients/'.$patient->id, 'local');
        try {
            $document = DB::transaction(function () use ($request, $patient, $validated, $file, $path): PatientDocument {
                $document = $patient->documents()->create(['hospital_id' => $this->tenant()->hospitalId(), 'branch_id' => $this->tenant()->branchId(), 'uploaded_by' => $request->user()->id, 'category' => $validated['category'], 'name' => basename(str_replace('\\', '/', $file->getClientOriginalName())), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
                app(AuditService::class)->record('patient_documents', 'uploaded', $document, null, $document->toArray());

                return $document;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return $request->expectsJson() ? response()->json(['data' => $document], 201) : back()->with('status', 'Patient document uploaded.');
    }

    public function show(string $patient, string $document): StreamedResponse
    {
        abort_unless($this->tenant()->can('PATIENT_DOCUMENT.VIEW'), 403);
        $patient = $this->patient($patient);
        $document = $patient->documents()->where('hospital_id', $this->tenant()->hospitalId())->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        app(AuditService::class)->record('patient_documents', 'downloaded', $document);

        return Storage::disk('local')->download($document->path, $document->name, ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    private function patient(string $id): Patient
    {
        return Patient::query()->forHospital($this->tenant()->hospitalId())->findOrFail($id);
    }
}
