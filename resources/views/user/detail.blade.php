@extends('layouts.base', [
    'hasSidebar' => true
])

@section('head-styles')
    <link href="{{ asset('css/user-detail.css') }}" rel="stylesheet">
@endsection

@section('body-script')
    <script>
        window.searchCampaignFormUrl = "{{ route('campaign.for-user-display') }}";
        window.searchCompaniesFormUrl = "{{ route('company.for-user-display') }}";
        window.getCompanyUrl = "{{ route('company.for-dropdown') }}";
        window.campaignCompanySelected = @json($campaignCompanySelected);
        window.updateUserUrl = "{{ route('user.update', ['user' => ':userId']) }}";
        window.timezones = @json($timezones);
        window.updateCompanyDataUrl = "{{ route('user.update-company-data', ['user' => ':userId']) }}";
        window.user = @json($user);
        window.deleteUserUrl = "{{ route('user.delete', ['user' => $user->id]) }}";
        window.updateUserPhotoUrl = "{{ route('user.update-avatar', ['user' => $user->id]) }}";
        window.campaignQ = @json($campaignQ);
        @if (auth()->user()->isAdmin())
            window.userRole = 'site_admin';
        @else
            window.userRole = @json(auth()->user()->getRole(App\Models\Company::findOrFail(get_active_company())));
        @endif
        window.userIndexUrl = "{{ route('user.index') }}";
    </script>
    <script src="{{ asset('js/user-detail.js') }}"></script>
@endsection

@section('sidebar-toggle-content')
    <i class="fas fa-chevron-circle-left mr-2"></i>User Details
@endsection

@section('sidebar-content')
    <div class="avatar">
        <div class="avatar--image" :style="{backgroundImage: 'url(\'' + user.image_url + '\')'}" v-if="showAvatarImage">
            <button class="avatar--edit" v-if="enableInputs" @click="showAvatarImage = false">
                <i class="fas fa-pencil-alt"></i>
            </button>
        </div>
        <vue-dropzone id="profile-image" :options="dropzoneOptions" :useCustomSlot="true" @vdropzone-success="profileImageUploaded" @vdropzone-error="profileImageError" v-if="!showAvatarImage">
            <div class="dropzone-upload-profile-image">
                <h3 class="dropzone-title">Drag and drop to upload content!</h3>
                <div class="dropzone-subtitle">...or click to select a file from your computer</div>
            </div>
        </vue-dropzone>
    </div>
    <button class="btn pm-btn pm-btn-blue edit-user" v-if="!enableInputs && (loggedUserRole === 'site_admin' || (loggedUserRole === 'admin' && user.role !== 'site_admin'))" @click="enableInputs = !enableInputs">
        <i class="fas fa-pencil-alt"></i>
    </button>
    <form class="clearfix form" method="post" action="{{ route('user.update', ['user' => $user->id]) }}" @submit.prevent="saveUser">
        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" class="form-control empty" name="first_name" placeholder="First Name" v-model="editUserForm.first_name" required v-if="enableInputs">
            <p class="form-control panel-data" v-if="!enableInputs">@{{ user.first_name }}</p>
        </div>
        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" class="form-control" name="last_name" placeholder="Last Name" v-model="editUserForm.last_name" required v-if="enableInputs">
            <p class="form-control panel-data" v-if="!enableInputs">@{{ user.last_name }}</p>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="text" class="form-control" name="email" placeholder="Email" v-model="editUserForm.email" required v-if="enableInputs">
            <p class="form-control panel-data" v-if="!enableInputs">@{{ user.email }}</p>
        </div>
        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="text" class="form-control" name="phone_number" placeholder="Phone Number" v-model="editUserForm.phone_number" v-if="enableInputs">
            <p class="form-control panel-data" v-if="!enableInputs">@{{ user.phone_number }}</p>
        </div>
        <button class="btn pm-btn pm-btn-purple float-left mt-4" type="submit" :disabled="loading" v-if="enableInputs">
            <span v-if="!loading"><i class="fas fa-save mr-2"></i>Save</span>
            <div class="loader-spinner" v-if="loading">
                <spinner-icon></spinner-icon>
            </div>
        </button>
        {{--<button class="btn pm-btn pm-btn-blue float-right mt-4" type="button">Change Password</button>--}}
    </form>
    <button v-if="loggedUserRole === 'site_admin'" class="btn pm-btn pm-btn-danger delete-user" type="button" @click="deleteUser"><i class="fas fa-trash-alt"></i></button>
@endsection

