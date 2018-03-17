<!doctype html>
<html lang='en'>
<head>
    <meta charset='utf-8' />
    <title><?= meta("title") ?></title>
    <meta name='description' content='<?= meta("description") ?>'>
    <meta name='author' content='Kevin Albertson'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0' />

    <link rel='icon' href='img/favicon.png' type='image/png' />
    <link rel='stylesheet' href='style.css' />
    <link rel='stylesheet' href='vendor/highlightjs/atom-one-dark.css' />
    <script src='vendor/highlightjs/highlight.pack.js'></script>

    <style type='text/css'>
    </style>
    <!--[if lt IE 9]>
    <script src='http://html5shiv.googlecode.com/svn/trunk/html5.js'></script>
    <![endif]-->
</head>

<body>
    <article id='container'>
        <header>
            <div id='post-meta'>
                <span id='post-date'><?= meta('date') ?></span>
                <span id='post-author'>By <a href='http://kevinalbs.com'>Kevin Albertson</a></span>
            </div>
            <h1><?= meta("title") ?></h1>
            <h2><?= meta("subtitle") ?></h2>
        </header>
        <main>