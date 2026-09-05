<?php

namespace SparkPost\Test;

use PHPUnit\Framework\TestCase;
use SparkPost\SparkPost;

class TransmissionTest extends TestCase
{
    /** @var FakeHttpClient */
    private $client;

    /** @var SparkPost */
    private $resource;

    private $responseBody = ['results' => 'yay'];

    private $postTransmissionPayload = [
        'content' => [
            'from' => ['name' => 'Sparkpost Team', 'email' => 'postmaster@sendmailfor.me'],
            'subject' => 'First Mailing From PHP',
            'text' => 'Congratulations, {{name}}!! You just sent your very first mailing!',
        ],
        'substitution_data' => ['name' => 'Avi'],
        'recipients' => [
            [
                'address' => [
                    'name' => 'Vincent',
                    'email' => 'vincent.song@sparkpost.com',
                ],
            ],
            ['address' => 'test@example.com'],
        ],
        'cc' => [
            [
                'address' => [
                    'email' => 'avi.goldman@sparkpost.com',
                ],
            ],
        ],
        'bcc' => [
            ['address' => 'Emely Giraldo <emely.giraldo@sparkpost.com>'],
        ],

    ];

    private $getTransmissionPayload = [
        'campaign_id' => 'thanksgiving',
    ];

    public function setUp(): void
    {
        $this->client = new FakeHttpClient();
        $this->resource = new SparkPost($this->client, ['key' => 'SPARKPOST_API_KEY', 'async' => false]);
    }

    public function testInvalidEmailFormat()
    {
        $this->expectException(\Exception::class);

        $this->postTransmissionPayload['recipients'][] = [
            'address' => 'invalid email format',
        ];

        $this->resource->transmissions->post($this->postTransmissionPayload);
    }

    public function testGet()
    {
        $this->client->queue(200, $this->responseBody);

        $response = $this->resource->transmissions->get($this->getTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());

        $request = $this->client->requests[0][0];
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('https://api.sparkpost.com:443/api/v1/transmissions/?campaign_id=thanksgiving', $request['url']);
    }

    public function testGetWithUri()
    {
        $this->client->queue(200, $this->responseBody);

        $this->resource->transmissions->get('some-id');

        $this->assertEquals('https://api.sparkpost.com:443/api/v1/transmissions/some-id', $this->client->requests[0][0]['url']);
    }

    public function testPut()
    {
        $this->client->queue(200, $this->responseBody);

        $response = $this->resource->transmissions->put($this->getTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('PUT', $this->client->requests[0][0]['method']);
        $this->assertEquals($this->getTransmissionPayload, json_decode($this->client->requests[0][0]['body'], true));
    }

    public function testPost()
    {
        $this->client->queue(200, $this->responseBody);

        $response = $this->resource->transmissions->post($this->postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());

        $request = $this->client->requests[0][0];
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('https://api.sparkpost.com:443/api/v1/transmissions/', $request['url']);
        $sent = json_decode($request['body'], true);
        $this->assertEquals($this->resource->transmissions->formatPayload($this->postTransmissionPayload), $sent);
        $this->assertArrayNotHasKey('cc', $sent);
        $this->assertArrayNotHasKey('bcc', $sent);
    }

    public function testPostWithRecipientList()
    {
        $postTransmissionPayload = $this->postTransmissionPayload;
        $postTransmissionPayload['recipients'] = ['list_id' => 'SOME_LIST_ID'];

        $this->client->queue(200, $this->responseBody);

        $response = $this->resource->transmissions->post($postTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
        // Recipient lists are sent untouched.
        $this->assertEquals($postTransmissionPayload, json_decode($this->client->requests[0][0]['body'], true));
    }

    public function testDelete()
    {
        $this->client->queue(200, $this->responseBody);

        $response = $this->resource->transmissions->delete($this->getTransmissionPayload);

        $this->assertEquals($this->responseBody, $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('DELETE', $this->client->requests[0][0]['method']);
    }

    public function testFormatPayload()
    {
        $correctFormattedPayload = json_decode('{"content":{"from":{"name":"Sparkpost Team","email":"postmaster@sendmailfor.me"},"subject":"First Mailing From PHP","text":"Congratulations, {{name}}!! You just sent your very first mailing!","headers":{"CC":"avi.goldman@sparkpost.com"}},"substitution_data":{"name":"Avi"},"recipients":[{"address":{"name":"Vincent","email":"vincent.song@sparkpost.com"}},{"address":{"email":"test@example.com"}},{"address":{"email":"emely.giraldo@sparkpost.com","header_to":"\"Vincent\" <vincent.song@sparkpost.com>"}},{"address":{"email":"avi.goldman@sparkpost.com","header_to":"\"Vincent\" <vincent.song@sparkpost.com>"}}]}', true);

        $formattedPayload = $this->resource->transmissions->formatPayload($this->postTransmissionPayload);
        $this->assertEquals($correctFormattedPayload, $formattedPayload);
    }
}
