<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use SparkPost\SparkPostResponse;

class SparkPostPromiseTest extends TestCase
{
    public function testInitialState()
    {
        $promise = new SparkPostPromise();
        $this->assertEquals(SparkPostPromise::PENDING, $promise->getState());
        $this->assertNull($promise->getRequest());
    }

    public function testResolveAndWait()
    {
        $response = new SparkPostResponse(200);
        $promise = new SparkPostPromise(null, ['debug' => 1]);
        $promise->resolve($response);

        $this->assertEquals(SparkPostPromise::FULFILLED, $promise->getState());
        $this->assertSame($response, $promise->wait());
        $this->assertNull($promise->wait(false));
        $this->assertEquals(['debug' => 1], $promise->getRequest());
    }

    public function testRejectAndWait()
    {
        $exception = new SparkPostException('nope', 500);
        $promise = new SparkPostPromise();
        $promise->reject($exception);

        $this->assertEquals(SparkPostPromise::REJECTED, $promise->getState());
        $this->assertNull($promise->wait(false));

        $this->expectExceptionObject($exception);
        $promise->wait();
    }

    public function testSettleOnlyOnce()
    {
        $promise = new SparkPostPromise();
        $promise->resolve('first');
        $promise->resolve('second');
        $promise->reject(new \Exception('third'));

        $this->assertEquals('first', $promise->wait());
    }

    public function testWaitFunctionIsCalledWithPromise()
    {
        $promise = new SparkPostPromise(function (SparkPostPromise $p) {
            $p->resolve('done');
        });

        $this->assertEquals('done', $promise->wait());
    }

    public function testWaitThrowsWhenNothingSettlesThePromise()
    {
        $promise = new SparkPostPromise(function () {
        });

        $this->expectException(\RuntimeException::class);
        $promise->wait();
    }

    public function testThenOnFulfilled()
    {
        $promise = new SparkPostPromise();
        $child = $promise->then(function ($value) {
            return $value.'!';
        });

        $this->assertEquals(SparkPostPromise::PENDING, $child->getState());
        $promise->resolve('yay');

        $this->assertEquals(SparkPostPromise::FULFILLED, $child->getState());
        $this->assertEquals('yay!', $child->wait());
    }

    public function testThenOnAlreadySettledPromise()
    {
        $promise = new SparkPostPromise();
        $promise->resolve('yay');

        $this->assertEquals('yay!', $promise->then(function ($value) {
            return $value.'!';
        })->wait());
    }

    public function testThenOnRejectedRecovers()
    {
        $promise = new SparkPostPromise();
        $promise->reject(new \Exception('boom'));

        $child = $promise->then(null, function ($e) {
            return 'recovered from '.$e->getMessage();
        });

        $this->assertEquals('recovered from boom', $child->wait());
    }

    public function testRejectionPropagatesWithoutHandler()
    {
        $exception = new \Exception('boom');
        $promise = new SparkPostPromise();
        $child = $promise->then(function () {
            $this->fail('onFulfilled must not run');
        });
        $promise->reject($exception);

        $this->assertEquals(SparkPostPromise::REJECTED, $child->getState());
        $this->expectExceptionObject($exception);
        $child->wait();
    }

    public function testValuePassesThroughWithoutHandler()
    {
        $promise = new SparkPostPromise();
        $child = $promise->then(null, function () {
        });
        $promise->resolve('yay');

        $this->assertEquals('yay', $child->wait());
    }

    public function testThrowingHandlerRejectsChild()
    {
        $promise = new SparkPostPromise();
        $child = $promise->then(function () {
            throw new \LogicException('handler failed');
        });
        $promise->resolve('yay');

        $this->expectException(\LogicException::class);
        $child->wait();
    }

    public function testHandlerReturningPromiseIsChained()
    {
        $inner = new SparkPostPromise();
        $promise = new SparkPostPromise();
        $child = $promise->then(function () use ($inner) {
            return $inner;
        });
        $promise->resolve('outer');

        $this->assertEquals(SparkPostPromise::PENDING, $child->getState());
        $inner->resolve('inner');
        $this->assertEquals('inner', $child->wait());
    }

    public function testChildWaitDrivesParent()
    {
        $promise = new SparkPostPromise(function (SparkPostPromise $p) {
            $p->resolve(1);
        });

        $this->assertEquals(2, $promise->then(function ($v) {
            return $v * 2;
        })->wait());
    }
}
