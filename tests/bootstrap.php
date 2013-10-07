<?php
error_reporting(E_ALL | E_STRICT);

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->add('SheKnows\\OoyalaApi', dirname(__DIR__) . '/src');
$loader->add('SheKnows\\OoyalaApi\\Tests', dirname(__DIR__) . '/tests');

Guzzle\Tests\GuzzleTestCase::setServiceBuilder(
    Guzzle\Service\Builder\ServiceBuilder::factory(array(
        'live.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key' => $_SERVER['API_KEY'],
                'api_secret' => $_SERVER['API_SECRET'],
                'request.options' => array(
                    'timeout' => 0,
                    'connect_timeout' => 0,
                ),
            ),
        ),
        'cdn.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key' => $_SERVER['API_KEY'],
                'api_secret' => $_SERVER['API_SECRET'],
                'base_url' => 'https://cdn-api.ooyala.com/{api_version}',
                'request.options' => array(
                    'timeout' => 0,
                    'connect_timeout' => 0,
                ),
            ),
        ),
        'cache.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key' => $_SERVER['API_KEY'],
                'api_secret' => $_SERVER['API_SECRET'],
                'base_url' => 'https://cdn-api.ooyala.com/{api_version}',
                'ooyala.cache' => array(
                    'max-age' => 900,
                    'stale-if-error' => 3600
                ),
                'request.options' => array(
                    'timeout' => 0,
                    'connect_timeout' => 0,
                ),
            ),
        ),
        'mock.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key'    => '123',
                'api_secret' => '456',
                'base_url'   => 'http://test.local',
                'request.options' => array(
                    'timeout' => 0,
                    'connect_timeout' => 0,
                ),
            )
        ),
    ))
);
