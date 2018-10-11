<?php

require_once __DIr__ . '/../vendor/autoload.php';

class testOrder extends PHPUnit_Framework_TestCase {

    protected $client;

    protected function setUp() {
        $theHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'Authorization' => 'Bearer EFA545D71E354EFBFA6CBE8BBAE9ED67'];

        $this->client = new GuzzleHttp\Client([
            'base_uri' => 'http://localhost',
            "headers" => $theHeaders
        ]);

    }

    public function testGet_ValidInput_OrderObject() {

        $response = $this->client->get('/delivery/v1/orders', [
            'query' => [
                'page' => 0,
                'limit' => 10
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        foreach ($data AS $order) {
            /*$this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('status', $order);
            $this->assertArrayHasKey('distance', $order);*/
        }
    }

    public function testPost_NewOrder_OrderObject() {
        $response = $this->client->post('/delivery/v1/order', [
            'json' => [
                "origin" => [
                    "28.704060",
                    "77.102493"
                ],
                "destination" => [
                    "28.535517",
                    "77.391029"
                ]
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        //$this->assertArrayHasKey('id', $data);
        //$this->assertArrayHasKey('status', $data);
        //$this->assertArrayHasKey('distance', $data);
    }

    public function testPut_NewOrder_OrderObject() {
        $response = $this->client->put('/delivery/v1/order/1', [
            'json' => [
                "status" => "taken"
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        var_dump($data);die;
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('code', $data);

    }
}
