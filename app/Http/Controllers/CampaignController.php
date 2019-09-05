<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\EmailLog;
use App\Models\Recipient;
use App\Models\PhoneNumber;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use App\Http\Requests\NewCampaignRequest;
use Carbon\Carbon;

class CampaignController extends Controller
{
    private $emailLog;

    private $campaign;

    private $company;

    private $recipient;

    public function __construct(Campaign $campaign, Company $company, EmailLog $emailLog, Recipient $recipient)
    {
        $this->campaign = $campaign;
        $this->company = $company;
        $this->emailLog = $emailLog;
        $this->recipient = $recipient;
    }

    public function index(Request $request)
    {
        return view('campaigns.index', []);
    }


    /**
     * Load Campaign Console Page
     * 
     * @param Request  $request
     * @param Campaign $campaign
     * 
     * @return \Illuminate\View\View
     */
    public function console(Request $request, Campaign $campaign, $filter = null)
    {
        $counters = [];
        $counters['total'] = Recipient::withResponses($campaign->id)->count();
        $counters['unread'] = Recipient::unread($campaign->id)->count();
        $counters['idle'] = Recipient::idle($campaign->id)->count();
        $counters['calls'] = Recipient::withResponses($campaign->id)->whereIn(
            'recipients.id',
            result_array_values(
                DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='phone'")
            )
        )->count();
        $counters['email'] = Recipient::withResponses($campaign->id)->whereIn(
            'recipients.id',
            result_array_values(
                DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='email'")
            )
        )->count();
        $counters['sms'] = Recipient::withResponses($campaign->id)->whereIn(
            'recipients.id',
            result_array_values(
                DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='text'")
            )
        )->count();

        $labels = ['none', 'interested', 'appointment', 'callback', 'service', 'not_interested', 'wrong_number', 'car_sold', 'heat'];
        foreach ($labels as $label) {
            $counters[$label] = Recipient::withResponses($campaign->id)
                ->labelled($label, $campaign->id)
                ->count();
        }

        $data = [
            'counters' => $counters,
            'campaign' => $campaign
        ];

        if ($filter) {
            $data['filterApplied'] = $filter;
        }
        return view('campaigns.console', $data);
    }

    /**
     * Return all campaigns for user display
     * @param Request $request
     * @return mixed
     */
    public function getForUserDisplay(Request $request)
    {
        $campaignQuery = Campaign::searchByRequest($request);
        $campaigns = $campaignQuery
            ->orderBy('status', 'asc')
            ->orderBy('campaigns.id', 'desc')
            ->paginate(15);

        return $campaigns;
    }

    public function getList(Request $request)
    {
        $campaigns = $this->campaign->with(['client', 'mailers'])
            ->selectRaw("
                (select count(distinct(recipient_id)) from recipients where campaign_id = campaigns.id) as recipientCount),
                (select count(distinct(recipient_id)) from responses where campaign_id = campaigns.id and type='phone' and recording_sid is not null) as phoneCount,
                (select count(distinct(recipient_id)) from responses where campaign_id = campaigns.id and type='email') as emailCount,
                (select count(distinct(recipient_id)) from responses where campaign_id = campaigns.id and type='text') as textCount,
                users.id as client_id
            ")
            ->get();

        return $campaigns->toJson();
    }

