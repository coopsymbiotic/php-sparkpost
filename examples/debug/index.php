<?php

namespace Examples\Templates;

require dirname(__FILE__).'/../bootstrap.php';

use SparkPost\SparkPost;

/*
 * configure options in example-options.json
 */
$sparky = new SparkPost([
    "key" => getenv('SPARKPOST_API_KEY'),
    // fetch API KEY from environment variable
    "debug" => true
]);

$promise = $sparky->request('GET', 'templates');

try {
    $response = $promise->wait();

    var_dump($response);

    echo "Request:\n";
    print_r($response->getRequest());

    echo "Response:\n";
    echo $response->getStatusCode()."\n";
    print_r($response->getBody())."\n";
} catch (\Exception $e) {
    echo "Request:\n";
    print_r($e->getRequest());

    echo "Exception:\n";
    echo $e->getCode()."\n";
    echo $e->getMessage()."\n";
}
