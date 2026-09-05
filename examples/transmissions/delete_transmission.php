<?php

namespace Examples\Transmissions;

require dirname(__FILE__).'/../bootstrap.php';

use SparkPost\SparkPost;

// In these examples, fetch API key from environment variable
$sparky = new SparkPost(["key" => getenv('SPARKPOST_API_KEY')]);

// Delete *scheduled* transmissions (only) by *campaign ID* (only)
// See https://developers.sparkpost.com/api/transmissions/#transmissions-delete-delete-a-scheduled-transmission

$promise = $sparky->transmissions->delete('?campaign_id=white_christmas');

try {
    $response = $promise->wait();
    echo $response->getStatusCode()."\n";
    print_r($response->getBody())."\n";
} catch (\Exception $e) {
    echo $e->getCode()."\n";
    echo $e->getMessage()."\n";
}
