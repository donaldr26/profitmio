<?php

namespace App\Http\Controllers;

use App\Events\ServiceDeptLabelAdded;
use App\Events\CampaignCountsUpdated;
use App\Http\Requests\AddRecipientRequest;
use App\Http\Requests\CreateRecipientListRequest;
use App\Http\Resources\Recipient as RecipientResource;
use App\Http\Resources\RecipientList as RecipientListResource;
use App\Jobs\LoadListRecipients;
use App\Jobs\ProcessList;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Recipient;
use App\Models\RecipientList;
use App\Models\Response;
use App\Services\PusherBroadcastingService;
use App\Services\CrmService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;

class RecipientController extends Controller
{
    protected $pages = 15;

    private $crm;
    private $log;

    public function __construct(CrmService $crm, Logger $log)
    {
        $this->crm = $crm;
        $this->log = $log;
    }

    public function forUserDisplay(Campaign $campaign)
    {
        return RecipientListResource::collection(RecipientList::whereCampaignId($campaign->id)
            ->with('recipients')
            ->paginate(15));
    }

    public function searchForDeployment(Campaign $campaign, Request $request)
    {
        $contact = $request->has('contact') ? $request->contact : false;
        $group = $request->has('group') ? $request->group : 'no-group';
        $lists = $request->has("lists") ? $request->input('lists') : '';
        $max = $request->has('max') ? $request->max : false;
        $source = $request->has('data_source') ? (array)$request->input('data_source') : [];
        Log::debug("source = " . json_encode($source));

        $recipients = \DB::table('recipients')
            ->whereNull('deleted_at')
            ->whereNull('last_responded_at')
            ->where('campaign_id', $campaign->id);

        if ($contact == 'all-sms' or $contact == 'no-resp-sms') {
            $recipients->whereRaw("length(phone) > 9");
        }
        if ($contact == 'all-email' or $contact == 'no-resp-email') {
            $recipients->where('email', '<>', '')
                ->where('email_valid', 1);
        }
        if ($contact == 'sms-only') {
            $recipients->where('email', '=', '')->where(function ($q) {
                $q->orWhere('phone', '<>', '+1')
                    ->orWhere('phone', '<>', '');
            })
                ->whereRaw("length(phone) > 9");
        }
        if ($contact == 'email-only') {
            $recipients->where('email', '<>', '')
                ->where(function ($q) {
                    $q->orWhere('phone', '=', '+1')
                        ->orWhere('phone', '=', '');
                })
                ->where('email_valid', 1);
        }
        if ($contact == 'no-resp-email') {
            $recipients->whereNotIn('id',
                \DB::table('responses')->where('campaign_id',
                    $campaign->id)->select('recipient_id')->get()->pluck('recipient_id')->toArray())
                ->where('email_valid', 1);
        }
        if ($contact == 'no-resp-sms') {
            $recipients->whereNotIn('id',
                \DB::table('responses')->where('campaign_id',
                    $campaign->id)->select('recipient_id')->get()->pluck('recipient_id')->toArray())
                ->whereRaw("length(phone) > 9");
        }

        if (is_array($lists) && !empty($lists) && $lists[0] != null) {
            if (!in_array('all', $lists)) {
                $recipients->whereIn('recipient_list_id', (array)$lists);
            }
        }

        if (count($source) == 1) {
            if ($source[0] == 'database') {
                $recipients->whereFromDealerDb(true);
            }
            if ($source[0] == 'conquest') {
                $recipients->whereFromDealerDb(false);
            }
        }

        $total = $recipients->count();
        $groups = [];
        $leftovers = 0;

        if ($max > 0) {
            $leftovers = 0;
            if ($max < $total) {
                $leftovers = $total % $max;
            }

            $groups = [];
            for ($i = 0; $i < floor($total / $max); $i++) {
                $groups[] = ['name' => 'Group' . $i, 'count' => $max];
            }

            if ($leftovers != 0) {
                $groups[] = ['name' => 'Group' . $i, 'count' => $leftovers];
            }

            if ($max > $total) {
                $groups[] = ['name' => 'Group0', 'count' => $total];
            }
        }

        if (empty($groups)) {
            $groups[] = ['name' => "Group0", 'count' => $total];
        }

        return json_encode([
            'total'  => $total,
            'groups' => $groups,
        ]);
    }

