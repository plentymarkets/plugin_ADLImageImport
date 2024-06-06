<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ApiService
{
    /**
     * @var Client
     */
    private $client;

    /**
     * ApiService constructor.
     */
    public function __construct()
    {
        $this->client = new GuzzleHttp\Client();
    }

    /**
     * @param string $url
     * @param string $accessToken
     * @param array $params
     *
     * @return mixed
     */
    public function get(string $url, string $accessToken, array $params = [])
    {
        try {
            $request = $this->client->get($url, [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => "Bearer " . $accessToken,
                    'Content-Type'  => 'application/json'
                ]
            ]);

            return [
                'statusCode' => $request->getStatusCode(),
                'response'   => json_decode($request->getBody()->getContents()),
                'header'     => $request->getHeaders()
            ];
        } catch (RequestException $exception) {
            return [
                "error_msg" => $exception->getMessage()
            ];
        }
    }

    /**
     * @param $url
     * @param $accessToken
     * @param $data
     * @return mixed
     * @throws Exception
     */
    public function post ($url, $accessToken, $data)
    {
        try {

            $request = $this->client->post(
                $url,
                [
                    'body'    => json_encode($data),
                    'headers' => [
                        'Accept'        => 'application/json',
                        'Authorization' => "Bearer " . $accessToken,
                        'Content-Type'  => 'application/json'
                    ]
                ]
            );

            return [
                'statusCode' => $request->getStatusCode(),
                'response'   => json_decode($request->getBody()->getContents())
            ];
        } catch (GuzzleHttp\Exception\RequestException $ex) {
            throw new Exception($ex->getMessage());
        }
    }
}
