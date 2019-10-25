<?php

declare(strict_types=1);

namespace JustSteveKing\LaravelPostcodes\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use function GuzzleHttp\Psr7\build_query;
use JustSteveKing\LaravelPostcodes\Service\BulkReverseGeocoding\Geolocation;

class PostcodeService
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var Client
     */
    protected $http;

    /**
     * Postcode Service constructor.
     *
     * @param Client $client
     *
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->url = config('services.postcodes.url');

        $this->http = $client;
    }

    /**
     * Validate a postcode against the API
     *
     * @param string $postcode
     *
     * @return bool
     */
    public function validate(string $postcode): bool
    {
        return $this->getResponse("postcodes/$postcode/validate");
    }

    /**
     * Get the address details from a postcode
     *
     * @param string $postcode
     *
     * @return object
     */
    public function getPostcode(string $postcode): object
    {
        return $this->getResponse("postcodes/$postcode");
    }

    /**
     * Get the address details from a multiple postcodes at once
     *
     * @param array $postcodes
     *
     * @param array $filter - optional array of fields to return
     * @return \Illuminate\Support\Collection
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getPostcodes(array $postcodes, array $filter = []): object
    {
        if (!empty($filter)) {
            $filter = build_query(['filter' => implode(',', $filter)]);
        }

        return collect($this->getResponse('postcodes?' . $filter, 'POST', ['postcodes' => array_values($postcodes)]))
            ->map(function ($item) {
                return $item->result;
            });
    }

    /**
     * Get information based on outward code including geo data
     *
     * @param string $outwardcode
     *
     * @return object
     */
    public function getOutwardCode(string $outwardcode): object
    {
        return $this->getResponse("outcodes/$outwardcode");
    }

    /**
     * Get the address details from a random postcode
     *
     * @return object
     */
    public function getRandomPostcode()
    {
        return $this->getResponse("random/postcodes");
    }

    /**
     * Query the API for a given string
     *
     * @param string $query
     *
     * @return array|null
     */
    public function query(string $query): ?array
    {
        $queryString = http_build_query(['q' => $query]);

        return $this->getResponse("postcodes?$queryString");
    }

    /**
     * Get data for the postcodes nearest to the passed postcode
     *
     * @param string $postcode
     *
     * return array|null
     */
    public function nearest(string $postcode): ?array
    {
        return $this->getResponse("postcodes/$postcode/nearest");
    }

    /**
     * Lookup a terminated postcode. Returns the postcode, year and month of termination.
     *
     * @param string $postcode
     *
     * @return object
     */
    public function getTerminatedPostcode($postcode)
    {
        return $this->getResponse("terminated_postcodes/$postcode");
    }

    /**
     * Autocomplete a postcode partial.
     *
     * @param string $partialPostcode
     *
     * @return array|null
     */
    public function autocomplete(string $partialPostcode): ?array
    {
        return $this->getResponse("postcodes/$partialPostcode/autocomplete");
    }

    /**
     * Get nearest outward codes for a given longitude & latitude
     *
     * @param float $latitude
     * @param float $longitude
     *
     * @return array|null
     */
    public function nearestOutwardCodesForGivenLngAndLat(float $longitude, float $latitude): ?array
    {
        return $this->getResponse(sprintf(
            'outcodes?lon=%s&lat=%s',
            $longitude,
            $latitude
        ));
    }

    /**
     * @param Geolocation[] $geolocations
     *
     * @return array|null
     * @throws GuzzleException
     */
    public function bulkReverseGeocoding(array $geolocations): ?array
    {
        $body = json_encode(array_map(function (Geolocation $geolocation) {
            return $geolocation->toArray();
        }, $geolocations));
        return $this->getResponse('postcodes', 'POST', $body);
    }

    /**
     * Get information about nearest outcodes based on outward code
     *
     * @param string $outwardcode
     *
     * @param int $limit Needs to be less than 100
     * @param int $radius Needs to be less than 25,000m
     * @return object
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getNearestOutwardCode(string $outwardcode, int $limit = 10, int $radius = 5000): object
    {
        $limit = ($limit > 100) ? 100 : $limit;
        $radius = ($radius > 100) ? 25000 : $radius;

        return collect($this->getResponse("outcodes/$outwardcode/nearest?limit=$limit&radius=$radius"));
    }

    /**
     * Get the response and return the result object
     *
     * @param string|null $uri
     * @param string $method
     * @param array $data - data to be sent in post/patch/put request
     * @param array $options - array of options to be passed to curl, if $data is passed 'json' will be overwritten
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getResponse(string $uri = null, string $method = 'GET', array $data = [], array $options = [])
    {
        $url = $this->url . $uri;

        if (!empty($data)) {
            $options['json'] = $data;
        }

        $request = $this->http->request(
            $method,
            $url,
            $options
        );

        return json_decode($request->getBody()->getContents())->result;
    }
}
