<?php

namespace SparkPost\Test;

use SparkPost\HttpClientInterface;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use SparkPost\SparkPostResponse;

/**
 * In-memory transport for unit tests: records requests and replays a queue
 * of canned responses when the promises are waited on.
 */
class FakeHttpClient implements HttpClientInterface
{
    /** @var array recorded [request, options, debugRequest] triples */
    public $requests = [];

    /** @var array queue of SparkPostResponse|\Throwable to answer with, in order */
    private $queue = [];

    /** @var SparkPostPromise[] promises not settled yet */
    private $pending = [];

    /**
     * @param int|SparkPostResponse|\Throwable $response - a status code (JSON body {"results":"yay"}), a response or an exception
     */
    public function queue($response, $body = ['results' => 'yay'])
    {
        if (is_int($response)) {
            $response = new SparkPostResponse($response, ['Content-Type' => 'application/json'], json_encode($body));
        }
        $this->queue[] = $response;

        return $this;
    }

    public function send(array $request, array $options = [], $debugRequest = null)
    {
        $this->requests[] = [$request, $options, $debugRequest];
        $promise = new SparkPostPromise([$this, 'settle'], $debugRequest);
        $this->pending[] = [$promise, $debugRequest, isset($options['retries']) ? (int) $options['retries'] : 0];

        return $promise;
    }

    public function waitAll()
    {
        while ($this->pending) {
            $this->settle($this->pending[0][0]);
        }
    }

    /**
     * Settles pending promises in order until the given one is settled,
     * applying the same retry-on-5xx rule as CurlClient.
     */
    public function settle(SparkPostPromise $promise)
    {
        while ($promise->getState() === SparkPostPromise::PENDING && $this->pending) {
            list($current, $debug, $retries) = array_shift($this->pending);

            $attempts = 0;
            do {
                if (!$this->queue) {
                    throw new \LogicException('FakeHttpClient: no queued response left');
                }
                $answer = array_shift($this->queue);
                ++$attempts;
                $retry = $answer instanceof SparkPostResponse && $answer->getStatusCode() >= 500 && $attempts <= $retries;
            } while ($retry);

            if ($answer instanceof \Throwable) {
                $current->reject($answer);
            } elseif ($answer->getStatusCode() >= 400) {
                $current->reject(SparkPostException::fromResponse($answer, $debug));
            } else {
                $current->resolve(new SparkPostResponse(
                    $answer->getStatusCode(),
                    $answer->getHeaders(),
                    $answer->getRawBody(),
                    $debug,
                    $answer->getReasonPhrase(),
                    $answer->getProtocolVersion()
                ));
            }
        }
    }
}
