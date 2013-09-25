<?php
error_reporting(E_ALL | E_STRICT);

$loader = require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$loader->add('SheKnows\\OoyalaApi', dirname(__DIR__) . '/src');
$loader->add('SheKnows\\OoyalaApi\\Tests', dirname(__DIR__) . '/tests');

Guzzle\Tests\GuzzleTestCase::setServiceBuilder(
    Guzzle\Service\Builder\ServiceBuilder::factory(array(
        'live.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key' => $_SERVER['API_KEY'],
                'api_secret' => $_SERVER['API_SECRET'],
            ),
        ),
        'cdn.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key' => $_SERVER['API_KEY'],
                'api_secret' => $_SERVER['API_SECRET'],
                'base_url' => 'https://cdn-api.ooyala.com/{api_version}',
            ),
        ),
        'mock.ooyala-client' => array(
            'class' => 'SheKnows\\OoyalaApi\\OoyalaClient',
            'params' => array(
                'api_key'    => '123',
                'api_secret' => '456',
                'base_url'   => 'http://test.local'
            )
        ),
    ))
);
