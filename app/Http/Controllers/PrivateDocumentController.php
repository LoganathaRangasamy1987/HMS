<?php

namespace App\Http\Controllers;

use App\Models\PrivateDocument;
use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PrivateDocumentController extends Controller
{
    public function store(Request $request, TenantContext $tenant, AuditService $audit): JsonResponse
    {
        abort_unless($tenant->isAdmin(), 403);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $file = $request->file('file');
        $path = $file->store('hospitals/'.$tenant->hospitalId().'/branches/'.$tenant->branchId(), 'local');
        try {
            $document = DB::transaction(function () use ($request, $tenant, $file, $path, $audit): PrivateDocument {
                $document = PrivateDocument::create(['hospital_id' => $tenant->hospitalId(), 'branch_id' => $tenant->branchId(), 'user_id' => $request->user()->id, 'name' => basename(str_replace('\\', '/', $file->getClientOriginalName())), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
                $audit->record('documents', 'uploaded', $document);

                return $document;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return response()->json(['data' => $document], 201);
    }

    public function show(string $document, TenantContext $tenant, AuditService $audit): StreamedResponse
    {
        abort_unless($tenant->isAdmin(), 403);
        $document = PrivateDocument::where('hospital_id', $tenant->hospitalId())->where('branch_id', $tenant->branchId())->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        $audit->record('documents', 'downloaded', $document);

        return Storage::disk('local')->download($document->path, $document->name, ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
