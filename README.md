ooyala-api-client
=================

A PHP library for Ooyala REST APIs

Development
-----------
The Ooyala client API uses composer to manage dependencies.
Included are a couple convenince Rake tasks for getting/running composer:

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

Run live tests agains the Ooyala API:

```shell
rake test:internet
```

Run the entire test suite:

```shell
rake test
```

License
-------
Ooyala API Client is © 2013 SheKnows, LLC. It is free software, and may be
redistributed under the terms specified in the LICENSE file.
