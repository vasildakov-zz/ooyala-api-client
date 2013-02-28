ooyala-api-client
=================

A PHP library for Ooyala REST APIs

[![Build Status](https://api.travis-ci.org/sheknows/ooyala-api-client.png?branch=master)](https://travis-ci.org/sheknows/ooyala-api-client)

Development
-----------
The Ooyala client API uses composer to manage dependencies.
Included are a couple convenience Rake tasks for getting/running composer:

Install composer dependencies + dev dependencies:

```sh
rake composer:dev
```

Install required dependencies:

```sh
rake composer:install
```

_Note: Running Rake composer tasks requires curl_

Testing
-------
The library testing suite contains mocked response objects and some core tests against the live Ooyala API.

Run offline tests tests:

```shell
rake test:offline
```

Run live tests against the Ooyala API:

```shell
rake test:internet
```

Run the entire test suite:

```shell
rake test
```

Documentation
-------------
API documentation is available at [http://sheknows.github.com/ooyala-api-client/](http://sheknows.github.com/ooyala-api-client/). To build the documentation:

Make sure you have initialized submodules:
```
git submodule init
git submodule update
```

Run the API generator:
```shell
rake build:api
```

License
-------
Ooyala API Client is © 2013 SheKnows, LLC. It is free software, and may be
redistributed under the terms specified in the LICENSE file.
