<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lead Model
 *
 * This is the same as a Recipient, except it provides different methods
 */
class Lead extends Recipient
{
    const GOOD_HEALTH = 'ok';
    const WARN_HEALTH = 'warning';
    const POOR_HEALTH = 'past-due';
    const POSITIVE_OUTCOME = 'positive';
    const NEGATIVE_OUTCOME = 'negative';

    protected $fillable = ['status', 'notes', 'last_status_changed_at', 'last_responded_at', 'sent_to_crm',
        'service', 'interested', 'not_interested', 'heat'];

    public $dates = ['last_status_changed_at', 'last_responded_at'];

    /**
     * Constructor Override.
     *
     * Override to prevent non-responders from being contacted
     */
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($query) {
            // $query->whereNotNull('last_responded_at');
            $query->has('responses');
        });
    }

    // todo: find a way to perform serches in-model

    public function scopeNew($query)
    {
        return $query->whereStatus(Recipient::NEW_STATUS);
    }

    public function scopeOpen($query)
    {
        return $query->whereStatus(Recipient::OPEN_STATUS);
    }

    public function scopeClosed($query)
    {
        return $query->whereStatus(Recipient::CLOSED_STATUS);
    }

    public function scopeHasEmails($query)
    {
        return $query->whereHas('responses', function ($q) {
            $q->where('type', 'email');
        });
    }

    public function scopeHasCalls($query)
    {
        return $query->whereHas('responses', function ($q) {
            $q->where('type', 'phone');
        });
    }

    public function scopeHasSms($query)
    {
        return $query->whereHas('responses', function ($q) {
            $q->where('type', 'text');
        });
    }

    public function open() : void
    {
        $this->update([
            'status' => self::OPEN_STATUS,
            'last_status_changed_at' => now(),
        ]);
    }

    public function close() : void
    {
        $this->update([
            'status' => self::CLOSED_STATUS,
            'last_status_changed_at' => now(),
        ]);
    }

    public function reopen()
    {
        $this->update([
            'status' => self::OPEN_STATUS,
            'last_status_changed_at' => now(),
        ]);
    }
}
