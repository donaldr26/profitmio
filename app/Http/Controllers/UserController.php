<?php

namespace App\Http\Controllers;

use App\Classes\CompanyUserActivityLog;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateCompanyDataRequest;
use App\Models\Company;
use App\Mail\InviteUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    private $company;

    /** @var CompanyUserActivityLog  */
    private $companyUserActivityLog;

    private $user;

    public function __construct(Company $company, CompanyUserActivityLog $companyUserActivityLog, User $user)
    {
        $this->company = $company;
        $this->companyUserActivityLog = $companyUserActivityLog;
        $this->user = $user;
    }

    public function activate(User $user, Request $request)
    {
        $user->activate($request->input('company'));

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $users = auth()->user()->getListOfUsers($request->input('company'));
        return view('users.index', [
            'users' => $users,
            'company' => !auth()->user()->isAdmin() ? $this->company->findOrFail(get_active_company()) : null,
            'companies' => $this->company->orderBy('name', 'desc')->get(),
            'selectedCompanyId' => $request->has('company') ? $request->input('company') : null
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'companies' => $this->company->all()
        ]);
    }

    public function deactivate(User $user, Request $request)
    {
        $user->deactivate($request->input('company'));

        return redirect()->back();
    }

    /**
     * @param StoreUserRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->user
            ->where('email', $request->input('email'))
            ->first();

        if (($request->input('role') == 'site_admin' && !is_null($user)) || ($user && $user->isAdmin())) {
            return redirect()->back()->withErrors('The email has already been taken.');
        }
        if (!$user) {
            $user = new $this->user();
            $user->is_admin = $request->input('role') == 'site_admin' ? true : false;
            $user->password = '';
            $user->username = $request->input('email');
            $user->first_name = $request->input('first_name');
            $user->last_name = $request->input('last_name');
            $user->email = $request->input('email');
            $user->save();
        }
        if ($user->isAdmin()) {
            $processRegistration = URL::temporarySignedRoute(
                'registration.complete.show', Carbon::now()->addMinutes(60), [
                    'id' => $user->getKey()
                ]
            );
        } else {
            // Attach to company if user is not admin
            if (auth()->user()->isAdmin()) {
                $company = $this->company->findOrFail($request->input('company'));
            } else {
                $company = $this->company->findOrFail(get_active_company());
            }

            $user->companies()->attach($company->id, [
                'role' => $request->input('role')
            ]);

            $processRegistration = URL::temporarySignedRoute(
                'registration.complete.show', Carbon::now()->addMinutes(60), [
                    'id' => $user->getKey(),
                    'company' => $company->id
                ]
            );

            $this->companyUserActivityLog->attach($user, $company->id, $request->input('role'));
        }

        Mail::to($user)->send(new InviteUser($user, $processRegistration));

        return redirect()->route('user.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function createForm()
    {
        $viewData = [
            'companies' => Company::all()
        ];
        return view('users.new', $viewData);
    }

    /**
     * Return the view to select a company
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function selectActiveCompany(Request $request)
    {
        return view('users.select-company');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        $viewData['user'] = $user;
        $viewData['campaigns'] = collect([]);

        if ($user->isAgencyUser()) {
            $viewData['campaigns'] = $user->agencyCampaigns()->get();
        }
        if ($user->access == 'Client') {
            $viewData['campaigns'] = $user->campaigns()->get();
        }

        return view('users.details', $viewData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'companies' => $user->companies()->orderBy('name', 'asc')->get()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $user->update($request->except(['password']));

        return response()->redirectToRoute('user.edit', ['user' => $user->id]);
    }

    public function updateForm(User $user)
    {
        $viewData['user'] = $user;
        $viewData['companies'] = Company::all();

        return view('users.edit', $viewData);
    }

    public function updateCompanyData(User $user, UpdateCompanyDataRequest $request)
    {
        $invitation = $user->invitations()->where('company_id', $request->input('company'))->firstOrFail();

        $config = $invitation->config;
        $config['timezone'] = $request->input('timezone');

        $invitation->config = $config;
        $invitation->role = $request->input('role');
        $invitation->save();

        return response()->json('Resource updated.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }
}