@section('main-content')
    <div class="container" id="user-view">
        <a class="btn pm-btn pm-btn-blue go-back" href="{{ route('user.index') }}">
            <i class="fas fa-arrow-circle-left mr-2"></i> Go Back
        </a>
        <b-card no-body>
            <b-tabs card>
                <b-tab title="CAMPAIGN" active>
                    @if($hasCampaigns)
                    <div class="row align-items-end no-gutters mb-md-3">
                        <div class="col-12 col-sm-5 col-lg-4">
                            <div class="form-group filter--form-group">
                                <label>Filter By Company</label>
                                <v-select :options="companies" label="name" v-model="campaignCompanySelected" class="filter--v-select" @input="onCampaignCompanySelected"></v-select>
                            </div>
                        </div>
                        <div class="col-none col-sm-2 col-lg-4"></div>
                        <div class="col-12 col-sm-5 col-lg-4">
                            <input type="text" v-model="searchCampaignForm.q" class="form-control filter--search-box" aria-describedby="search"
                                   placeholder="Search" @keyup.enter="fetchCampaigns">
                        </div>
                    </div>
                    @endif
                    <div class="row align-items-end no-gutters mt-3">
                        <div class="col-12">
                            <div class="loader-spinner" v-if="loadingCampaigns">
                                <spinner-icon></spinner-icon>
                            </div>
                            <div class="no-items-row" v-if="countActiveCampaigns === 0 && countInactiveCampaigns === 0">
                                No Items
                            </div>
                            <div class="campaign-group-label" v-if="countActiveCampaigns > 0">ACTIVE</div>
                            <campaign v-for="campaign in campaigns" v-if="campaign.status === 'Active'" :key="campaign.id" :campaign="campaign"></campaign>
                            <div class="campaign-group-label" v-if="countInactiveCampaigns > 0">INACTIVE</div>
                            <campaign v-for="campaign in campaigns" v-if="campaign.status !== 'Active'" :key="campaign.id" :campaign="campaign"></campaign>
                            @if($hasCampaigns)
                            <pm-pagination :pagination="campaignsPagination" @page-changed="onCampaignPageChanged"></pm-pagination>
                            @endif
                        </div>
                    </div>
                </b-tab>
                <b-tab title="COMPANY">
                    <div class="row align-items-end no-gutters mb-md-4">
                        <div class="col-12 col-sm-5 col-lg-4">
                        </div>
                        <div class="col-none col-sm-2 col-lg-4"></div>
                        <div class="col-12 col-sm-5 col-lg-4">
                            <input type="text" v-model="searchCompanyForm.q" class="form-control filter--search-box" aria-describedby="search"
                                   placeholder="Search" @keyup.enter="fetchCompanies">
                        </div>
                    </div>
                    <div class="row align-items-end no-gutters mt-3">
                        <div class="col-12">
                            <div class="loader-spinner" v-if="loadingCompanies">
                                <spinner-icon></spinner-icon>
                            </div>
                            <div class="no-items-row" v-if="countCompanies === 0">
                                No Items
                            </div>
                            <div class="company" v-for="company in companiesForList">
                                <div class="row no-gutters">
                                    <div class="col-12 col-md-4 company-info">
                                        <div class="company-info--image">
                                            <img src="" alt="">
                                        </div>
                                        <div class="company-info--data">
                                            <strong>@{{ company.name }}</strong>
                                            <p>@{{ company.address }}</p>
                                        </div>
                                    </div>
                                    <div class="col-4 col-md-3 company-role">
                                        <v-select :options="roles" :disabled="!canEditCompanyData(company)" v-model="company.role" class="filter--v-select" @input="updateCompanyData(company)" :clearable="false">
                                            <template slot="selected-option" slot-scope="option">
                                                @{{ option.label | userRole }}
                                            </template>
                                            <template slot="option" slot-scope="option">
                                                @{{ option.label | userRole }}
                                            </template>
                                        </v-select>
                                    </div>
                                    <div class="col-4 col-md-3 company-timezone">
                                        <v-select :options="timezones" :disabled="!canEditCompanyData(company)" v-model="company.timezone" class="filter--v-select" @input="updateCompanyData(company)" :clearable="false">
                                            <template slot="selected-option" slot-scope="option">
                                                @{{ option.label }}
                                            </template>
                                            <template slot="option" slot-scope="option">
                                                @{{ option.label }}
                                            </template>
                                        </v-select>
                                    </div>
                                    <div class="col-4 col-md-2 company-active-campaigns">
                                        <small>Active Campaigns</small>
                                        <div>
                                            <span class="pm-font-campaigns-icon"></span>
                                            <span class="company-active-campaigns--counter">@{{ company.active_campaigns_for_user }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <pm-pagination :pagination="companiesPagination" @page-changed="onCompanyPageChanged"></pm-pagination>
                        </div>
                    </div>
                </b-tab>
            </b-tabs>
        </b-card>
    </div>
@endsection
