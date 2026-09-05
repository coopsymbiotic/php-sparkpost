<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\CurlClient;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use SparkPost\SparkPostResponse;

class SparkPostTest extends TestCase
{
    /** @var FakeHttpClient */
    private $client;

    /** @var SparkPost */
    private $resource;

    private $responseBody = ['results' => 'yay'];
    private $errorBody = ['errors' => [['message' => 'boom']]];

    private $postTransmissionPayload = [
        'content' => [
            'from' => ['name' => 'Sparkpost Team', 'email' => 'postmaster@sendmailfor.me'],
            'subject' => 'First Mailing From PHP',
            'text' => 'Congratulations, {{name}}!! You just sent your very first mailing!',
        ],
        'substitution_data' => ['name' => 'Avi'],
        'recipients' => [
            ['address' => 'avi.goldman@sparkpost.com'],
        ],
    ];

    private $getTransmissionPayload = [
        'campaign_id' => 'thanksgiving',
    ];

    public function setUp(): void
    {
        $this->client = new FakeHttpClient();
        $this->resource = new SparkPost(['key' => 'SPARKPOST_API_KEY']);
        $this->resource->setHttpClient($this->client);
    }

    public function testConstructorWithOptionsOnly()
    {
        $sparky = new SparkPost(['key' => 'SPARKPOST_API_KEY']);
        $this->assertSame(CurlClient::shared(), $sparky->getHttpClient());
        $this->assertEquals('SPARKPOST_API_KEY', $sparky->getOptions()['key']);
    }

    public function testConstructorWithStringKey()
    {
        $sparky = new SparkPost('SPARKPOST_API_KEY');
        $this->assertEquals('SPARKPOST_API_KEY', $sparky->getOptions()['key']);
    }

    public function testConstructorWithLegacyClientArgument()
    {
        // Previous signature: an HTTPlug client followed by the options.
        $sparky = new SparkPost(new \stdClass(), ['key' => 'SPARKPOST_API_KEY', 'async' => false]);
        $this->assertSame(CurlClient::shared(), $sparky->getHttpClient());
        $this->assertFalse($sparky->getOptions()['async']);
    }

    public function testConstructorWithOurClientArgument()
    {
        $sparky = new SparkPost($this->client, ['key' => 'SPARKPOST_API_KEY']);
        $this->assertSame($this->client, $sparky->getHttpClient());
    }

    public function testConstructorRequiresKey()
    {
        $this->expectException(\Exception::class);
        new SparkPost([]);
    }

