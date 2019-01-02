@extends('layouts.base', [
    'hasSidebar' => false
])

@section('head-styles')
    <link href="{{ asset('css/campaign-index.css') }}" rel="stylesheet">
@endsection

@section('body-script')
    <script>
        window.searchFormUrl = "{{ route('campaign.for-user-display') }}";
        window.getCompanyUrl = "{{ route('company.for-dropdown') }}";
        window.companySelected = @json($companySelected);
        window.q = @json($q);
    </script>
    <script src="{{ asset('js/campaign-index.js') }}"></script>
@endsection

@section('main-content')
    <div class="container" id="campaign-index">
        <div class="row align-items-end no-gutters mb-md-3">
            <div class="col-12 col-sm-5 col-lg-3">
                <div class="form-group filter--form-group">
                    <label>Filter By Company</label>
                    <v-select :options="companies" label="name" v-model="companySelected" class="filter--v-select" @input="onCompanySelected"></v-select>
                </div>
            </div>
            <div class="col-none col-sm-2 col-lg-6"></div>
            <div class="col-12 col-sm-5 col-lg-3">
                <input type="text" v-model="searchForm.q" class="form-control filter--search-box" aria-describedby="search"
                       placeholder="Search" @keyup.enter="fetchData">
            </div>
        </div>
        <div class="row align-items-end no-gutters">
            <div class="col-12">
                <div class="loader-spinner" v-if="isLoading">
                    <spinner-icon></spinner-icon>
                </div>
                <campaign v-for="campaign in campaigns" :key="campaign.id" :campaign="campaign"></campaign>
                <pm-pagination :pagination="pagination" @page-changed="onPageChanged"></pm-pagination>
            </div>
        </div>
    </div>
@endsection
