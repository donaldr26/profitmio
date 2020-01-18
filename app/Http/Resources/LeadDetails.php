<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Response;

class LeadDetails extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $data['lead'] = [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status_for_humans,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'notes' => $this->notes,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'sent_to_crm' => $this->sent_to_crm,
            'service' => $this->service,
            'vehicle' => implode(' ', [$this->year, $this->make, $this->model]),
            'labels' => $this->labels,
            'checked_in' => $this->checkedIn(),
            'checked_in_at_formatted' => $this->getCheckedInAt(),
        ];

        $data['threads']['mailer'] = EmailResponse::collection($this->responses()->whereType(Response::MAILER_TYPE)->get());
        $data['threads']['email'] = EmailResponse::collection($this->responses()->whereType('email')->get());
        $data['threads']['text'] = EmailResponse::collection($this->responses()->whereType('text')->get());
        $data['threads']['phone'] = EmailResponse::collection($this->responses()->whereType('phone')->get());
        $data['threads']['emailDrop'] = EmailDrop::collection($this->drops()->whereType('email')->orderBy('send_at', 'desc')->take(1)->get());
        $data['threads']['textDrop'] = SmsDrop::collection($this->drops()->whereType('sms')->orderBy('send_at', 'desc')->take(1)->get());

        $data['appointments'] = Appointment::collection($this->appointments);
        $data['responses'] = $this->responses;

        return $data;
    }
}
