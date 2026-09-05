<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\SparkPostResponse;

class SparkPostResponseTest extends TestCase
{
    /** @var SparkPostResponse */
    private $response;

    private $body = ['results' => ['id' => '123']];

    public function setUp(): void
    {
        $this->response = new SparkPostResponse(
            200,
            ['Content-Type' => 'application/json', 'X-Multi' => ['a', 'b']],
            json_encode($this->body),
            ['some' => 'request'],
            'OK',
            '2'
        );
    }

    public function testGetBody()
    {
        $this->assertEquals($this->body, $this->response->getBody());
        $this->assertEquals(json_encode($this->body), $this->response->getRawBody());
    }

    public function testGetBodyWhenNotJson()
    {
        $response = new SparkPostResponse(502, [], '<html>Bad Gateway</html>');
        $this->assertNull($response->getBody());
        $this->assertEquals('<html>Bad Gateway</html>', $response->getRawBody());
    }

    public function testGetProtocolVersion()
    {
        $this->assertEquals('2', $this->response->getProtocolVersion());
    }

    public function testWithProtocolVersion()
    {
        $new = $this->response->withProtocolVersion('1.1');
        $this->assertEquals('1.1', $new->getProtocolVersion());
        $this->assertEquals('2', $this->response->getProtocolVersion());
    }

    public function testGetHeaders()
    {
        $this->assertEquals(['Content-Type' => ['application/json'], 'X-Multi' => ['a', 'b']], $this->response->getHeaders());
    }

    public function testHasHeaderIsCaseInsensitive()
    {
        $this->assertTrue($this->response->hasHeader('content-type'));
        $this->assertTrue($this->response->hasHeader('CONTENT-TYPE'));
        $this->assertFalse($this->response->hasHeader('X-Missing'));
    }

    public function testGetHeader()
    {
        $this->assertEquals(['application/json'], $this->response->getHeader('content-type'));
        $this->assertEquals(['a', 'b'], $this->response->getHeader('x-multi'));
        $this->assertEquals([], $this->response->getHeader('x-missing'));
    }

    public function testGetHeaderLine()
    {
        $this->assertEquals('a, b', $this->response->getHeaderLine('X-Multi'));
        $this->assertEquals('', $this->response->getHeaderLine('X-Missing'));
    }

    public function testWithHeader()
    {
        $new = $this->response->withHeader('x-multi', 'c');
        $this->assertEquals(['c'], $new->getHeader('X-Multi'));
        $this->assertEquals(['a', 'b'], $this->response->getHeader('X-Multi'));
    }

    public function testWithAddedHeader()
    {
        $new = $this->response->withAddedHeader('x-multi', 'c');
        $this->assertEquals(['a', 'b', 'c'], $new->getHeader('X-Multi'));
        $this->assertEquals(['a', 'b'], $this->response->getHeader('X-Multi'));
    }

    public function testWithoutHeader()
    {
        $new = $this->response->withoutHeader('CONTENT-TYPE');
        $this->assertFalse($new->hasHeader('Content-Type'));
        $this->assertTrue($this->response->hasHeader('Content-Type'));
    }

    public function testGetRequest()
    {
        $this->assertEquals(['some' => 'request'], $this->response->getRequest());
        $this->assertNull((new SparkPostResponse(200))->getRequest());
    }

    public function testWithBody()
    {
        $new = $this->response->withBody('{"results":"other"}');
        $this->assertEquals(['results' => 'other'], $new->getBody());
        $this->assertEquals($this->body, $this->response->getBody());
    }

    public function testGetStatusCode()
    {
        $this->assertSame(200, $this->response->getStatusCode());
    }

    public function testWithStatus()
    {
        $new = $this->response->withStatus(404, 'Not Found');
        $this->assertSame(404, $new->getStatusCode());
        $this->assertEquals('Not Found', $new->getReasonPhrase());
        $this->assertSame(200, $this->response->getStatusCode());
    }

    public function testGetReasonPhrase()
    {
        $this->assertEquals('OK', $this->response->getReasonPhrase());
    }
}