    public function testRequestSync()
    {
        $this->resource->setOptions(['async' => false]);
        $this->client->queue(200);

        $this->assertInstanceOf(SparkPostResponse::class, $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload));
    }

    public function testRequestAsync()
    {
        $this->resource->setOptions(['async' => true]);
        $this->client->queue(200);

        $this->assertInstanceOf(SparkPostPromise::class, $this->resource->request('GET', 'transmissions', $this->getTransmissionPayload));
    }

    public function testRequestValuesSentToClient()
    {
        $this->resource->setOptions(['async' => false, 'retries' => 2, 'timeout' => 7, 'connect_timeout' => 3, 'curl_options' => [CURLOPT_PROXY => 'proxy:3128']]);
        $this->client->queue(200);

        $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload, ['X-Custom' => 'yes']);

        list($request, $options) = $this->client->requests[0];
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('https://api.sparkpost.com:443/api/v1/transmissions', $request['url']);
        $this->assertEquals($this->postTransmissionPayload, json_decode($request['body'], true));
        $this->assertEquals('yes', $request['headers']['X-Custom']);
        $this->assertEquals('SPARKPOST_API_KEY', $request['headers']['Authorization']);
        $this->assertEquals(['retries' => 2, 'timeout' => 7, 'connect_timeout' => 3, 'curl_options' => [CURLOPT_PROXY => 'proxy:3128']], $options);
    }

    public function testGetRequestHasNoBody()
    {
        $this->resource->setOptions(['async' => false]);
        $this->client->queue(200);

        $this->resource->request('GET', 'transmissions', $this->getTransmissionPayload);

        $request = $this->client->requests[0][0];
        $this->assertNull($request['body']);
        $this->assertEquals('https://api.sparkpost.com:443/api/v1/transmissions?campaign_id=thanksgiving', $request['url']);
    }

    public function testDebugOptionWhenFalse()
    {
        $this->resource->setOptions(['async' => false, 'debug' => false]);
        $this->client->queue(200);

        $response = $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertNull($response->getRequest());
    }

    public function testDebugOptionWhenTrue()
    {
        $this->resource->setOptions(['async' => false, 'debug' => true]);

        // successful
        $this->client->queue(200);
        $response = $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);
        $this->assertEquals($this->postTransmissionPayload, json_decode($response->getRequest()['body'], true));

        // unsuccessful
        $this->client->queue(500, $this->errorBody);
        try {
            $this->resource->request('POST', 'transmissions', $this->postTransmissionPayload);
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals($this->postTransmissionPayload, json_decode($e->getRequest()['body'], true));
        }
    }

    public function testSuccessfulSyncRequest()
    {
        $this->client->queue(200);

        $response = $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $this->client->requests);
    }

    public function testUnsuccessfulSyncRequest()
    {
        $this->client->queue(500, $this->errorBody);

        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals($this->errorBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
            $this->assertEquals(json_encode($this->errorBody), $e->getMessage());
        }
    }

    public function testTransportErrorSyncRequest()
    {
        $this->client->queue(SparkPostException::fromCurlError(CURLE_COULDNT_CONNECT, 'Could not connect'));

        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertNull($e->getBody());
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(CURLE_COULDNT_CONNECT, $e->getCurlErrorNumber());
        }
    }

    public function testSuccessfulSyncRequestWithRetries()
    {
        $this->client->queue(503, $this->errorBody)->queue(503, $this->errorBody)->queue(200);

        $this->resource->setOptions(['retries' => 2]);
        $response = $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnsuccessfulSyncRequestWithRetries()
    {
        $this->client->queue(503, $this->errorBody)->queue(503, $this->errorBody)->queue(503, $this->errorBody);

        $this->resource->setOptions(['retries' => 2]);
        try {
            $this->resource->syncRequest('POST', 'transmissions', $this->postTransmissionPayload);
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals($this->errorBody, $e->getBody());
            $this->assertEquals(503, $e->getCode());
        }
    }

    public function testSuccessfulAsyncRequestWithWait()
    {
        $this->client->queue(200);

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $this->assertEquals(SparkPostPromise::PENDING, $promise->getState());
        $response = $promise->wait();

        $this->assertEquals(SparkPostPromise::FULFILLED, $promise->getState());
        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUnsuccessfulAsyncRequestWithWait()
    {
        $this->client->queue(500, $this->errorBody);

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);

        try {
            $promise->wait();
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals($this->errorBody, $e->getBody());
            $this->assertEquals(500, $e->getCode());
        }
        $this->assertEquals(SparkPostPromise::REJECTED, $promise->getState());
    }

    public function testSuccessfulAsyncRequestWithThen()
    {
        $this->client->queue(200);
        $called = false;

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $promise->then(function ($response) use (&$called) {
            $called = true;
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals($this->responseBody, $response->getBody());
        }, function () {
            $this->fail('onRejected should not be called');
        })->wait();

        $this->assertTrue($called);
    }

    public function testUnsuccessfulAsyncRequestWithThen()
    {
        $this->client->queue(500, $this->errorBody);
        $called = false;

        $promise = $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload);
        $promise->then(function () {
            $this->fail('onFulfilled should not be called');
        }, function ($exception) use (&$called) {
            $called = true;
            $this->assertEquals(500, $exception->getCode());
            $this->assertEquals($this->errorBody, $exception->getBody());
        })->wait();

        $this->assertTrue($called);
    }

    public function testWaitAllSettlesEveryPromise()
    {
        $this->client->queue(200)->queue(500, $this->errorBody)->queue(200);
        $fulfilled = 0;
        $rejected = 0;

        for ($i = 0; $i < 3; ++$i) {
            $this->resource->asyncRequest('POST', 'transmissions', $this->postTransmissionPayload)
                ->then(function () use (&$fulfilled) {
                    ++$fulfilled;
                }, function () use (&$rejected) {
                    ++$rejected;
                });
        }

        $this->resource->waitAll();

        $this->assertEquals(2, $fulfilled);
        $this->assertEquals(1, $rejected);
    }

    public function testGetHttpHeaders()
    {
        $headers = $this->resource->getHttpHeaders([
            'Custom-Header' => 'testing',
        ]);

        $version = $this->getProperty($this->resource, 'version');

        $this->assertEquals('SPARKPOST_API_KEY', $headers['Authorization']);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('testing', $headers['Custom-Header']);
        $this->assertEquals('php-sparkpost/'.$version, $headers['User-Agent']);
    }

    public function testGetUrl()
    {
        $url = 'https://api.sparkpost.com:443/api/v1/transmissions?key=value%201,value%202,value%203&flag=1';
        $testUrl = $this->resource->getUrl('transmissions', ['key' => ['value 1', 'value 2', 'value 3'], 'flag' => true]);
        $this->assertEquals($url, $testUrl);
    }

    public function testGetUrlWithoutPort()
    {
        $this->resource->setOptions(['port' => null, 'host' => 'api.eu.sparkpost.com']);
        $this->assertEquals('https://api.eu.sparkpost.com/api/v1/transmissions', $this->resource->getUrl('transmissions'));
    }

    public function testSetHttpClient()
    {
        $client = new FakeHttpClient();
        $this->resource->setHttpClient($client);
        $this->assertSame($client, $this->resource->getHttpClient());
    }

    public function testSetHttpClientFallsBackToCurl()
    {
        $this->resource->setHttpClient(new \stdClass());
        $this->assertSame(CurlClient::shared(), $this->resource->getHttpClient());

        $this->resource->setHttpClient(null);
        $this->assertSame(CurlClient::shared(), $this->resource->getHttpClient());
    }

    public function testSetOptionsStringKey()
    {
        $this->resource->setOptions('SPARKPOST_API_KEY');
        $this->assertEquals('SPARKPOST_API_KEY', $this->resource->getOptions()['key']);
    }

    public function testSetOptionsIgnoresUnknownKeys()
    {
        $this->resource->setOptions(['bogus' => 1]);
        $this->assertArrayNotHasKey('bogus', $this->resource->getOptions());
    }

    public function testSetBadOptions()
    {
        $this->expectException(\Exception::class);

        $this->setProperty($this->resource, 'options', []);
        $this->resource->setOptions(['not' => 'SPARKPOST_API_KEY']);
    }

    private function getProperty($object, $name)
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    private function setProperty($object, $name, $value)
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