    /**
     * Show a specific campaign
     *
     * @param \App\Models\Campaign $campaign
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(Campaign $campaign)
    {
        $viewData = [];

        $viewData['campaign'] = $campaign;

        $emailStats = $campaign->getEmailLogsStats();
        $responseStats = $campaign->getRecipientStats();

        $viewData['emailCount'] = $emailStats->count();
        $viewData['emailStats'] = $emailStats->first();
        $viewData['responseCount'] = $responseStats->count();
        $viewData['responseStats'] = $responseStats->first();

        return view('campaigns.dashboard', $viewData);
    }


    public function showStats(Campaign $campaign, Request $request)
    {
        return view('campaigns.stats', [
            'campaign' => $campaign
        ]);
    }


    public function details(Campaign $campaign)
    {
        $campaign->with('phones');
        return view('campaigns.details', [
            'campaign' => $campaign
        ]);
    }

    public function create()
    {
        $dealerships = Company::getDealerships();
        $agencies = Company::getAgencies();
        $viewData = [
            'dealerships' => $dealerships,
            'agencies' => $agencies,
        ];

        return view('campaigns.create', $viewData);
    }

    public function store(NewCampaignRequest $request)
    {
        $expires_at = null;
        $starts_at = (new Carbon($request->start, \Auth::user()->timezone))->timezone('UTC')->toDateTimeString();
        $ends_at = (new Carbon($request->end, \Auth::user()->timezone))->timezone('UTC')->toDateTimeString();

        if (! empty($request->input('expires'))) {
            $expires_at = (new Carbon($request->expires, \Auth::user()->timezone))->timezone('UTC')->toDateTimeString();
        } else {
            $expires_at = (new Carbon($request->end, \Auth::user()->timezone))->timezone('UTC')->addWeeks(2);
        }

        $status = $request->status;
        if ($expires_at <= \Carbon\Carbon::now('UTC')) {
            $status = 'Expired';
        }
        $campaign = Campaign::create([
            'name' => $request->input('name'),
            'status' => $status,
            'order_id' => $request->input('order'),
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'agency_id' => $request->input('agency'),
            'dealership_id' => $request->input('dealership'),
            'adf_crm_export' => (bool) $request->input('adf_crm_export'),
            'adf_crm_export_email' => $request->input('adf_crm_export_email', []),
            'client_passthrough' => (bool) $request->input('client_passthrough'),
            'client_passthrough_email' => $request->input('client_passthrough_email', []),
            'lead_alerts' => (bool) $request->input('lead_alerts'),
            'lead_alert_email' => $request->input('lead_alert_emails', []),
            'service_dept' => (bool) $request->input('service_dept'),
            'service_dept_email' => $request->input('service_dept_email', []),
            'sms_on_callback' => (bool) $request->input('service_dept'),
            'sms_on_callback_number' => $request->input('sms_on_callback_number', []),
        ]);

        if (! $campaign->expires_at) {
            $campaign->update(['expires_at' => $campaign->ends_at->addMonth()]);
        }

        if ($request->has('phone_number_ids')) {
            foreach ((array)$request->input('phone_number_ids') as $phone_number_id) {
                PhoneNumber::find($phone_number_id)->update(['campaign_id' => $campaign->id]);
            }
        }

        return response()->json(['message' => 'Resource created']);
    }


    public function edit(Campaign $campaign)
    {
        $dealerships = Company::getDealerships();
        $agencies = Company::getAgencies();
        $viewData = [
            'campaign' => $campaign,
            'dealerships' => $dealerships,
            'agencies' => $agencies,
        ];

        return view('campaigns.edit', $viewData);
    }

    public function update(Campaign $campaign, NewCampaignRequest $request)
    {
        if ($request->filled('phone_number_id') || $request->filled('forward')) {
            $phone = \App\Models\PhoneNumber::findOrFail($request->phone_number_id);
            $phone->fill(['forward' => $request->forward]);
            $phone->save();
        }

        $expires_at = null;
        $starts_at = (new Carbon($request->start, \Auth::user()->timezone))->timezone('UTC')->toDateTimeString();
        $ends_at = (new Carbon($request->end, \Auth::user()->timezone))->timezone('UTC')->toDateTimeString();

        if (! empty($request->input('expires'))) {
            $expires_at = (new Carbon($request->expires, \Auth::user()->timezone))->timezone('UTC')->toDateTimeString();
        } else {
            $expires_at = (new Carbon($request->end, \Auth::user()->timezone))->timezone('UTC')->addWeeks(2);
        }

        $status = $request->status;
        if (! $expires_at || ($expires_at && $expires_at <= \Carbon\Carbon::now('UTC'))) {
            $status = 'Expired';
        }

        $campaign->fill([
            'name' => $request->input('name'),
            'status' => $status,
            'order_id' => $request->input('order'),
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'expires_at' => $expires_at,
            'agency_id' => $request->input('agency'),
            'dealership_id' => $request->input('dealership'),
            'adf_crm_export' => (bool) $request->input('adf_crm_export'),
            'adf_crm_export_email' => $request->input('adf_crm_export_email', []),
            'lead_alerts' => (bool) $request->input('lead_alerts'),
            'lead_alert_email' => $request->input('lead_alert_emails', []),
            'service_dept' => (bool) $request->input('service_dept'),
            'service_dept_email' => $request->input('service_dept_email', []),
            'sms_on_callback' => (bool) $request->input('sms_on_callback'),
            'sms_on_callback_number' => $request->input('sms_on_callback_number', []),
            'client_passthrough' => (bool) $request->input('client_passthrough'),
            'client_passthrough_email' => $request->input('client_passthrough_email', []),
        ]);

        $campaign->save();

        return response()->json(['message' => 'Resource updated.']);
    }

    public function delete(Campaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('campaigns.index');
    }

    public function toggleCampaignUserAccess(Campaign $campaign, User $user)
    {
        $campaign->users()->toggle($user->id);

        return response()->json(['message' => 'Resource updated.']);
    }
}
