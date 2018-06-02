<?php

namespace IPFS;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait to help reuse HTTP client logic.
 */
trait HttpClientTrait
{

    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    private $httpClient;

    /**
     * Default request options for the HTTP client.
     *
     * @var array
     */
    private $httpClientConfig = [
        // Security consideration: we must not use the certificate authority
        // file shipped with Guzzle because it can easily get outdated if a
        // certificate authority is hacked. Instead, we rely on the certificate
        // authority file provided by the operating system which is more likely
        // going to be updated in a timely fashion. This overrides the default
        // path to the pem file bundled with Guzzle.
        'verify' => true,
        'timeout' => 30,
        // Security consideration: prevent Guzzle from using environment
        // variables to configure the outbound proxy.
        'proxy' => [
            'http' => null,
            'https' => null,
            'no' => [],
        ],
    ];

    /**
     * Gets the HTTP client object.
     *
     * @return \GuzzleHttp\ClientInterface
     *   The HTTP client.
     */
    private function getHttpClient()
    {
        if (!isset($this->httpClient)) {
            $http_client_config = $this->getOption('http_client_config');

            // Shallow merge defaults underneath options.
            $config = $http_client_config + $this->httpClientConfig;

            $this->httpClient = new Client($config);
        }
        return $this->httpClient;
    }

    /**
     * Decodes an IPFS HTTP API response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *   A response object.
     *
     * @return array
     *   An array containing the values of a IPFS HTTP API response.
     */
    private function decodeResponse(ResponseInterface $response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Sets a specific HTTP client option.
     *
     * See http://docs.guzzlephp.org/en/stable/request-options.html for the list
     * of possible options to set.
     *
     * @param string $name
     *   The name of the HTTP client option to set.
     * @param mixed $value
     *   The value of the HTTP client option to set.
     */
    private function setHttpClientConfigOption($name, $value)
    {
        $this->httpClientConfig[$name] = $value;
    }
}
