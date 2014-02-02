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

        // Update the request tick timer.
        touch('./TICK');
    }

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

    public static function all_champions($f2p = false) {
        if(!self::region_check('champion')) {
            throw new Exception('Currently set region (' . self::get('region') . ') is not available for the current request (all_champions).');
        }
        // Region passes. Does the user want free to play (f2p) champions?
        if($f2p) {
            $champions = self::request('v1.1', 'champion?freeToPlay=true');
        }else {
            $champions = self::request('v1.1', 'champion');
        }

        return $champions['champions'];
    }

    private static function search_for_champion($ident) {
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

    public static function get_champion_by_name($name) {
        return self::search_for_champion($name);
    }

    public static function get_champion_by_id($id) {
        return self::search_for_champion($id);
    }

    // public function test() {
    //     $obj = new stdClass;
    //     $obj->username = 'user';
    //     $obj->password = 'password';
    //     return $obj;
    // }
}

?>