    /**
     * @param \App\Models\Campaign $campaign
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(Campaign $campaign)
    {
        $viewData['campaign'] = $campaign->load('recipientLists');

        return view('campaigns.recipient_lists.index', $viewData);
    }

    /**
     * @param \App\Models\Campaign $campaign
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function listDetails(Campaign $campaign, RecipientList $list)
    {
        $list = RecipientList::whereId($list->id)->whereCampaignId($campaign->id)->with('recipients')->get();
        $viewData['campaign'] = $campaign;
        $viewData['list'] = $list;

        return view('campaigns.recipient_lists.show', $viewData);
    }

    public function getRecipientsForUserDisplay(Request $request, Campaign $campaign, RecipientList $list)
    {
        $items = Recipient::searchByRequest($request, $list)
            ->orderBy('id', 'asc')
            ->paginate(15);

        return RecipientResource::collection($items);
    }

    /**
     * @param Request  $request
     * @param Campaign $campaign
     * @param string   $filter
     * @param null     $label
     * @return mixed
     */
    public function getRecipientData(Request $request, Campaign $campaign, $filter = 'all', $label = null)
    {
        if ($filter == 'all') {
            $recipients = Recipient::withResponses($campaign->id);
        }
        if ($filter == 'unread') {
            $recipients = Recipient::unread($campaign->id);
        }
        if ($filter == 'idle') {
            $recipients = Recipient::idle($campaign->id);
        }
        if ($filter == 'archived') {
            $recipients = Recipient::archived($campaign->id);
        }
        if ($filter == 'labelled') {
            $recipients = Recipient::withResponses($campaign->id)
                ->labelled($campaign->id, $label);
        }
        if ($filter == 'email') {
            $recipients = Recipient::withResponses($campaign->id)->whereIn(
                'recipients.id',
                result_array_values(
                    DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='email'")
                )
            );
        }
        if ($filter == 'text') {
            $recipients = Recipient::withResponses($campaign->id)->whereIn(
                'recipients.id',
                result_array_values(
                    DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='text'")
                )
            );
        }
        if ($filter == 'calls') {
            $recipients = Recipient::withResponses($campaign->id)->whereIn(
                'recipients.id',
                result_array_values(
                    DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='phone'")
                )
            );
        }

        if (!isset($recipients)) {
            abort(401);
        }

        if ($request->has('search')) {
            $recipients->where(function ($query) use ($request) {
                $keywords = explode(' ', $request->search);
                foreach ($keywords as $keyword) {
                    $query->orWhere('first_name', 'like', '%' . $keyword . '%')
                        ->orWhere('last_name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%')
                        ->orWhere('phone', 'like', '%' . $keyword . '%')
                        ->orWhere('make', 'like', '%' . $keyword . '%')
                        ->orWhere('model', 'like', '%' . $keyword . '%')
                        ->orWhere('year', 'like', '%' . $keyword . '%');
                }
            });
        }

        $recipients->join('responses as r1', function ($join) {
            $join->on('recipients.id', '=', 'r1.id');
        })
            ->leftJoin('responses as r2', function ($join) {
                $join->on('r1.recipient_id', '=', 'r2.recipient_id')
                    ->on('r1.created_at', '<', 'r2.created_at');
            })
            ->whereNull('r2.created_at')
            ->selectRaw('recipients.*, r1.created_at as last_seen')
            ->orderBy('last_seen', 'desc');

        $recipients = $recipients->paginate($this->pages);

        $recipients->totalCount = Recipient::withResponses($campaign->id)->count();
        $recipients->unread = Recipient::unread($campaign->id)->count();
        $recipients->idle = Recipient::idle($campaign->id)->count();
        $recipients->archived = Recipient::archived()->count();
        $recipients->email = Recipient::withResponses($campaign->id)->whereIn(
            'recipients.id',
            result_array_values(
                DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='email'")
            )
        )->count();
        $recipients->calls = Recipient::withResponses($campaign->id)->whereIn(
            'recipients.id',
            result_array_values(
                DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='phone'")
            )
        )->count();
        $recipients->sms = Recipient::withResponses($campaign->id)->whereIn(
            'recipients.id',
            result_array_values(
                DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='text'")
            )
        )->count();

        $recipients->labelCounts = Recipient::withResponses($campaign->id)
            ->selectRaw("sum(interested) as interested, sum(not_interested) as not_interested,
                sum(appointment) as appointment, sum(service) as service, sum(wrong_number) as wrong_number,
                sum(car_sold) as car_sold, sum(heat) as heat_case, sum(callback) as callback,
                sum(case when (interested = 0 and not_interested = 0 and appointment = 0 and service = 0 and
                wrong_number = 0 and car_sold = 0 and heat = 0) then 1 else 0 end) as not_labelled")
            ->first();

        $viewData['campaign'] = $campaign;
        $viewData['recipients'] = $recipients;
        $viewData['filter'] = $filter;
        $viewData['label'] = $label;
        $viewData['counters'] = [
            'totalCount'  => $recipients->totalCount,
            'unread'      => $recipients->unread,
            'idle'        => $recipients->idle,
            'archived'    => $recipients->archived,
            'email'       => $recipients->email,
            'calls'       => $recipients->calls,
            'sms'         => $recipients->sms,
            'labelCounts' => array_map('intval', $recipients->labelCounts->toArray()),
        ];

        return $viewData;
    }

    /**
     * @param Request  $request
     * @param Campaign $campaign
     * @return mixed
     */
    public function getRecipients(Request $request, Campaign $campaign)
    {
        $filter = $request->input('filter', null);

        $recipients = Recipient::where('recipients.campaign_id', $campaign->id)
            ->select('recipients.*');

        if (!$filter || $filter === 'all') {
            $recipients->withResponses($campaign->id);
        }

        if ($filter === 'unread' || $filter === 'idle') {
            $recipients->$filter($campaign->id);
        }

        if ($filter == 'email') {
            $recipients = Recipient::withResponses($campaign->id)->whereIn(
                'recipients.id',
                result_array_values(
                    DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='email'")
                )
            );
        }

        if ($filter == 'sms') {
            $recipients = Recipient::withResponses($campaign->id)->whereIn(
                'recipients.id',
                result_array_values(
                    DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='text'")
                )
            );
        }

        if ($filter == 'calls') {
            $recipients = Recipient::withResponses($campaign->id)->whereIn(
                'recipients.id',
                result_array_values(
                    DB::select("select recipient_id from responses where campaign_id = {$campaign->id} and type='phone'")
                )
            );
        }

        $labels = ['none', 'interested', 'appointment', 'callback', 'service', 'not_interested', 'wrong_number', 'car_sold', 'heat'];
        if (in_array($filter, $labels)) {
            $recipients = Recipient::withResponses($campaign->id)
                ->labelled($filter, $campaign->id);
        }

        if ($request->filled('search')) {
            $recipients->search($request->input('search'));
        }

        return $recipients->orderBy('last_responded_at', 'DESC')->paginate($request->input('per_page', 30));
    }

    public function showRecipientList(Request $request, Campaign $campaign, RecipientList $list)
    {
        return view('campaigns.recipient_lists.detail')->with([
            'campaign' => $list->campaign,
            'list'     => $list,
        ]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Campaign     $campaign
     *
     * @return string
     */
    public function fromCampaign(Request $request, Campaign $campaign)
    {
        $valid_filters = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'year',
            'make',
            'model',
            'vin',
            'address1',
            'city',
            'state',
            'zip',
        ];
        $filters = [];
        foreach ($request->query as $name => $value) {
            if (!empty($value) && in_array($name, $valid_filters)) {
                $filter = [$name, '=', $value];
                array_push($filters, $filter);
            }
        }

        $recipients = Recipient::where('campaign_id', $campaign->id)
            ->where($filters);

        if ($request->query->has("sortField") && $request->query->get('sortField') != '') {
            $recipients = $recipients->orderBy($request->query->get("sortField"), $request->query->get("sortOrder"));
        }

        $count = $recipients->count();

        if ($request->has('pageIndex') && $request->has('pageSize')) {
            $toSkip = ($request->query('pageIndex') - 1) * $request->query('pageSize');

            $recipients = $recipients->skip($toSkip)->take($request->query('pageSize'));

            return json_encode(['itemsCount' => $count, 'data' => $recipients->get()]);
        }

        return $recipients->get()->toJson();
    }

    /**
     * Get an object containing Recipient counts with partial data
     *
     * @param \App\Models\Campaign $campaign
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getPartialRecipientCounts(Campaign $campaign)
    {
        $missing = \DB::table('recipients')
            ->select(
                DB::raw("sum(trim(coalesce(first_name,'')) = '' and trim(coalesce(last_name,'')) = '') as name"),
                DB::raw("sum(trim(coalesce(email,'')) = '' and trim(replace(coalesce(phone,''), '+1', '')) = '') as emailOrPhone"),
                DB::raw("sum(trim(coalesce(year,'')) = '') as year"),
                DB::raw("sum(trim(coalesce(make,'')) = '') as make"),
                DB::raw("sum(trim(coalesce(model,'')) = '') as model"),
                DB::raw("sum(trim(coalesce(address1,'')) = '') as address1"),
                DB::raw("sum(trim(coalesce(city,'')) = '') as city"),
                DB::raw("sum(trim(coalesce(state,'')) = '') as state"),
                DB::raw("sum(trim(coalesce(zip,'')) = '') as zip"),
                DB::raw("sum(trim(coalesce(vin,'')) = '') as vin"),
                DB::raw("count(*) as total"))
            ->where('campaign_id', $campaign->id)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->orWhere(function ($or) {
                    $or->where(DB::raw("trim(coalesce(first_name,'')) = ''"))
                        ->where(DB::raw("trim(coalesce(last_name,'')) = ''"));
                })
                    ->orWhere(function ($or) {
                        $or->where(DB::raw("trim(coalesce(email,'')) = ''"))
                            ->where(DB::raw("trim(replace(coalesce(phone,''), '+1', '')) = ''"));
                    })
                    ->orWhereRaw("trim(coalesce(year,'')) = ''")
                    ->orWhereRaw("trim(coalesce(make,'')) = ''")
                    ->orWhereRaw("trim(coalesce(model,'')) = ''")
                    ->orWhereRaw("trim(coalesce(address1,'')) = ''")
                    ->orWhereRaw("trim(coalesce(city,'')) = ''")
                    ->orWhereRaw("trim(coalesce(state,'')) = ''")
                    ->orWhereRaw("trim(coalesce(zip,'')) = ''");
            })
            ->get();

        return $missing;
    }

    /**
     * Get all recipients with a specified field missing
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    public function getPartialRecipientsByField(Campaign $campaign, Request $request)
    {
        $valid_fields = ['name', 'email', 'phone', 'year', 'make', 'model', 'vin', 'address1', 'city', 'state', 'zip'];

        if (!$request->query->has('field')) {
            $this->abortBadFields();
        }
        $field = $request->query->get('field');

        if (!in_array($field, $valid_fields)) {
            $this->abortBadFields();
        }

        $recipients = Recipient::where('campaign_id', $campaign->id)
            ->where(function ($query) use ($field) {
                $query->orWhereNull($field);
                $query->orWhere($field, '=', '');
            });

        $count = $recipients->count();

        return json_encode(['itemsCount' => $count, 'data' => $recipients->get()]);
    }

    /**
     * @param Campaign $campaign
     * @param Request  $request
     */
    public function deletePartialRecipientsByField(Campaign $campaign, Request $request)
    {
        $valid_fields = ['name', 'email', 'phone', 'year', 'make', 'model', 'vin', 'address1', 'city', 'state', 'zip'];

        if (!in_array($request->field, $valid_fields)) {
            abort(400, "No valid field was specified");
        }

        $field = $request->field;

        if ($field == 'name') {
            $toBeRemoved = Recipient::where('campaign_id', $campaign->id)
                ->where(function ($query) {
                    $query->orWhere('first_name', '=', '');
                    $query->orWhereNull('first_name');
                })
                ->where(function ($query) {
                    $query->orWhere('last_name', '=', '');
                    $query->orWhereNull('last_name');
                });
        } else {
            $toBeRemoved = Recipient::where('campaign_id', $campaign->id)
                ->where(function ($query) use ($field) {
                    $query->orWhere($field, '=', '');
                    $query->orWhereNull($field);
                });
        }

        $toBeRemoved->delete();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Campaign     $campaign
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request, Campaign $campaign)
    {
        if (!$request->has('recipient_ids')) {
            abort(422, "Invalid Parameters");
        }

        if (Recipient::whereIn('id',
                $request->input('recipient_ids'))->count() != count($request->input('recipient_ids'))) {
            abort(422, "Not all recipients exist, please try again");
        }

        $dropped = collect(DB::select("
        select distinct recipient_id as `dropped`
            from deployment_recipients as dt where dt.deployment_id in (
              select id from campaign_schedules where campaign_id = {$campaign->id}
            )
            and dt.sent_at is not null
        "))->pluck('dropped')->toArray();

        $dangers = array_values(array_intersect($request->input('recipient_ids'), $dropped));

        if (count($dangers) > 0) {
            $errors = new MessageBag(['recipients' => 'Unable to process bulk deletion as some recipients have already been sent media and therefore cannot be deleted']);

            return response()->json(['errors' => $errors], 422);
        }

        Recipient::where('campaign_id', $campaign->id)
            ->whereIn('id', (array)$request->input('recipient_ids'))
            ->whereNotIn('id', $dropped)
            ->delete();

        return response()->json(['message' => 'Resources Deleted']);
    }

    /**
     * @param \App\Http\Requests\AddRecipientRequest $request
     * @param \App\Models\Campaign                   $campaign
     */
    public function update(AddRecipientRequest $request, Campaign $campaign)
    {
        $recipient = Recipient::findOrFail($request->recipient_id);

        $recipient->update([
            'first_name' => $request->get('first_name'),
            'last_name'  => $request->get('last_name'),
            'email'      => $request->get('email'),
            'phone'      => $request->get('phone'),
            'address1'   => $request->get('address1'),
            'city'       => $request->get('city'),
            'state'      => $request->get('state'),
            'zip'        => $request->get('zip'),
            'year'       => $request->get('year'),
            'make'       => $request->get('make'),
            'model'      => $request->get('model'),
            'vin'        => $request->get('vin'),
        ]);
    }

    /**
     * @param \App\Http\Requests\AddRecipientRequest $request
     * @param \App\Models\Campaign                   $campaign
     */
    public function add(AddRecipientRequest $request, Campaign $campaign)
    {
        $group_id = $this->getMaxRecipientGroup($campaign->id) + 1;

        $recipient = new Recipient([
            'first_name'  => $request->get('first_name'),
            'last_name'   => $request->get('last_name'),
            'email'       => $request->get('email'),
            'phone'       => $request->get('phone'),
            'address1'    => $request->get('address1'),
            'city'        => $request->get('city'),
            'state'       => $request->get('state'),
            'zip'         => $request->get('zip'),
            'year'        => $request->get('year'),
            'make'        => $request->get('make'),
            'model'       => $request->get('model'),
            'campaign_id' => $campaign->id,
            'subgroup'    => $group_id,
        ]);

        $recipient->save();
    }

    /**
     * @param Campaign $campaign
     */
    public function downloadRecipientList(Campaign $campaign, RecipientList $list)
    {
        $recipients = $list->recipients;
        $columns = \DB::getSchemaBuilder()->getColumnListing('recipients');

        $filename = 'List_' . $list->id . '_recipients.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Type: application/force-download');
        header('Content-Disposition: attachment; filename=' . $filename . '');

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        fputcsv($output, $columns);
        foreach ($recipients as $recipient) {
            fputcsv($output, $recipient);
        }
        fclose($output);

        return;
    }

    /**
     * @param Campaign $campaign
     */
    public function download(Campaign $campaign)
    {
        $recipients = Recipient::where('campaign_id', $campaign->id)->get()->toArray();
        $columns = \DB::getSchemaBuilder()->getColumnListing('recipients');

        $filename = 'Campaign_' . $campaign->id . '_recipients.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Type: application/force-download');
        header('Content-Disposition: attachment; filename=' . $filename . '');

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        fputcsv($output, $columns);
        foreach ($recipients as $recipient) {
            fputcsv($output, $recipient);
        }
        fclose($output);

        return;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Campaign     $campaign
     * @return \Illuminate\Http\JsonResponse
     * @throws \Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException
     * @throws \Pion\Laravel\ChunkUpload\Exceptions\UploadFailedException
     */
    public function uploadFile(Request $request, Campaign $campaign)
    {
        // create the file receiver
        $receiver = new FileReceiver("file", $request, HandlerFactory::classFromRequest($request));
        // check if the upload is success, throw exception or return response you need
        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }
        // receive the file
        $save = $receiver->receive();
        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            // save the file and return any response you need, current example uses `move` function. If you are
            // not using move, you need to manually delete the file by unlink($save->getFile()->getPathname())
            return $this->saveFile($save->getFile());
        }
        // we are in chunk mode, lets send the current progress
        /** @var AbstractHandler $handler */
        $handler = $save->handler();

        return response()->json([
            "done"   => $handler->getPercentageDone(),
            'status' => true,
        ]);
    }

    /**
     * Saves the file
     *
     * @param UploadedFile $file
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function saveFile(UploadedFile $file)
    {
        $fileName = $this->createFilename($file);
        // Group files by mime type
        $mime = str_replace('/', '-', $file->getMimeType());
        // Group files by the date (week
        $dateFolder = date("Y-m-W");
        // Build the file path
        // $filePath = "upload/{$mime}/{$dateFolder}/";
        $filePath = "temporary_uploads/";
        $finalPath = storage_path("app/" . $filePath);

        // move the file name
        $file->move($finalPath, $fileName);

        $f = fopen(storage_path('app/' . $filePath . $fileName), 'r');
        $i = 1;
        $headers = fgetcsv($f);
        $rows = [];
        while (($row = fgetcsv($f)) !== false && $i <= 5) {
            $rows[] = $row;
            $i++;
        }
        fclose($f);

        return response()->json([
            'path'      => $filePath,
            'name'      => $fileName,
            'mime_type' => $mime,
            'headers'   => $headers,
            'rows'      => $rows,
        ]);
    }

    /**
     * Create unique filename for uploaded file
     * @param UploadedFile $file
     * @return string
     */
    protected function createFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = str_replace("." . $extension, "", $file->getClientOriginalName()); // Filename without extension
        // Add timestamp hash to name of the file
        $filename .= "_" . md5(time()) . "." . $extension;

        return $filename;
    }

