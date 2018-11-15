<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipient extends Model
{
    protected $table = 'recipients';

    public static $mappable = [
        'first_name', 'last_name', 'email', 'phone', 'address1', 'city', 'state', 'zip',
        'make', 'model', 'vin'
    ];

    public function getVehicleAttribute()
    {
        return $this->year . ' ' . $this->make . ' ' . $this->model;
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'campaign_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'recipient_id', 'id');
    }

    public function drops()
    {
        return $this->belongsToMany(Drop::class, 'deployment_recipients', 'recipient_id', 'deployment_id');
    }

    public function list()
    {
        return $this->belongsTo(RecipientList::class, 'recipient_list_id');
    }

    public function responses()
    {
        return $this->hasOne(Response::class, 'recipient_id', 'recipient_id');
    }

    public function suppressions()
    {
        return $this->hasMany(SmsSuppression::class, 'phone', 'phone');
    }

    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function scopeWithResponses($query, $campaignId)
    {
        return $query->whereIn('recipients.id',
            result_array_values(
                \DB::select("
                    select distinct(recipient_id) from responses where campaign_id = {$campaignId}
                ")
            )
        );
    }
    public function scopeUnread($query, $campaignId)
    {
        return $query->whereIn('recipients.id',
            result_array_values(
                \DB::select("
                    select recipient_id from responses where id in (
                    select max(id) from responses where campaign_id={$campaignId} and `read` = 0 and type <> 'phone' group by recipient_id
                    ) and incoming = 1 and `read` = 0
                ")
            )
        );
    }

    public function scopeIdle($query, $campaignId)
    {
        return $query->whereIn('recipients.id',
            result_array_values(
                \DB::select("
                    select recipient_id from responses where id in (
                    select max(id) from responses where campaign_id={$campaignId} and `incoming` = 0 group by recipient_id
                    ) and incoming = 0
                ")
            )
        );
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeLabelled($query, $campaignId, $label)
    {
        if ($label == 'none') {
            return $query->where('recipients.campaign_id', $campaignId)->where([
                'interested' => 0, 'not_interested' => 0, 'service' => 0, 'heat' => 0,
                'appointment' => 0, 'car_sold' => 0, 'wrong_number' => 0,
            ]);
        }

        return $query->where('recipients.campaign_id', $campaignId)->where($label, 1);
    }

    public function scopeSearch($query, $searchString)
    {
    }

    public function markInvalidEmail()
    {
        $this->email = '';

        $this->save();
    }
}
