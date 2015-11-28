<?php
namespace Ritopls\Components;

Class Summoner {
    private $API, $version = 'v1.4';

    /**
     * @param \Ritopls\API $API
     * Constructs the class by assigning the local API instance. Just ignore.
     */
    public function __construct(\Ritopls\API $API) {
        $this->API = $API; // Set the API.
    }

    /**
     * @internal param array $data Summoner data
     * @return string|void
     * Implodes an array of summoner data.
     */
    private function implodeData((array) $data) {
        if(count($data) > 40) {
            throw new \Ritopls\Exceptions\APIException('Too many details to retrieve at once. Maximum is 40.');
            return;
        }

        // We need to implode to a CSV if it's an array
        $data = implode(',', $data);
        if(!$data) {
            throw new \DomainException('Failed to implode the data.');
            return;
        }

        return $data;
    }

    /**
     * @param int|array One Summoner ID as an integer, or many as an array
     * @return array JSON decoded API response array.
     * @throws \Ritopls\Exceptions\APIException
     * Get summoner objects mapped by summoner ID for a given list of summoner IDs
     */
    public function getSummonerById($ids) {
        if(is_array($ids)) $ids = $this->implodeData($ids);

        return $this->API->request($this->version, 'summoner/' . $ids);
    }

    /**
     * @param string|array $names One summoner name as a string, or many as an array.
     * @return array JSON decoded API response array.
     * @throws \Ritopls\Exceptions\APIException
     * Get summoner objects mapped by standardized summoner name for a given list of summoner names
     */
    public function getSummonerByName($names) {
        if(is_array($names)) $names = $this->implodeData($names);

        return $this->API->request($this->version, 'summoner/by-name/' . $names);
    }

    /**
     * @param int|array One Summoner ID as an integer, or many as an array
     * @return array JSON decoded API response array.
     * @throws \Ritopls\Exceptions\APIException
     * Get mastery pages mapped by summoner ID for a given list of summoner IDs
     */
    public function getMasteries($summoner) {
        if(is_array($summoner)) $summoner = $this->implodeData($summoner);

        return $this->API->request($this->version, 'summoner/' . $summoner . '/masteries');
    }

    /**
     * @param int|array One Summoner ID as an integer, or many as an array
     * @return array JSON decoded API response array.
     * @throws \Ritopls\Exceptions\APIException
     * Get summoner names mapped by summoner ID for a given list of summoner IDs.
     */
    public function getNameById($name) {
        if(is_array($name)) $names = $this->implodeData($names);

        return $this->API->request($this->version, 'summoner/' . $names . '/name');
    }

    /**
     * @param int|array One Summoner ID as an integer, or many as an array
     * @return array JSON decoded API response array.
     * @throws \Ritopls\Exceptions\APIException
     * Get rune pages mapped by summoner ID for a given list of summoner IDs
     */
    public function getRunes($summoner) {
        if(is_array($summoner)) $summoner = $this->implodeData($summoner);

        return $this->API->request($this->version, 'summoner/' . $summoner . '/runes');
    }
}