<?php

use Sami\Sami;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in(dirname(__DIR__) . '/src')
;

return new Sami($iterator, array(
    'title'     => 'SheKnows Ooyala API Client',
    'build_dir' => __DIR__ . '/build',
    'cache_dir' => __DIR__ . '/cache',
));