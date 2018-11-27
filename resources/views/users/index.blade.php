@extends('layouts.remark')

@section('header')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.15/css/dataTables.bootstrap.min.css">
@endsection

@section('content')
    <div class="page">
        <div class="page-header container-fluid">
            <div class="row-fluid">
                <div class="col-xxl-8 offset-xxl-2 col-md-12">
                    <h3 class="page-title text-default">
                        Users
                    </h3>
                    <div class="page-header-actions">
                        <a href="{{ route('user.create') }}"
                           class="btn btn-sm btn-success waves-effect">
                            <i class="icon md-plus" aria-hidden="true"></i>
                            New User
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-content container-fluid">
            <div class="row-fluid" data-plugin="matchHeight" data-by-row="true">
                <div class="col-xxl-8 offset-xxl-2 col-md-12">
                    <div class="panel panel-info">
                        <div class="panel-body">
                            @if (auth()->user()->isAdmin())
                                <form method="get" action="{{ route('user.index') }}">
                                    <div class="form-group floating">
                                        <label class="floating-label" for="company">Filter By Company</label>
                                        <select class="form-control" name="company" required onchange="this.form.submit()">
                                            <option value="" {{ !$selectedCompanyId ? 'selected' : '' }}>All Companies</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}" {{ $company->id == $selectedCompanyId ? 'selected' : '' }}>{{ $company->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </form>
                            @endif
                            <div class="table-responsive">
                                <table id="users" class="table table-striped table-hover datatable">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        @if (!auth()->user()->isAdmin() || $selectedCompanyId)
                                            <th>Type</th>
                                        @else
                                            <th>Company / Type</th>
                                        @endif
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone Number</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($users as $user)
                                        <tr>
                                            <td class="id-row v-center"><strong>{{ $user->id }}</strong></td>
                                            <td class="v-center">{{ $user->first_name }}</td>
                                            <td class="v-center">{{ $user->last_name }}</td>
                                            @if ($user->isAdmin())
                                                <td class="text-capitalize v-center">@role('site_admin')</td>
                                            @elseif (!auth()->user()->isAdmin() || $selectedCompanyId)
                                                <td class="text-capitalize v-center">@role($user->pivot->role)</td>
                                            @else
                                                <td class="text-capitalize v-center">
                                                    <ul>
                                                        @foreach ($user->companies as $company)
                                                        <li>{{ $company->name }} @role($user->getRole($company))</li>
                                                        @endforeach
                                                    </ul>
                                                </td>
                                            @endif
                                            <td class="v-center">{{ $user->username }}</td>
                                            <td class="v-center">{{ $user->email }}</td>
                                            <td class="v-center">{{ $user->phone_number }}</td>
                                            <td>
                                                @if (auth()->user()->isAdmin() || !$user->isAdmin())
                                                <a class="btn btn-sm btn-warning btn-round mb-5"
                                                   href="{{ route('user.edit', ['user' => $user->id]) }}">
                                                    Edit
                                                </a>
                                                @endif
                                                @if (auth()->user()->isAdmin() && !$user->isAdmin())
                                                    <a class="btn btn-sm btn-success btn-round mb-5"
                                                       href="{{ route('admin.impersonate', ['user' => $user->id]) }}">
                                                        Impersonate
                                                    </a>
                                                @endif
                                                @if(auth()->user()->isAdmin() && !$user->isAdmin() && $user->hasPendingInvitations())
                                                    <a class="btn btn-link mb-5"
                                                       href="{{ route('user.edit', ['user' => $user->id]) }}">
                                                        Has Pending Invitations
                                                    </a>
                                                @endif
                                                @if (!auth()->user()->isAdmin() && !$user->isCompanyProfileReady($company))
                                                    <a class="btn btn-sm btn-primary btn-round mb-5"
                                                       href="{{ route('admin.resend-invitation', ['user' => $user->id, 'company' => $company->id ]) }}">
                                                        Re-send Invitation
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scriptTags')
    <script src="{{ secure_url('js/Plugin/material.js') }}"></script>
    <script src="{{ secure_url('js/Plugin/sweetalert.min.js') }}"></script>

    <script src="https://cdn.datatables.net/1.10.15/js/jquery.dataTables.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function () {
            $(".datatable").DataTable({"order": [[0, "desc"]]});
        });
    </script>
@endsection

@section('scripts')
@endsection

