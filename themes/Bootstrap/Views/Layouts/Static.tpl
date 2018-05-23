<!DOCTYPE html>
<html lang="{{ Language::code() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title or 'Page' }} - {{ Config::get('app.name') }}</title>
@php

echo Assets::build('css', array(
    'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css',
    'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css',
    'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
    asset_url('css/bootstrap-xl-mod.min.css', 'themes/bootstrap'),
    asset_url('css/style.css', 'themes/bootstrap'),
));

echo Asset::render('css', 'header');

@endphp

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
@php

echo Assets::build('js', array(
    asset_url('js/sprintf.min.js'),
    'https://code.jquery.com/jquery-1.12.4.min.js',
));

echo Asset::render('js', 'header');

@endphp

</head>
<body>

<div class="container">
    <div class="row">
        <a style="outline: none;" href="<?= site_url(); ?>"><img src="<?= asset_url('images/nova.png') ?>" alt="<?= Config::get('app.name') ?>"></a>
        <h1><strong>{{ ($title !== 'Home') ? $title : ''; }}</strong></h1>
        <hr style="margin-top: 0;">
    </div>
    {{ $content; }}
</div>

@include('Themes/Bootstrap::Partials/Footer')

@php

echo Asset::build('js', array(
    'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js'
));

echo Asset::render('js', 'footer');

@endphp

</body>
</html>
