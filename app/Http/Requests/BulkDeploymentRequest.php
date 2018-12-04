<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeploymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type' => 'required',
            'email_subject' => 'required_without:text_message',
            'email_text' => 'required_with:email_subject',
            'email_html' => 'required_with:email_subject',
            'text_message' => 'required_without:email_subject',
            'text_vehicle_image' => 'nullable',
            'send_vehicle_image' => 'nullable',
            'Group0_date' => 'required',
            'Group0_time' => 'required'
        ];
    }
}
