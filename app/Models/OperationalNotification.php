<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OperationalNotification extends Model
{
    protected $fillable = ['hospital_id', 'branch_id', 'recipient_user_id', 'event_key', 'type', 'title', 'message', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $notification): void {
            if (array_diff(array_keys($notification->getDirty()), ['read_at', 'updated_at'])) {
                throw new LogicException('Notification content cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('Notifications cannot be deleted.'));
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
