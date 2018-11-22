<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Campaign extends Model
{
    use LogsActivity;

    protected $fillable = [
        'agency_id',
        'dealership_id',
        'name',
        'status',
        'order_id',
        'starts_at',
        'ends_at',
        'adf_crm_export',
        'adf_crm_export_email',
        'lead_alerts',
        'lead_alert_email',
        'client_passthrough',
        'client_passthrough_email',
        'phone_number_id'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'starts_at',
        'ends_at'
    ];

    protected static $logAttributes = ['id', 'agency_id', 'dealership_id', 'name'];

    public function agency()
    {
        return $this->hasOne(Company::class, 'id', 'agency_id');
    }

    public function dealership()
    {
        return $this->hasOne(Company::class, 'id', 'dealership_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function getCompanyCampaigns(int $companyId)
    {
        return
            self::whereNull('deleted_at')
                ->where(function($query) use ($companyId) {
                    $query->where('agency_id', $companyId)
                        ->orWhere('dealership_id', $companyId);
                })->get();
    }

    /**
     * Return the HTML template to show the name of the company in datatables
     * @return string
     */
    public function getNameForTemplate()
    {
        $template = "<h5 style='max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space:nowrap;'>";
        if (isset($this->order_id)) {
            $template .= "<small>Order $this->order_id </small><br>";
        }
        $template .= ucwords($this->name);
        if (!empty($this->starts_at) && !empty($this->ends_at)) {
            $template .= "<br><small>From <code> " . show_date($this->starts_at, 'm/d/Y') . "</code> to <code>" . show_date($this->ends_at, 'm/d/Y') . "</code></small><br>";
        } else {
            $template = "<br><small>No Dates</small><br>";
        }
        $template .= '<span class="badge badge-outline';
        if ($this->status === 'Upcoming') {
            $template .= ' badge-primary';
        } else if ($this->status == 'Completed' || $this->status == 'Expired') {
            $template .= ' badge-default';
        } else {
            $template .= ' badge-success';
        }
        $template .= '">' . $this->status . '</span>';
        $template .= '</h5>';
        $template .= '<div class="campaign-links">';
        $template .= '<a class="btn btn-pure btn-primary btn-round campaign-view" href="' . route('campaign.view', ['campaign' => $this->id]) . '"><i class="fa fa-search"></i></a>';
        $template .= '<a class="btn btn-pure btn-primary btn-round campaign-drops" href="' . route('campaign.drop.index', ['campaign' => $this->id]) . '"><i class="icon icon-lg wi-raindrops" style="font-size: 28px; margin: -5px"></i></a>';
        $template .= '<a class="btn btn-pure btn-primary btn-round campaign-recipients" href="' . route('campaign.recipient.index', ['campaign' => $this->id]) . '"><i class="fa fa-users"></i></a>';
        $template .= '<a class="btn btn-pure btn-primary btn-round campaign-console" href="' . route('campaign.response-console.index', ['campaign' => $this->id]) . '"><i class="fa fa-terminal"></i></a>';
        $template .= '<a class="btn btn-pure btn-warning btn-round campaign-edit" href="' . route('campaign.edit', ['campaign' => $this->id]) . '"><i class="fa fa-pencil"></i></a>';
        $template .= '<button class="btn btn-pure btn-danger btn-round delete-button" data-deleteUrl="' . route('campaign.delete', ['campaign' => $this->id]) . '"><i class="fa fa-trash"></i></button>';
        $template .= '</div>';
        return $template;
    }

    public function phone()
    {
        return $this->hasOne(PhoneNumber::class, 'id', 'phone_number_id');
    }

    public function responses()
    {
        return $this->hasMany(Response::class, 'campaign_id', 'id');
    }

    public function recipients()
    {
        return $this->hasMany(Recipient::class, 'campaign_id', 'id');
    }

    public function recipientLists()
    {
        return $this->hasMany(RecipientList::class, 'campaign_id', 'id');
    }

    public function email_responses()
    {
        return $this->hasMany(Response::class, 'campaign_id', 'id')->where('responses.type', 'email');
    }

    public function phone_responses()
    {
        return $this->hasMany(Response::class, 'campaign_id', 'id')->where('responses.type', 'phone');
    }

    public function text_responses()
    {
        return $this->hasMany(Response::class, 'campaign_id', 'id')->where('responses.type', 'text');
    }

    public function drops()
    {
        return $this->hasMany(Drop::class, 'campaign_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(CampaignSchedule::class, 'campaign_id', 'id');
    }
}
