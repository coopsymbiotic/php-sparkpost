<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\SparkPostException;
use SparkPost\SparkPostResponse;

class SparkPostExceptionTest extends TestCase
{
    public function testFromResponse()
    {
        $body = ['errors' => [['message' => 'Unauthorized.']]];
        $response = new SparkPostResponse(401, [], json_encode($body));

        $exception = SparkPostException::fromResponse($response, ['req' => 1]);

        $this->assertEquals(401, $exception->getCode());
        $this->assertEquals(json_encode($body), $exception->getMessage());
        $this->assertEquals($body, $exception->getBody());
        $this->assertSame($response, $exception->getResponse());
        $this->assertEquals(['req' => 1], $exception->getRequest());
        $this->assertEquals(0, $exception->getCurlErrorNumber());
    }

    public function testFromResponseWithNonJsonBody()
    {
        $exception = SparkPostException::fromResponse(new SparkPostResponse(502, [], 'Bad Gateway'));

        $this->assertEquals(502, $exception->getCode());
        $this->assertEquals('Bad Gateway', $exception->getMessage());
        $this->assertNull($exception->getBody());
    }

    public function testFromCurlError()
    {
        $exception = SparkPostException::fromCurlError(CURLE_OPERATION_TIMEDOUT, 'Operation timed out', ['req' => 1]);

        $this->assertEquals(0, $exception->getCode());
        $this->assertEquals(CURLE_OPERATION_TIMEDOUT, $exception->getCurlErrorNumber());
        $this->assertStringContainsString('Operation timed out', $exception->getMessage());
        $this->assertNull($exception->getBody());
        $this->assertNull($exception->getResponse());
        $this->assertEquals(['req' => 1], $exception->getRequest());
    }

    public function testPlainConstructor()
    {
        $previous = new \RuntimeException('inner');
        $exception = new SparkPostException('msg', 7, ['req' => 1], $previous);

        $this->assertEquals('msg', $exception->getMessage());
        $this->assertEquals(7, $exception->getCode());
        $this->assertEquals(['req' => 1], $exception->getRequest());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testLegacyWrappingConstructor()
    {
        $inner = SparkPostException::fromResponse(new SparkPostResponse(500, [], '{"errors":[]}'));
        $exception = new SparkPostException($inner, ['req' => 1]);

        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals('{"errors":[]}', $exception->getMessage());
        $this->assertEquals(['errors' => []], $exception->getBody());
        $this->assertEquals(['req' => 1], $exception->getRequest());
        $this->assertSame($inner, $exception->getPrevious());

        $generic = new SparkPostException(new \RuntimeException('plain', 3));
        $this->assertEquals('plain', $generic->getMessage());
        $this->assertEquals(3, $generic->getCode());
        $this->assertNull($generic->getRequest());
    }
}
