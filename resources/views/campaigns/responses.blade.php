@extends('campaigns.base')

@section('head-styles')
    <link href="{{ asset('css/campaigns-responses.css') }}" rel="stylesheet">
@endsection

@section('body-script')
    <script src="{{ asset('js/campaigns-responses.js') }}"></script>
@endsection

@section('main-content')
    <div class="container" id="campaign-responses" v-cloak>
        <div class="row">
            <div class="col-12 col-sm-10 col-md-8">
                <a href="{{ url('/campaign/' . $campaign->id . '/responses/export-responders') }}" class="download-response mb-3">
            <span class="icon">
                <download-icon></download-icon>
            </span>
                    <span class="description">
                <strong>RESPONDERS</strong>
                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet at autem culpa distinctio ex excepturi, fugiat fugit illo inventore labore nemo quidem ratione recusandae ut, veniam vero voluptatem voluptatum.</p>
            </span>
                </a>

                <a href="{{ url('/campaign/' . $campaign->id . '/responses/export-nonresponders') }}" class="download-response mb-3">
            <span class="icon">
                <download-icon></download-icon>
            </span>
                    <span class="description">
                <strong>NON-RESPONDERS</strong>
                <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Aliquid amet at autem culpa distinctio ex excepturi, fugiat fugit illo inventore labore nemo quidem ratione recusandae ut, veniam vero voluptatem voluptatum.</p>
            </span>
                </a>
            </div>
        </div>
    </div>
@endsection
