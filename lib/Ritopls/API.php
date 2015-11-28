<?php
namespace Ritopls;

class API {
    private $API_KEY; // The API key
    private $region; // The current selected region
    private $regions = ['br', 'eune', 'euw', 'kr', 'lan', 'las', 'na', 'oce', 'ru', 'tr']; // All the regions available.
    public  $version = 1.0; // API version

    private $ch; // cURL object
    private $verifySSL = false; // Verify cURL SSL certificate
    private $timeout = 10; // After how much seconds to timeout the connection
    private $chOutput;

    public function __construct($API_KEY, $region) {
        if(empty($API_KEY) || empty($region)) throw new \InvalidArgumentException('API/Region are blank.');

        if(!in_array(strtolower($region), $this->regions)) {
            throw new \InvalidArgumentException('Cannot continue without a proper region.');
        }

        // Now just set some of the settings we will need
        $this->API_KEY = $API_KEY;
        $this->region  = $region;
    }

    /**
     * @param $endpoint API endpoint that's going to be appended
     * @param bool|FALSE $special one of the special APIs
     * @return string JSON decoded output
     * @throws \Exception
     */
    public function request($endpoint, $special = NULL) {
        // Set up cURL first
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, sprintf('https://%s.api.pvp.net/api/lol/%s/%s/?api_key=%s',
                                                      $this->region, $this->region, $endpoint, $this->API_KEY));
        if(!is_null($special)) {
            // Set the "special" URL here to it.
            curl_setopt($this->ch, CURLOPT_URL, $special);
        }

        // Set all the headers
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        if(!$this->verifySSL) curl_setopt($this->ch,  CURLOPT_SSL_VERIFYPEER, false); // Debug purposes only please.
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: ritopls ' . $this->version,
            'Accept-Language: en-US',
            'Accept-Charset: utf-8,ISO-8859-1',
        ));

        // Execute the request
        $this->chOutput = curl_exec($this->ch);
        if($this->chOutput === FALSE) {
            throw new Exceptions\APIException('cURL request failed: ', curl_error($this->ch));
        }

        // Grab the response data
        switch(curl_getinfo($this->ch)['http_code']) {
            case 400:
                throw new Exceptions\APIException('400: Bad Request');
            break;
            case 401:
                throw new Exceptions\APIException('401: Unauthorized');
            break;
            case 404:
                throw new Exceptions\APIException('404: No summoner data found for any specified inputs');
            break;
            case 429:
                throw new Exceptions\APIException('429: Rate limit exceeded.');
            break;
            case 500:
                throw new Exceptions\APIException('500: Internal Server Error');
            break;
            case 503:
                throw new Exceptions\APIException('503: Service unavailable');
            break;
        }

        // Close the connection
        curl_close($this->ch);

        // Return the data JSON decoded
        return json_decode($this->chOutput, true);
    }

    public function __destruct() {
        // Make sure we close this every time we end the API call.
        curl_close($this->ch);
        $this->ch = NULL;
    }
}
