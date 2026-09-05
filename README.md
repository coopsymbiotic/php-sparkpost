<a href="https://www.sparkpost.com"><img src="https://www.sparkpost.com/sites/default/files/attachments/SparkPost_Logo_2-Color_Gray-Orange_RGB.svg" width="200px"/></a>

[Sign up](https://app.sparkpost.com/join?plan=free-0817?src=Social%20Media&sfdcid=70160000000pqBb&pc=GitHubSignUp&utm_source=github&utm_medium=social-media&utm_campaign=github&utm_content=sign-up) for a SparkPost account and visit our [Developer Hub](https://developers.sparkpost.com) for even more content.

# SparkPost PHP Library

[![Travis CI](https://travis-ci.org/SparkPost/php-sparkpost.svg?branch=master)](https://travis-ci.org/SparkPost/php-sparkpost)
[![Coverage Status](https://coveralls.io/repos/SparkPost/php-sparkpost/badge.svg?branch=master&service=github)](https://coveralls.io/github/SparkPost/php-sparkpost?branch=master)
[![Downloads](https://img.shields.io/packagist/dt/sparkpost/sparkpost.svg?maxAge=3600)](https://packagist.org/packages/sparkpost/sparkpost)
[![Packagist](https://img.shields.io/packagist/v/sparkpost/sparkpost.svg?maxAge=3600)](https://packagist.org/packages/sparkpost/sparkpost)

The official PHP library for using [the SparkPost REST API](https://developers.sparkpost.com/api/).

Before using this library, you must have a valid API Key. To get an API Key, please log in to your SparkPost account and generate one in the Settings page.

## Installation
**Please note: The composer package `sparkpost/php-sparkpost` has been changed to `sparkpost/sparkpost` starting with version 2.0.**

The recommended way to install the SparkPost PHP Library is through composer.

```
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

The library needs PHP 8.0 or later with the `curl` and `json` extensions. It has no other dependency.

Next, run the Composer command to install the SparkPost PHP Library:

```
composer require sparkpost/sparkpost
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
use SparkPost\SparkPost;
```

**Note:** Without composer the costs outweigh the benefits of using the PHP client library. A simple function like the one in [issue #164](https://github.com/SparkPost/php-sparkpost/issues/164#issuecomment-289888237) wraps the SparkPost API and makes it easy to use the API without resolving the composer dependencies.

## HTTP transport

The library talks to the API with PHP's `curl` extension directly, there is no HTTP client to install or configure.

```php
<?php
require 'vendor/autoload.php';

use SparkPost\SparkPost;

$sparky = new SparkPost(['key' => 'YOUR_API_KEY']);
?>
```

All `SparkPost` instances in a process share one `SparkPost\CurlClient`, so connections (TCP + TLS) are established once and reused for every request, HTTP/2 is used when the API offers it, and asynchronous requests are multiplexed. See [Sending in bulk](#sending-in-bulk) for details.

The previous constructor signature, `new SparkPost($httpClient, $options)`, still works: the HTTPlug client passed as first argument is ignored and curl is used instead.

## Initialization
#### new Sparkpost(options)
* `options`
    * Required: Yes
    * Type: `String` or `Array`
    * A valid Sparkpost API key or an array of options
* `options.key`
    * Required: Yes
    * Type: `String`
    * A valid Sparkpost API key
* `options.host`
    * Required: No
    * Type: `String`
    * Default: `api.sparkpost.com`
* `options.protocol`
    * Required: No
    * Type: `String`
    * Default: `https`
* `options.port`
    * Required: No
    * Type: `Number`
    * Default: 443
* `options.version`
    * Required: No
    * Type: `String`
    * Default: `v1`
* `options.async`
    * Required: No
    * Type: `Boolean`
    * Default: `true`
    * `async` defines if the `request` function returns a `SparkPostPromise` (the request is started immediately and runs in the background) or blocks and returns a `SparkPostResponse`
* `options.retries`
    * Required: No
    * Type: `Number`
    * Default: `0`
    * `retries` controls how many API call attempts the client makes after receiving a 5xx response or a connection level error
* `options.debug`
    * Required: No
    * Type: `Boolean`
    * Default: `false`
    * If `debug` is true, then all `SparkPostResponse` and `SparkPostException` instances will return any array of the request values through the function `getRequest`
* `options.timeout`
    * Required: No
    * Type: `Number`
    * Default: `30`
    * Maximum time in seconds for a whole request. Exceeding it throws a `SparkPostException` with `getCurlErrorNumber()` = `CURLE_OPERATION_TIMEDOUT`
* `options.connect_timeout`
    * Required: No
    * Type: `Number`
    * Default: `10`
    * Maximum time in seconds to establish a connection
* `options.curl_options`
    * Required: No
    * Type: `Array`
    * Default: `[]`
    * Extra `CURLOPT_*` => value pairs applied to every request, e.g. `[CURLOPT_PROXY => 'proxy:3128']` or `[CURLOPT_CAINFO => '/path/to/ca.pem']`. They are applied last and override the library's own settings

## Methods
### request(method, uri [, payload [, headers]])
* `method`
    * Required: Yes
    * Type: `String`
    * HTTP method for request
* `uri`
    * Required: Yes
    * Type: `String`
    * The URI to receive the request
* `payload`
    * Required: No
    * Type: `Array`
    * If the method is `GET` the values are encoded into the URL. Otherwise, if the method is `POST`, `PUT`, or `DELETE` the payload is used for the request body.
* `headers`
    * Required: No
    * Type: `Array`
    * Custom headers to be sent with the request.

### syncRequest(method, uri [, payload [, headers]])
Sends a synchronous request to the SparkPost API and returns a `SparkPostResponse`

### asyncRequest(method, uri [, payload [, headers]])
Sends an asynchronous request to the SparkPost API and returns a `SparkPostPromise`

### waitAll()
Blocks until every asynchronous request started through the HTTP client has settled. Results are delivered to the promises' `then()` callbacks; nothing is thrown from `waitAll()` itself.

### setHttpClient(httpClient)
* `httpClient`
    * Required: No
    * Type: `SparkPost\HttpClientInterface`
    * Replaces the transport. Pass `null` (the default) to use the shared `SparkPost\CurlClient`. Useful to plug in a fake client in tests, or a `new CurlClient($maxConcurrency)` with its own connection pool

### setOptions(options)
* `options`
    *  Required: Yes
    *  Type: `Array`
    * See constructor

## Endpoints
### transmissions
* **post(payload)**
    * `payload` - see request options
    * `payload.cc`
        * Required: No
        * Type: `Array`
        * Recipients to receive a carbon copy of the transmission
    * `payload.bcc`
        * Required: No
        * Type: `Array`
        * Recipients to discreetly receive a carbon copy of the transmission

## Examples

### Send An Email Using The Transmissions Endpoint
```php
<?php
require 'vendor/autoload.php';

use SparkPost\SparkPost;

// Good practice to not have API key literals in code - set an environment variable instead
// For simple example, use synchronous model
$sparky = new SparkPost(['key' => getenv('SPARKPOST_API_KEY'), 'async' => false]);

try {
    $response = $sparky->transmissions->post([
        'content' => [
            'from' => [
                'name' => 'SparkPost Team',
                'email' => 'from@sparkpostbox.com',
            ],
            'subject' => 'First Mailing From PHP',
            'html' => '<html><body><h1>Congratulations, {{name}}!</h1><p>You just sent your very first mailing!</p></body></html>',
            'text' => 'Congratulations, {{name}}!! You just sent your very first mailing!',
        ],
        'substitution_data' => ['name' => 'YOUR_FIRST_NAME'],
        'recipients' => [
            [
                'address' => [
                    'name' => 'YOUR_NAME',
                    'email' => 'YOUR_EMAIL',
                ],
            ],
        ],
        'cc' => [
            [
                'address' => [
                    'name' => 'ANOTHER_NAME',
                    'email' => 'ANOTHER_EMAIL',
                ],
            ],
        ],
        'bcc' => [
            [
                'address' => [
                    'name' => 'AND_ANOTHER_NAME',
                    'email' => 'AND_ANOTHER_EMAIL',
                ],
            ],
        ],
    ]);
    } catch (\Exception $error) {
        var_dump($error);
    }
print($response->getStatusCode());
$results = $response->getBody()['results'];
var_dump($results);
?>
```

More examples [here](./examples/):
### [Transmissions](./examples/transmissions/)
- Create with attachment
- Create with recipient list
- Create with cc and bcc
- Create with template
- Create
- Delete (scheduled transmission by campaign_id *only*)

### [Templates](./examples/templates/)
- Create
- Get
- Get (list) all
- Update
- Delete

### [Message Events](./examples/message-events/)
- get
- get (with retry logic)

### Send An API Call Using The Base Request Function

We provide a base request function to access any of our API resources.
```php
<?php
require 'vendor/autoload.php';

use SparkPost\SparkPost;

$sparky = new SparkPost([
    'key' => getenv('SPARKPOST_API_KEY'),
    'async' => false]);

$webhookId = 'afd20f50-865a-11eb-ac38-6d7965d56459';
$response = $sparky->request('DELETE', 'webhooks/' . $webhookId);
print($response->getStatusCode());
?>
```

> Be sure to not have a leading `/` in your resource URI.

For complete list of resources, refer to [API documentation](https://developers.sparkpost.com/api/).

## Handling Responses
The API calls either return a `SparkPostPromise` or `SparkPostResponse` depending on if `async` is `true` or `false`

### Synchronous
```php
$sparky->setOptions(['async' => false]);
try {
    $response = ... // YOUR API CALL GOES HERE

    echo $response->getStatusCode()."\n";
    print_r($response->getBody())."\n";
}
catch (\Exception $e) {
    echo $e->getCode()."\n";
    echo $e->getMessage()."\n";
}
```

### Asynchronous
Asynchronous an be handled in two ways: by passing callbacks or waiting for the promise to be fulfilled. Waiting acts like synchronous request.
##### Wait (Synchronous)
```php

$promise = ... // YOUR API CALL GOES HERE

try {
    $response = $promise->wait();
    echo $response->getStatusCode()."\n";
    print_r($response->getBody())."\n";
} catch (\Exception $e) {
    echo $e->getCode()."\n";
    echo $e->getMessage()."\n";
}

echo "I will print out after the promise is fulfilled";
```

##### Then (Asynchronous)
```php
$promise = ... // YOUR API CALL GOES HERE

$promise->then(
    // Success callback
    function ($response) {
        echo $response->getStatusCode()."\n";
        print_r($response->getBody())."\n";
    },
    // Failure callback
    function (Exception $e) {
        echo $e->getCode()."\n";
        echo $e->getMessage()."\n";
    }
);

echo "I will print out before the promise is fulfilled";

// Wait for this promise, or for every pending request with $sparky->waitAll().
$promise->wait();
```

## Sending in bulk
The transport is built for sending very large numbers of small requests (one transmission per email) to the same host:

* One `curl_multi` handle per process owns the connection cache. Every `SparkPost` instance uses it, so creating a new instance per email still reuses the same TCP/TLS connection.
* HTTP/2 is negotiated with the API, so concurrent requests are multiplexed on one connection.
* `Expect: 100-continue` is disabled, responses are accepted compressed, TCP keep-alive and `TCP_NODELAY` are on, and DNS answers are cached.
* Retries (`retries` option) cover 5xx responses and connection level errors such as a keep-alive connection closed by the server.

Synchronous requests already benefit from all of this. To overlap network latency, send asynchronously and let the client keep several requests in flight:

```php
$sparky = new SparkPost(['key' => getenv('SPARKPOST_API_KEY'), 'retries' => 2]);
// How many requests to keep in flight (default 10). Above this, the next
// call blocks until a slot frees up, so memory stays bounded.
$sparky->getHttpClient()->setMaxConcurrency(20);

foreach ($emails as $id => $email) {
    $sparky->transmissions->post($email)->then(
        function ($response) use ($id) { /* record $response->getBody()['results']['id'] */ },
        function ($exception) use ($id) { /* log $exception->getCode(), $exception->getBody() */ }
    );
}

$sparky->waitAll();
```

## Handling Exceptions
An exception will be thrown in two cases: the request could not be completed (connection failure, timeout, ...) or the server returns a status code of `400` or higher.

### SparkPostException
* **getCode()**
    * Returns the response status code of `400` or higher, or `0` when the request could not be completed
* **getMessage()**
    * Returns the exception message
* **getBody()**
    * If there is a response body it returns it as an `Array`. Otherwise it returns `null`.
* **getRequest()**
    * Returns an array with the request values `method`, `url`, `headers`, `body` when `debug` is `true`
* **getResponse()**
    * Returns the `SparkPostResponse` for status code errors, `null` otherwise
* **getCurlErrorNumber()**
    * Returns the `CURLE_*` error code when the request could not be completed, `0` otherwise


### Contributing
See [contributing](https://github.com/SparkPost/php-sparkpost/blob/master/CONTRIBUTING.md).
