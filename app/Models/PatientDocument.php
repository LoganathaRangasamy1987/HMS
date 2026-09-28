<?php

namespace App\Models;

use Database\Factories\PatientDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientDocument extends Model
{
    /** @use HasFactory<PatientDocumentFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'patient_id', 'branch_id', 'uploaded_by', 'category', 'name', 'path', 'mime_type', 'size'];

    protected $hidden = ['path'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
