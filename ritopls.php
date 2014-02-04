<?php
class ritopls {
    static $vars, $request_tick, $ch, $ch_info;

    /**
     * Sets a variable in a static $vars property.
     * @param string $var_name the variable name
     * @param mixed $var_value the variable value.
     * @return null
     */
    public static function set($var_name, $var_value = null) {
        if(is_array($var_name)) {
            foreach($var_name as $k => $v) {
                self::$vars[$k] = $v;
            }
        }else {
            self::$vars[$var_name] = $var_value;
        }
    }

    /**
     * Returns a variable from the static $vars property.
     * @param string $var_name Variable name.
     * @return mixed
     */
    public static function get($var_name) {
        if(array_key_exists($var_name, self::$vars) && isset(self::$vars[$var_name])) { return self::$vars[$var_name]; }
    }

    /**
     * Checks whether a variable is set in the static $vars property.
     * @param string $var_name Variable name.
     * @return boolean
     */
    public static function is_set($var_name) {
        if(isset(self::$vars[$var_name])) { return true; }else { return false; }
    }

    /**
     * Performs a cURL request to Riot's servers.
     * @param string $version API version
     * @param string $rest The rest of the URI (post-version)
     * @return array $data
     */
    private static function request($version, $rest) {
        // First, check the timer.
        if(file_exists('./TICK')) {
            self::$request_tick = filemtime('./TICK');

            // Have we got a fixed value for # of ticks?
            if(!self::is_set('1_req_per_second')) { self::set('1_req_per_second', 2); }

            /*echo 'request_tick:' . self::$request_tick . '<br>';
            echo 'time():' . time() . '<br>';

            echo 'request_tick - time() = ' . (time() - self::$request_tick) . '<br>';

            die;*/

            if((time() - self::$request_tick) < 2) {
                throw new Exception('Maximum number of requests reached.');
            }
        }else {
            throw new Exception('File TICK does not exists. Cannot limit API calls.');
        }

        self::$ch = curl_init();

        // Cannot continue without a proper region.
        if(!self::is_set('region')) { throw new Exception('Unable to continue without a region.'); }

        // Do we have a proper CURLOPT_TIMEOUT set up? If not, make the TTL 10s.
        if(!self::is_set('timeout')) { self::set('timeout', 10); }

        // Have we got more than 1 argument? If so, make it work with the API key $_GET parameter.
        if(strstr($rest, '?')) { $rest = $rest . '&'; }else { $rest = $rest . '?'; }

        curl_setopt(self::$ch, CURLOPT_URL, 'https://prod.api.pvp.net/api/lol/' . self::get('region') . '/' . $version . '/' . $rest . 'api_key=' . self::get('key'));
        curl_setopt(self::$ch, CURLOPT_HEADER, false);
        curl_setopt(self::$ch, CURLOPT_RETURNTRANSFER, true);
        if(self::is_set('no_ssl_verify')) {
            curl_setopt(self::$ch,  CURLOPT_SSL_VERIFYPEER, true);
        }
        curl_setopt(self::$ch, CURLOPT_TIMEOUT, self::get('timeout'));
        curl_setopt(self::$ch, CURLOPT_HTTPHEADER, array(
            'Accept-Language: en-US',
            'Accept-Charset: ISO-8859-1,utf-8',
            'Origin: https://developer.riotgames.com'
        ));

        // Run the request!
        $data = curl_exec(self::$ch);

        // Update the request tick timer, does not matter if it failed or not.
        touch('./TICK');

        // Did it succeed?
        if($data === FALSE) { throw new Exception('cURL failed. Last error output: ' . curl_error(self::$ch)); }

        // Grab the response data.
        self::$ch_info = curl_getinfo(self::$ch);

        // Do some checks.
        switch(self::$ch_info['http_code']) {
            case 400:
                throw new Exception('400 Bad request.');
            break;
            case 503:
                throw new Exception('503 Service unavailable.');
            break;
            case 401:
                throw new Exception('401 Unauthorized.');
            break;
            case 500:
                throw new Exception('500 Internal server error.');
            break;
        }

        // We are good to go, close the connection.
        curl_close(self::$ch);

        // Return the data json decoded, of course.
        return json_decode($data, true);
    }

    /**
     * Checks whether a certain API is available in the currently set region.
     * @param string $api API type
     * @param string $region The region itself. This is usually NULL because it's handled by get('region')
     * @return boolean
     */
    private static function region_check($api, $region = NULL) {
        if(!$region) { $region = self::get('region'); }
        switch($api) {
            case 'champion':
            case 'game':
            case 'stats':
            case 'summoner':
                if(in_array($region, ['br', 'eune', 'euw', 'na'])) { return true; }else { return false; }
            break;
            case 'team':
            case 'league':
                if(in_array($region, ['br', 'eune', 'euw', 'na', 'tr'])) { return true; }else { return false; }
            break;
        }
    }

    /**
     * Lists all champions. Accepts a parameter $f2p to indicate whether only free to play ones should be returned or not.
     * @param boolean $f2p Whether to return only free to play champions or not (true = yes)
     * @return array $champions
     */
    public static function all_champions($f2p = false) {
        if(!self::region_check('champion')) {
            throw new Exception('Currently set region (' . self::get('region') . ') does not implement the current API call (all_champions).');
        }
        // Region passes. Does the user want free to play (f2p) champions?
        if($f2p) {
            $champions = self::request('v1.1', 'champion?freeToPlay=true');
        }else {
            $champions = self::request('v1.1', 'champion');
        }

        return $champions['champions'];
    }

