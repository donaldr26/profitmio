<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Profit Miner</title>

    <script src="{{ asset('js/new-app.js') }}" defer></script>
    <link href="{{ asset('css/new-app.css') }}" rel="stylesheet">
</head>
<body>
    <div id="app clearfix">
        <div class="sidebar-container">
            <div class="sidebar">
                @yield('sidebar')
            </div>
        </div>
        <div class="main-content-container">
            <div class="top-navbar">
                @component('layouts.top-navbar')
                @endcomponent
            </div>
            <div class="main-content">
                @yield('main-content')
            </div>
        </div>
    </div>
</body>
</html>