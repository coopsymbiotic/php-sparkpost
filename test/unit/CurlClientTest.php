<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\CurlClient;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use SparkPost\SparkPostResponse;

/**
 * Integration tests for the curl transport, against PHP's built-in web
 * server running test/server/router.php.
 */
class CurlClientTest extends TestCase
{
    private static $server;
    private static $port;

    /** @var SparkPost */
    private $sparky;

    public static function setUpBeforeClass(): void
    {
        self::$port = 18000 + random_int(0, 999);
        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            self::$port,
            escapeshellarg(dirname(__DIR__).'/server/router.php')
        );
        self::$server = proc_open($command, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource(self::$server)) {
            self::markTestSkipped('Unable to start the built-in web server');
        }

        // Wait for the server to accept connections.
        for ($i = 0; $i < 50; ++$i) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);

                return;
            }
            usleep(100000);
        }
        self::markTestSkipped('The built-in web server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public function setUp(): void
    {
        $this->sparky = new SparkPost([
            'key' => 'TEST_KEY',
            'protocol' => 'http',
            'host' => '127.0.0.1',
            'port' => self::$port,
            'async' => false,
        ]);
    }

    public function testSharedClientIsASingleton()
    {
        $this->assertSame(CurlClient::shared(), CurlClient::shared());
        $this->assertSame(CurlClient::shared(), $this->sparky->getHttpClient());
    }

    public function testPostIsSentWithHeadersAndJsonBody()
    {
        $payload = ['content' => ['from' => 'a@example.com', 'subject' => 'Hi'], 'recipients' => [['address' => 'b@example.com']]];
        $response = $this->sparky->transmissions->post($payload, ['X-Custom' => 'yes']);

        $this->assertInstanceOf(SparkPostResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1.1', $response->getProtocolVersion());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('application/json', $response->getHeaderLine('content-type'));
        $this->assertEquals(['one', 'two'], $response->getHeader('X-Test-Header'));

        $results = $response->getBody()['results'];
        $this->assertEquals('POST', $results['method']);
        $this->assertEquals('/api/v1/transmissions/', $results['path']);
        $this->assertEquals($this->sparky->transmissions->formatPayload($payload), json_decode($results['body'], true));
        $this->assertEquals('TEST_KEY', $results['headers']['HTTP_AUTHORIZATION']);
        $this->assertEquals('application/json', $results['headers']['HTTP_CONTENT_TYPE']);
        $this->assertEquals('yes', $results['headers']['HTTP_X_CUSTOM']);
        $this->assertStringStartsWith('php-sparkpost/', $results['headers']['HTTP_USER_AGENT']);
        $this->assertArrayNotHasKey('HTTP_EXPECT', $results['headers'], 'Expect: 100-continue must be disabled');
    }

    public function testGetSendsQueryStringAndNoBody()
    {
        $response = $this->sparky->request('GET', 'events/message', ['campaign_ids' => ['a b', 'c'], 'ok' => true]);

        $results = $response->getBody()['results'];
        $this->assertEquals('GET', $results['method']);
        $this->assertEquals('campaign_ids=a%20b,c&ok=1', $results['query']);
        $this->assertEquals('', $results['body']);
    }

    public function testPutAndDelete()
    {
        $this->assertEquals('PUT', $this->sparky->request('PUT', 'templates/x', ['a' => 1])->getBody()['results']['method']);
        $this->assertEquals('{"a":1}', $this->sparky->request('PUT', 'templates/x', ['a' => 1])->getBody()['results']['body']);
        $this->assertEquals('DELETE', $this->sparky->request('DELETE', 'templates/x')->getBody()['results']['method']);
    }

    public function testCompressedResponsesAreDecoded()
    {
        $this->assertEquals(['results' => 'compressed'], $this->sparky->request('GET', 'gzip')->getBody());
    }

    public function testErrorStatusThrows()
    {
        try {
            $this->sparky->request('GET', 'status/400');
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals(['errors' => [['message' => 'status 400', 'code' => 1234]]], $e->getBody());
            $this->assertEquals(400, $e->getResponse()->getStatusCode());
            $this->assertNull($e->getRequest());
        }
    }

    public function testDebugAttachesRequestToResponseAndException()
    {
        $this->sparky->setOptions(['debug' => true]);

        $response = $this->sparky->request('GET', 'ok');
        $this->assertEquals('GET', $response->getRequest()['method']);

        try {
            $this->sparky->request('POST', 'status/500', ['x' => 1]);
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals('{"x":1}', $e->getRequest()['body']);
        }
    }

    public function testRetriesOn5xx()
    {
        $this->sparky->setOptions(['retries' => 3]);

        $key = uniqid('ok');
        $response = $this->sparky->request('GET', "flaky/$key/2");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(3, $response->getBody()['results']['attempts']);

        $key = uniqid('ko');
        try {
            $this->sparky->request('GET', "flaky/$key/10");
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals(503, $e->getCode());
            $this->assertEquals(4, $e->getBody()['errors'][0]['attempt'], '1 attempt + 3 retries');
        }
    }

    public function testNoRetriesByDefault()
    {
        $key = uniqid('nr');
        try {
            $this->sparky->request('GET', "flaky/$key/1");
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals(1, $e->getBody()['errors'][0]['attempt']);
        }
    }

    public function testConnectionErrorThrows()
    {
        $this->sparky->setOptions(['port' => 1, 'retries' => 1]);

        try {
            $this->sparky->request('GET', 'x');
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals(CURLE_COULDNT_CONNECT, $e->getCurlErrorNumber());
            $this->assertNull($e->getBody());
            $this->assertStringContainsString('cURL error', $e->getMessage());
        }
    }

    public function testTimeout()
    {
        $this->sparky->setOptions(['timeout' => 1]);

        $start = microtime(true);
        try {
            $this->sparky->request('GET', 'slow');
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals(CURLE_OPERATION_TIMEDOUT, $e->getCurlErrorNumber());
        }
        $this->assertLessThan(3, microtime(true) - $start);
        // The built-in server is single threaded: let the slow request finish
        // before other tests hit it.
        sleep(3);
    }

    public function testCurlOptionsOverride()
    {
        $this->sparky->setOptions(['curl_options' => [CURLOPT_REFERER => 'https://example.com/']]);

        $results = $this->sparky->request('GET', 'x')->getBody()['results'];
        $this->assertEquals('https://example.com/', $results['headers']['HTTP_REFERER']);
    }

    public function testAsyncRequestsRunConcurrentlyAndSettleInThen()
    {
        $this->sparky->setOptions(['async' => true]);
        $fulfilled = [];
        $rejected = [];
        $promises = [];

        for ($i = 0; $i < 25; ++$i) {
            $uri = $i % 5 === 0 ? 'status/500' : 'echo/'.$i;
            $promises[$i] = $this->sparky->request('POST', $uri, ['n' => $i])->then(
                function (SparkPostResponse $response) use (&$fulfilled, $i) {
                    $fulfilled[$i] = json_decode($response->getBody()['results']['body'], true)['n'];
                },
                function (SparkPostException $e) use (&$rejected, $i) {
                    $rejected[$i] = $e->getCode();
                }
            );
        }

        $this->sparky->waitAll();

        $this->assertEquals(0, CurlClient::shared()->getPendingCount());
        $this->assertCount(20, $fulfilled);
        $this->assertCount(5, $rejected);
        foreach ($fulfilled as $i => $n) {
            $this->assertEquals($i, $n, 'each response matches its request');
        }
        $this->assertEquals([0 => 500, 5 => 500, 10 => 500, 15 => 500, 20 => 500], $rejected);
        foreach ($promises as $promise) {
            $this->assertEquals(SparkPostPromise::FULFILLED, $promise->getState());
        }
    }

    public function testAsyncWaitOnOnePromise()
    {
        $this->sparky->setOptions(['async' => true]);

        $first = $this->sparky->request('GET', 'one');
        $second = $this->sparky->request('GET', 'status/404');

        $this->assertEquals('/api/v1/one', $first->wait()->getBody()['results']['path']);
        try {
            $second->wait();
            $this->fail('Expected an exception');
        } catch (SparkPostException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testConcurrencyIsBounded()
    {
        $client = new CurlClient(3);
        $this->sparky->setHttpClient($client);
        $this->sparky->setOptions(['async' => true]);

        $max = 0;
        for ($i = 0; $i < 10; ++$i) {
            $this->sparky->request('GET', 'x');
            $max = max($max, $client->getPendingCount());
        }
        $this->assertLessThanOrEqual(3, $max);

        $client->waitAll();
        $this->assertEquals(0, $client->getPendingCount());
    }

    public function testSyncRequestsAcrossInstancesReuseTheConnection()
    {
        $other = new SparkPost(['key' => 'OTHER', 'protocol' => 'http', 'host' => '127.0.0.1', 'port' => self::$port, 'async' => false]);

        $this->assertEquals('TEST_KEY', $this->sparky->request('GET', 'a')->getBody()['results']['headers']['HTTP_AUTHORIZATION']);
        $this->assertEquals('OTHER', $other->request('GET', 'b')->getBody()['results']['headers']['HTTP_AUTHORIZATION']);
        $this->assertSame($this->sparky->getHttpClient(), $other->getHttpClient());
    }
}
