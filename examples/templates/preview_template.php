<?php

namespace Examples\Templates;

require dirname(__FILE__).'/../bootstrap.php';

use SparkPost\SparkPost;

// In these examples, fetch API key from environment variable
$sparky = new SparkPost(["key" => getenv('SPARKPOST_API_KEY')]);

$template_id = "PHP-example-template";

$promise = $sparky->request('POST', "templates/$template_id/preview?draft=true", [
    'substitution_data' => [
        'some_key' => 'some_value',
    ],
]);

try {
    $response = $promise->wait();
    echo $response->getStatusCode()."\n";
    print_r($response->getBody())."\n";
} catch (\Exception $e) {
    echo $e->getCode()."\n";
    echo $e->getMessage()."\n";
}
