<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $fillable = ['operational_notification_id', 'channel', 'status', 'attempts', 'last_attempt_at', 'sent_at', 'failed_at', 'last_error'];

    protected function casts(): array
    {
        return ['last_attempt_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(OperationalNotification::class, 'operational_notification_id');
    }
}
