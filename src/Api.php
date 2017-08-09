<?php namespace Likewinter\LolApi;

use DusanKasan\Knapsack\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Likewinter\LolApi\Models\ModelInterface;
use Psr\Http\Message\ResponseInterface;

use Likewinter\LolApi\ApiRequest\ApiRequestInterface;

class Api
{
    const DEFAULT_PATH = '/lol/';
    const RATE_LIMITS_TYPE = [
        'X-Rate-Limit-Count' => 'general',
        'X-App-Rate-Limit-Count' => 'app',
        'X-Method-Rate-Limit-Count' => 'method',
    ];
    const PLATFORMS_ENDPOINTS = [
        'BR1' => 'br1.api.riotgames.com',
        'EUN1' => 'eun1.api.riotgames.com',
        'EUW1' => 'euw1.api.riotgames.com',
        'JP1' => 'jp1.api.riotgames.com',
        'KR' => 'kr.api.riotgames.com',
        'LA1' => 'la1.api.riotgames.com',
        'LA2' => 'la2.api.riotgames.com',
        'NA1' => 'na1.api.riotgames.com',
        'OC1' => 'oc1.api.riotgames.com',
        'TR1' => 'tr1.api.riotgames.com',
        'RU' => 'ru.api.riotgames.com',
        'PBE1' => 'pbe1.api.riotgames.com',
    ];
    const REGIONS_PLATFORMS = [
        'BR' => 'BR1',
        'EUNE' => 'EUN1',
        'EUW' => 'EUW1',
        'JP' => 'JP1',
        'KR' => 'KR',
        'LAN' => 'LA1',
        'LAS' => 'LA2',
        'NA' => 'NA1',
        'OCE' => 'OC1',
        'TR' => 'TR1',
        'RU' => 'RU',
        'PBE' => 'PBE1',
    ];
    /**
     * @var string
     */
    private $apiKey;
    /**
     * @var Client
     */
    private $http;
    /**
     * @var array
     */
    private $rateLimits = [];
    /**
     * @var Uri
     */
    private $endpointURI;

    /**
     * Api constructor.
     *
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->http = $this->setupHttpClient();
        $this->endpointURI = new Uri('https:');
    }

    public function request(ApiRequestInterface $apiRequest): ModelInterface
    {
        $uri = $this->getUriForRequest($apiRequest);
        $options = [];
        if ($query = $apiRequest->getQuery()) {
            $options['query'] = $query;
        }
        $response = $this->http->get($uri, $options);
        $this->setRateLimits($response);
        $data = \GuzzleHttp\json_decode($response->getBody());
        $data->region = $apiRequest->getRegion();

        return $apiRequest
            ->getMapper()
            ->map($data)
            ->wireApi($this);
    }

    public function getRateLimits(): array
    {
        return $this->rateLimits;
    }

    private function setupHttpClient(): Client
    {
        return new Client([
            'headers' => [
                'X-Riot-Token' => $this->apiKey,
            ],
        ]);
    }

    private function setRateLimits(ResponseInterface $response): void
    {
        $this->rateLimits = Collection::from($response->getHeaders())
            ->only(['X-Rate-Limit-Count', 'X-App-Rate-Limit-Count', 'X-Method-Rate-Limit-Count'])
            ->reduce(function (array $limits, array $value, string $name) {
                foreach (explode(',', $value[0]) as $limit) {
                    [$requests, $seconds] = explode(':', $limit);
                    $limits[self::RATE_LIMITS_TYPE[$name]][] = [
                        'requests' => (int) $requests,
                        'seconds' => (int) $seconds,
                    ];
                }

                return $limits;
            }, []);
    }

    private function getUriForRequest(ApiRequestInterface $apiRequest): Uri
    {
        $host = self::PLATFORMS_ENDPOINTS[$apiRequest->getPlatform()];
        $path = self::DEFAULT_PATH . $apiRequest->getType() . '/';
        if ($version = $apiRequest->getVersion()) {
            $path .= "v{$version}/";
        }
        foreach ($apiRequest->getSubtypes() as $subtype => $value) {
            if ($subtype) {
                $path .= $subtype . '/' . $value . '/';
            } else {
                $path .= $value . '/';
            }
        }

        return $this->endpointURI
            ->withHost($host)
            ->withPath($path);
    }
}
