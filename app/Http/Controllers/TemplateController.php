<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CampaignScheduleTemplate;
use App\Http\Requests\NewTemplateRequest;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function newForm()
    {
        $viewData = [];

        return view('templates.new', $viewData);
    }

    public function create(NewTemplateRequest $request)
    {
        $data = $request->only(['name', 'type', 'email_subject', 'email_text',
            'email_html', 'text_message', 'text_message_image', 'send_vehicle_image']);

        $template = new CampaignScheduleTemplate([
            'name' => $request->name,
            'type' => $request->type,
            'email_subject' => $request->email_subject,
            'email_text' => $request->email_text,
            'email_html' => $request->email_html,
            'text_message' => $request->text_message,
            'text_message_image' => $request->text_message_image,
            'send_vehicle_image' => (int)$request->send_vehicle_image
        ]);

        // dd($template);

        $template->save();

        return redirect()->route('template.index');
    }

    public function editForm(CampaignScheduleTemplate $template)
    {
        $viewData['template'] = $template;

        return view('template.details', $viewData);
    }

    public function update(CampaignScheduleTemplate $template, Request $request)
    {
        $template->fill($request->input('params'));

        $template->save();

        return redirect()->route('template.edit', ['template' => $template->id]);
    }

    public function show(CampaignScheduleTemplate $template)
    {
        dd($template);
    }

    public function showJson(CampaignScheduleTemplate $template)
    {
        return $template->toJson();
    }

    public function index()
    {
        return view('template.index', [
            'companySelected' => Company::find(session('filters.template.index.company')),
            'q' => session('filters.template.index.q')
        ]);
    }

    public function getForUserDisplay(Request $request)
    {
        $templates = CampaignScheduleTemplate::searchByRequest($request)
            ->paginate(15);

        return $templates;
    }

    public function delete(CampaignScheduleTemplate $template)
    {
        $template->delete();
        return redirect()->route('template.index');
    }
}
