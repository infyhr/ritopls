<?php
namespace Ritopls\Components;

Class Summoner {
    private $username, $API;

    public function __construct(\Ritopls\API $API) {
        $this->API = $API;
        var_dump('Hello from Ritopls\Components\Summoner!', $this->API);

        $this->username = 'Ohaio';
    }

    public function getUsername() {
        return $this->username;
    }
}