    /**
     * Searches for a champion and returns its data via either a champion ID or a champion name.
     * @param mixed $ident The identificator. If it's a string a user is looking for a champion by its name. Otherwise, a champion ID is assumed.
     * @return object $obj
     */
    public static function get_champion($ident) {
        // Get all champions.
        $all_champions = self::all_champions();

        // Get the identifier. If it's a string the user is looking for a champ's name, else it's an id.
        if(is_string($ident)) { $lookfor = 'name'; }else { $lookfor = 'id'; }

        // Now search!
        $key = NULL;
        foreach($all_champions as $k => $v) {
            if($v[$lookfor] == $ident) { $key = $k; }
        }

        // Incase nothing was found throw an Exception.
        if($key == NULL) { throw new Exception('Unable to find a champion named "' . $name . '"'); }

        // Got the key, construct the object.
        $obj                    = new stdClass;
        $obj->id                = $all_champions[$key]['id'];
        $obj->name              = $all_champions[$key]['name'];
        $obj->active            = $all_champions[$key]['active'];
        $obj->attackRank        = $all_champions[$key]['attackRank'];
        $obj->defenseRank       = $all_champions[$key]['defenseRank'];
        $obj->magicRank         = $all_champions[$key]['magicRank'];
        $obj->difficultyRank    = $all_champions[$key]['difficultyRank'];
        $obj->botEnabled        = $all_champions[$key]['botEnabled'];
        $obj->freeToPlay        = $all_champions[$key]['freeToPlay'];
        $obj->botMmEnabled      = $all_champions[$key]['botMmEnabled'];
        $obj->rankedPlayEnabled = $all_champions[$key]['rankedPlayEnabled'];

        // Return!
        return $obj;
    }

    /**
     * Return a summoner object with information related to the summoner either by summoner's uid or their name.
     * @param mixed $ident The identification. If it's an array, multiple objects are returned.
     *                     If it's a string/int, a single is returned.
     * @return object $obj
     */
    public static function get_summoner($ident) {
        // Let's see if this API is available.
        if(!self::region_check('summoner')) {
            throw new Exception('Currently set region (' . self::get('region') . ') does not implement the current API call (get_summoner).');
        }

        /* Alright, the ident can be either... an array, a string, or an integer.
           If it's an integer the user is looking for a single summoner by their ID.
           If it's a string, the user is looking for a single summoner by their name.
           And finally, if it's an array, multiple summoner are looked for. */
        switch($ident) {
            case is_array($ident):
                if(count($ident) > 39) { # 39 because arrays start with 0.
                    throw new Exception('Maximum number of IDs to retrieve at once is limited to 40.');
                }

                // Construct the URL by imploding the array into a CSV string.
                $rest = 'summoner/' . implode(',', $ident);
            break;
            case is_string($ident):
                // The user is looking for a summoner by their name. Fix the name according to the docs
                // @see https://developer.riotgames.com/api/methods#!/394/1392
                $ident = strtolower($ident);
                $ident = trim($ident);
                $ident = str_replace(' ', '', $ident);

                $rest = 'summoner/by-name/' . urlencode($ident); # Must be urlencoded, some names are in сyяиllис.
            break;
            case is_int($ident):
                // The user is looking for a summoner by their unique ID.
                $rest = 'summoner/' . $ident;
            break;
            default:
                throw new Exception('Unknown variable type.');
            break;
        }

        // Make the request now.
        $data = self::request('v1.3', $rest);

        // Check the type of the $ident to construct a valid object.
        if(is_string($ident) || is_int($ident)) {
            // This is the same now pretty much since it's only a single result.
            $obj                    = new stdClass;
            $obj->id                = $data[$ident]['id'];
            $obj->name              = $data[$ident]['name'];
            $obj->profileIconId     = $data[$ident]['profileIconId'];
            $obj->summonerLevel     = $data[$ident]['summonerLevel'];
            $obj->revisionDate      = $data[$ident]['revisionDate'];

            return $obj;
        }else {
            // More than one result, no point in returning this as an object, would only cause hassle.
            // Instead, return it as an array.
            return $data;
        }
    }

    /**
     * Returns a Summoner's username by the given ID. The ID can be either an array (for multiple Summoners)
     * or simply an integer for a single summoner.
     * @param mixed $id The ID of the target(s). If it's an array multiple usernames are returned.
     * @return mixed
     */
    public static function get_username_by_id($id) {
        // Can the user use this API?
        if(!self::region_check('summoner')) {
            throw new Exception('Currently set region (' . self::get('region') . ') does not implement the current API call (get_username_by_id).');
        }

        // Check whether $id is an array or not in order to fetch multiple or a single result.
        if(is_array($id)) {
            if(count($id) > 39) { throw new Exception('Maximum number of IDs to retrieve at once is limited to 40.'); }

            // Construct the URL by imploding the array into a CSV string.
            $rest = 'summoner/' . implode(',', $id) . '/name';
        }else {
            if(!is_int($id)) { return false; } # Must be a number.
            $rest = 'summoner/' . $id . '/name';
        }

        // Fire off the request!
        $data = self::request('v1.3', $rest);

        if(!is_int($id)) { return $data; } # Just return the array if it's not an int

        // Else just return the id as a string.
        return $data[$id];
    }
}

?>