    /**
     * @param Request       $request
     * @param Campaign      $campaign
     * @param RecipientList $list
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteRecipientList(Request $request, Campaign $campaign, RecipientList $list)
    {
        if ($list->campaign_id != $campaign->id) {
            abort(403, 'Unauthorized');
        }

        $campaign_id = $campaign->id;

        $donotdelete = collect(DB::select("select recipient_id from deployment_recipients
            where deployment_id in (select id from campaign_schedules where campaign_id = $campaign_id)
            and deployment_recipients.sent_at is not null"))
            ->pluck('recipient_id')
            ->toArray();

        $list->recipients()->whereNotIn('id', $donotdelete)->delete();

        $list = $list->fresh();

        if ($list->recipients()->count() == 0) {
            try {
                $list->delete();
            } catch (\Exception $e) {
                $errors = new MessageBag(["recipients" => "Unable to delete selected recipient list!"]);

                return response()->json(['errors' => $errors], 422);
            }
        }

        return response()->json(['message' => 'Resource deleted.']);
    }

    /**
     * @param Request  $request
     * @param Campaign $campaign
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createRecipientList(CreateRecipientListRequest $request, Campaign $campaign)
    {
        try {
            /**
             * Validation Time
             */
            $validator = Validator::make($request->all(), [
                'uploaded_file_name'     => 'required',
                'uploaded_file_headers'  => 'required',
                'uploaded_file_fieldmap' => 'required',
                'pm_list_name'           => 'required',
                'pm_list_type'           => 'required|in:all_conquest,all_database,use_recipient_field',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }
            Log::debug('New List passed initial validation');

            $fieldmap = $request->input('uploaded_file_fieldmap');
            $validator = Validator::make($fieldmap, [
                'first_name' => 'required',
                'last_name'  => 'required',
                'email'      => 'required_if:phone,',
                'phone'      => 'required_if:email,',
            ], [
                'first_name' => 'The "first_name" field must be mapped',
                'last_name'  => 'The "last_name" field must be mapped',
                'email'      => 'The "email" field must be mapped if the "phone" field is not',
                'phone'      => 'The "phones" field must be mapped if the "email" field is not',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Validate for the conquest vs database setting
            if ($request->input('pm_list_type') == 'use_recipient_field') {
                $validator = Validator::make($request->all(), [
                    'is_database' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }
            }
            Log::debug("New List passed list_type validation");
            if ($request->input('pm_list_type') == 'all_database') {
                $type = 'database';
            } elseif ($request->input('pm_list_type') == 'all_conquest') {
                $type = 'conquest';
            } else {
                $type = 'mixed';
            }

            // Add the List object
            $list = RecipientList::create([
                'campaign_id' => $campaign->id,
                'uploaded_by' => auth()->user()->id,
                'fieldmap'    => $fieldmap,
                'type'        => $type,
                'name'        => $request->input('pm_list_name'),
            ]);

            $filepath = Storage::path('temporary_uploads/' . $request->input('uploaded_file_name'));
            $list->addMedia($filepath)->toMediaCollection('recipient-lists');

            Log::debug("New List created with id {$list->id}");
            Log::debug("Ready to create new recipients from the file (this should be pushed off to a job)");

            LoadListRecipients::dispatch($list)->onQueue('load-lists');
        } catch (\Exception $e) {
            $errors = new MessageBag(['file' => 'Unable to load file']);
            Log::error("Unable to load file for campaign {$campaign->id}. " . $e->getMessage());

            return response()->json(['errors' => $errors], 422);
        }

        return response()->json(['message' => 'Recipients Created.']);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Campaign     $campaign
     *
     * @return string
     */
    public function bulkUpload(Request $request, Campaign $campaign)
    {
        $campaign_id = $campaign->id;
        $data = $request->request->get('recipients');
        $upload_id = substr(sha1($request->upload_identifier), 0, 63);

        $list = RecipientList::where('upload_identifier', $upload_id)->first();
        if (!$list) {
            $list = new RecipientList([
                'campaign_id'       => $campaign_id,
                'uploaded_by'       => \Auth::user()->id,
                'name'              => $request->name ?: 'Default',
                'upload_identifier' => $upload_id,
            ]);
            $list->save();
        }

        if (!empty($data)) {
            $recipients = json_decode($data, true);
            foreach ($recipients as &$recipient) {
                unset($recipient['id']); // remove the ID that is provided by the papaparse
                $recipient['campaign_id'] = $campaign_id;
                $recipient['carrier'] = $upload_id;
                $recipient['recipient_list_id'] = $list->id;
                $recipient['created_at'] = \Carbon\Carbon::now('UTC');
                $recipient['phone'] = preg_replace("/[^0-9]/", "", $recipient['phone']);
            }
            try {
                Recipient::insert($recipients);

                return json_encode(['code' => 200, 'message' => 'success']);
            } catch (\Exception $e) {
                // echo $e->getMessage();
                // Log::create(['message'=>$e->getMessage(), 'code'=>$e->getCode(), 'file'=>__FILE__, 'line_number'=>__LINE__]);
                return json_encode(['code' => 500, 'message' => $e->getMessage()]);
            }
        } else {
            return json_encode(['code' => '402', 'message' => 'No data found']);
        }
    }

    /**
     * @param Request  $request
     * @param Campaign $campaign
     */
    public function finishUpload(Request $request, Campaign $campaign)
    {
        $upload_id = substr(sha1($request->upload_identifier), 0, 63);

        $list = \App\Models\RecipientList::where('upload_identifier', $upload_id)->first();

        $group_id = $this->getMaxRecipientGroup($campaign->id) + 1;

        \DB::table('recipients')->where('carrier', $upload_id)
            ->update(['subgroup' => $group_id, 'carrier' => '']);

        if ($list) {
            ProcessList::dispatch($list)->onQueue('lists');
        }
    }

    /**
     * @param Campaign $campaign
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteAll(Campaign $campaign)
    {
        Recipient::where('campaign_id', $campaign->id)->delete();

        return redirect()->route('campaigns.recipients.index', ['campaign' => $campaign->id]);
    }

    private function abortBadFields()
    {
        abort('400', 'Cannot show partial recipients by field without a field specified');
    }

    /**
     * Get the max recipient group number for a campaign
     *
     * @param int $campaign_id
     * @return int
     */
    private function getMaxRecipientGroup($campaign_id)
    {
        $recipients = \DB::table('recipients')->selectRaw("max(subgroup) as max_group")
            ->where('campaign_id', $campaign_id)
            ->groupBy('campaign_id')
            ->first();

        $max_group = 0;
        if ($recipients) {
            $max_group = $recipients->max_group;
        }

        return $max_group;
    }

    /**
     * Get the number of recipients in a drop contacted or not
     *
     * @param \App\Models\RecipientList $list
     * @return mixed
     */
    public function recipientListDeleteStats(Request $request, Campaign $campaign, RecipientList $list)
    {
        $total = $list->recipients()->count();
        $recipient = DB::select("
        select count( distinct recipient_id) as `count`
            from deployment_recipients where deployment_id in (
              select id from campaign_schedules where campaign_id = {$list->campaign_id}
            ) and recipient_id in (
              select recipient_id from recipients where campaign_id = {$list->campaign_id} and recipient_list_id = {$list->id}
            )
        ");
        $inDrops = count($recipient) ? $recipient[0]->count : 0;

        $recipient = DB::select("
        select count( distinct recipient_id) as `count`
            from deployment_recipients as dt where dt.deployment_id in (
              select id from campaign_schedules where campaign_id = {$list->campaign_id}
            ) and dt.recipient_id in (
              select recipient_id from recipients where campaign_id = {$list->campaign_id} and recipient_list_id = {$list->id}
            )
            and dt.sent_at is not null
        ");
        $dropped = count($recipient) ? $recipient[0]->count : 0;

        $deletable = $total - $dropped;

        return [
            'total'      => $total,
            'inDrops'    => $inDrops,
            'dropped'    => $dropped,
            'deletable'  => $deletable,
            'delete_url' => route('campaigns.recipient-lists.delete', [$campaign->id, $list->id]),
        ];
    }

    public function sendToCrm(Recipient $recipient)
    {
    }
}
