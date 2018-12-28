<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Profit Miner</title>

    @yield('head-styles')
    @yield('head-script')
</head>
<body>
    <div id="app" class="clearfix {{ $hasSidebar ? 'has-sidebar' : 'no-sidebar' }}">
        @if (isset($hasSidebar) && $hasSidebar)
            @component('layouts.sidebar')
            @endcomponent
        @endif
        <div class="main-content-container">
            @component('layouts.top-navbar')
            @endcomponent
            <div class="main-content">
                @yield('main-content')
            </div>
        </div>
    </div>
    @yield('body-script')
</body>
</html>
