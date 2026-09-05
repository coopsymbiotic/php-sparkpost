# Migration Guide

This is a guide to help you make the switch when the SparkPost PHP library changes major versions.

## Migrating from 2.x to 3.0

### No more HTTP client to provide
The library now uses PHP's `curl` extension directly. Remove `php-http/*` and `guzzlehttp/*` from your `composer.json` if you only installed them for this library, and drop the client from the constructor:

#### 2.x
```php
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;

$httpClient = new GuzzleAdapter(new Client());
$sparky = new SparkPost($httpClient, ['key' => 'YOUR_API_KEY']);
```

#### 3.0
```php
$sparky = new SparkPost(['key' => 'YOUR_API_KEY']);
```

The 2.x form keeps working (the client argument is ignored), so this change can be made at your own pace.

### Responses, promises and exceptions
`SparkPostResponse`, `SparkPostPromise` and `SparkPostException` keep their public methods but no longer implement PSR-7 / HTTPlug interfaces. Code that type-hinted `Psr\Http\Message\ResponseInterface` or `Http\Promise\Promise` must use the SparkPost classes instead. `SparkPostException::getCode()` is `0` for connection level errors (see `getCurlErrorNumber()`).

### Asynchronous requests
`async` no longer depends on the client. Requests start immediately, at most `CurlClient::getMaxConcurrency()` at a time, and `wait()` or `SparkPost::waitAll()` collect the results.

## Migrating from 1.0 to 2.0

## Package name change
The composer package name has changed from `sparkpost/php-sparkpost` to `sparkpost/sparkpost`

### No more setupUnwrapped
We replaced the idea of 'wrapping' API resources with a simple `request` function. To see it in action, check out this [example](https://github.com/SparkPost/php-sparkpost/tree/2.0.0#send-an-api-call-using-the-base-request-function).

### `transmission` becomes `transmissions`
Transmission endpoints are now under `$sparky->transmissions` instead of `$sparky->transmission` to map more directly to the [API docs](https://developers.sparkpost.com/api/).

* We no longer map parameters to the API - we simplified. Instead custom mapping, now set the payload to match the API docs.
* The exceptions to the previous statement are `cc` and `bcc`. They are helpers to make it easier to add cc and bcc recipients. [Example](https://github.com/SparkPost/php-sparkpost/tree/2.0.0#send-an-email-using-the-transmissions-endpoint)

### Switched from Ivory Http Adapter to HTTPlug
Ivory Http Adapter was deprecated in favor of HTTPlug.

### Asynchronous support
We addeded in support for [asynchronous calls](https://github.com/SparkPost/php-sparkpost/tree/2.0.0#asynchronous) (assuming your client supports it).

### Example
#### 2.0
```php
try {
	$sparky->setOptions([ 'async' => false ]);
    // Build your email and send it!
    $results = $sparky->transmissions->post([
    	'content'=>[
	        'from'=>[
	            'name' => 'From Envelope',
	            'email' => 'from@sparkpostbox.com>'
	        ],
	        'subject'=>'First Mailing From PHP',
	        'html'=>'<html><body><h1>Congratulations, {{name}}!</h1><p>You just sent your very first mailing!</p></body></html>',
	        'text'=>'Congratulations, {{name}}!! You just sent your very first mailing!',
	    ],
        'substitution_data'=>['name'=>'YOUR FIRST NAME'],
        'recipients'=>[
            [
                'address'=>[
                    'name'=>'YOUR FULL NAME',
                    'email'=>'YOUR EMAIL ADDRESS'
                ]
            ]
        ]
    ]);
    echo 'Woohoo! You just sent your first mailing!';
} catch (\Exception $err) {
    echo 'Whoops! Something went wrong';
    var_dump($err);
}
```

#### 1.0
```php
try {
    // Build your email and send it!
    $results = $sparky->transmission->send([
        'from'=>[
            'name' => 'From Envelope',
            'email' => 'from@sparkpostbox.com>'
        ],
        'html'=>'<html><body><h1>Congratulations, {{name}}!</h1><p>You just sent your very first mailing!</p></body></html>',
        'text'=>'Congratulations, {{name}}!! You just sent your very first mailing!',
        'substitutionData'=>['name'=>'YOUR FIRST NAME'],
        'subject'=>'First Mailing From PHP',
        'recipients'=>[
            [
                'address'=>[
                    'name'=>'YOUR FULL NAME',
                    'email'=>'YOUR EMAIL ADDRESS'
                ]
            ]
        ]
    ]);
    echo 'Woohoo! You just sent your first mailing!';
} catch (\Exception $err) {
    echo 'Whoops! Something went wrong';
    var_dump($err);
}
```
